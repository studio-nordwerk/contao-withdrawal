import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { expect, test, type Page } from "@playwright/test";

const password = () => readFileSync(".env", "utf8").match(/^CONTAO_ADMIN_PASSWORD=(.*)$/m)![1];

function sql(statement: string): string {
  return execFileSync(
    "docker",
    [
      "compose",
      "exec",
      "-T",
      "db",
      "mariadb",
      "-ucontao",
      "-pcontao",
      "--default-character-set=utf8mb4",
      "contao",
      "-N",
      "-e",
      statement,
    ],
    { encoding: "utf8" },
  ).trim();
}

function createUser(username: string, modules: string[]): void {
  const list = modules.map((module) => `'${module}'`).join(",");
  const setup = `require 'vendor/autoload.php';
    $db = \\Doctrine\\DBAL\\DriverManager::getConnection(['driver'=>'pdo_mysql','host'=>'db','user'=>'contao','password'=>'contao','dbname'=>'contao']);
    $db->delete('tl_user', ['username'=>'${username}']);
    $db->insert('tl_user', ['username'=>'${username}','name'=>'Settings audit','email'=>'${username}@example.test','language'=>'en','password'=>password_hash(getenv('CONTAO_ADMIN_PASSWORD'), PASSWORD_BCRYPT),'modules'=>serialize([${list}]),'inherit'=>'custom','tstamp'=>time(),'dateAdded'=>time()]);`;
  execFileSync("docker", ["compose", "exec", "-T", "php", "php", "-r", setup]);
}

async function login(page: Page, username: string, secret = password()): Promise<void> {
  await page.goto("/contao");
  await page.locator("[name=username]").fill(username);
  await page.locator("[name=password]").fill(secret);
  await page.locator("button[type=submit],input[type=submit]").first().click();
  await expect(page.locator("[name=password]")).toHaveCount(0);
}

test.describe.serial("settings record", () => {
  let backup = "";

  test.beforeAll(() => {
    backup = sql(
      "SELECT CONCAT_WS('|', HEX(merchantEmail), HEX(path), HEX(baseStylesEnabled), IFNULL(HEX(mailOptions), 'NULL')) FROM tl_withdrawal_settings WHERE id = 1",
    );
  });

  test.afterAll(() => {
    sql("DELETE FROM tl_withdrawal_settings WHERE id = 1");
    if (backup) {
      const [email, path, styles, mail] = backup.split("|");
      sql(
        `INSERT INTO tl_withdrawal_settings (id, tstamp, merchantEmail, path, baseStylesEnabled, mailOptions) VALUES (1, UNIX_TIMESTAMP(), UNHEX('${email}'), UNHEX('${path}'), UNHEX('${styles}'), ${mail === "NULL" ? "NULL" : `UNHEX('${mail}')`})`,
      );
    }
    sql("DELETE FROM tl_user WHERE username IN ('settings-editor', 'settings-denied')");
  });

  test("a record from an earlier release opens unchanged and saving keeps the mail texts", async ({
    page,
  }) => {
    // The state an installation of an earlier release (or a shop/seminar setup script) leaves behind.
    sql("DELETE FROM tl_withdrawal_settings WHERE id = 1");
    sql(
      `INSERT INTO tl_withdrawal_settings (id, tstamp, merchantEmail, path, mailOptions) VALUES (1, 1700000000, 'legacy@example.test', '/legacy-widerruf', '{"senderName":"Legacy Shop"}')`,
    );
    const env = readFileSync(".env", "utf8");
    await login(page, env.match(/^CONTAO_ADMIN_EMAIL=(.*)$/m)![1]);
    await page.goto("/contao?do=withdrawal_settings");
    await expect(page).toHaveURL(/act=edit&id=1/);
    await expect(page.locator("#ctrl_merchantEmail")).toHaveValue("legacy@example.test");
    await expect(page.locator("#ctrl_path")).toHaveValue("/legacy-widerruf");
    await expect(page.locator("input[type=checkbox][name=baseStylesEnabled]")).toBeChecked();

    await page.locator("#ctrl_merchantEmail").fill("new@example.test");
    await page.locator("#save").click();
    await expect(page.locator(".tl_error")).toHaveCount(0);
    expect(sql("SELECT merchantEmail FROM tl_withdrawal_settings WHERE id = 1")).toBe(
      "new@example.test",
    );
    expect(sql("SELECT mailOptions FROM tl_withdrawal_settings WHERE id = 1")).toContain(
      "Legacy Shop",
    );
    // Versioning is on, but the mail texts are maintained elsewhere and stay out of the versions.
    const version = sql(
      "SELECT data FROM tl_version WHERE fromTable = 'tl_withdrawal_settings' ORDER BY version DESC LIMIT 1",
    );
    expect(version).toContain("new@example.test");
    expect(version).not.toContain("mailOptions");
    await page.goto("/home");
    await expect(page.getByRole("link", { name: "Vertrag widerrufen" })).toHaveAttribute(
      "href",
      "/legacy-widerruf",
    );
  });

  test("without a record the module creates it with defaults", async ({ page }) => {
    sql("DELETE FROM tl_withdrawal_settings WHERE id = 1");
    const env = readFileSync(".env", "utf8");
    await login(page, env.match(/^CONTAO_ADMIN_EMAIL=(.*)$/m)![1]);
    await page.goto("/contao?do=withdrawal_settings");
    await expect(page).toHaveURL(/act=edit&id=1/);
    await expect(page.locator("#ctrl_merchantEmail")).toHaveValue("");
    await expect(page.locator("#ctrl_path")).toHaveValue("/withdrawal");
    await page.locator("#save").click();
    // The mandatory field blocks the submit in the browser already.
    await expect(page.locator("#ctrl_merchantEmail:invalid")).toHaveCount(1);
    await page.locator("#ctrl_merchantEmail").fill("defaults@example.test");
    await page.locator("#save").click();
    await expect(page.locator(".tl_error")).toHaveCount(0);
    expect(sql("SELECT COUNT(*) FROM tl_withdrawal_settings")).toBe("1");
  });

  test("the module permission alone opens the settings and the mail texts", async ({ page }) => {
    createUser("settings-editor", ["withdrawal_settings"]);
    await login(page, "settings-editor");
    await page.goto("/contao?do=withdrawal_settings");
    await expect(page.locator("#ctrl_merchantEmail")).toBeEditable();
    await page.getByRole("link", { name: "Edit mail texts" }).click();
    await expect(page).toHaveURL(/key=mails/);
    await expect(page.locator("[name=subject]")).toBeVisible();
    await page.locator("[name=senderName]").fill("Editor Shop");
    await page.getByRole("button", { name: "Absender speichern" }).click();
    await expect(page.getByText("Mail settings saved.")).toBeVisible();
    expect(sql("SELECT mailOptions FROM tl_withdrawal_settings WHERE id = 1")).toContain(
      "Editor Shop",
    );
    // Links of earlier releases still lead to the mail texts.
    await page.goto("/contao?do=withdrawal_settings&mail=withdrawal_merchant&locale=en");
    await expect(page).toHaveURL(/key=mails&mail=withdrawal_merchant&locale=en/);
    await page.getByRole("link", { name: "Zurück zu den Einstellungen" }).click();
    await expect(page).toHaveURL(/act=edit&id=1/);
  });

  test("without the module permission neither view is accessible", async ({ page }) => {
    createUser("settings-denied", ["article"]);
    await login(page, "settings-denied");
    for (const path of [
      "/contao?do=withdrawal_settings",
      "/contao?do=withdrawal_settings&act=edit&id=1",
      "/contao?do=withdrawal_settings&key=mails",
      "/contao?do=article&table=tl_withdrawal_settings&act=edit&id=1",
    ]) {
      const response = await page.goto(path);
      expect(response!.status(), path).toBeGreaterThanOrEqual(400);
      await expect(page.locator("#ctrl_merchantEmail")).toHaveCount(0);
      await expect(page.locator("[name=senderName]")).toHaveCount(0);
    }
  });
});
