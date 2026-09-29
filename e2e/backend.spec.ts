import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { expect, test } from "@playwright/test";

test("untrusted declaration markup is text in frontend and backend", async ({ page }) => {
  const payload = '<img src="x" data-withdrawal-injection="yes">';
  await page.goto("/withdrawal");
  await page.locator("[name=name]").fill(`Audit ${payload}`);
  await page.locator("[name=contractReference]").fill(`AUDIT-XSS ${payload}`);
  await page.locator("[name=email]").fill("markup@example.test");
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.locator("[data-withdrawal-injection]")).toHaveCount(0);
  await expect(page.locator("dl")).toContainText(payload);
  await page.getByRole("button", { name: "Widerruf bestätigen" }).click();
  await expect(page.getByText("Ihr Widerruf ist eingegangen.")).toBeVisible();
  const env = readFileSync(".env", "utf8");
  await page.goto("/contao?do=withdrawals");
  await page.locator("[name=username]").fill(env.match(/^CONTAO_ADMIN_EMAIL=(.*)$/m)![1]);
  await page.locator("[name=password]").fill(env.match(/^CONTAO_ADMIN_PASSWORD=(.*)$/m)![1]);
  await page.locator("button[type=submit],input[type=submit]").first().click();
  await expect(page.locator("[data-withdrawal-injection]")).toHaveCount(0);
  const row = page.getByRole("row", { name: /AUDIT-XSS/ }).first();
  await expect(row).toContainText(payload);
  await expect(page.getByRole("link", { name: "Withdrawals", exact: true })).toHaveCount(2);
  await row.locator('a[href*="act=edit"]').first().click();
  await expect(page.locator("[name=submittedAt]")).toHaveValue(/Europe\/Berlin/);
  await page.locator("[name=consumerName]").evaluate((input) => input.removeAttribute("readonly"));
  await page.locator("[name=consumerName]").fill("TAMPERED");
  await page.getByRole("button", { name: "Save and close" }).click();
  await expect(page.getByRole("row", { name: /AUDIT-XSS/ }).first()).toContainText(
    `Audit ${payload}`,
  );
});

test("a backend account without withdrawal permission cannot access the list", async ({ page }) => {
  const setup = `require 'vendor/autoload.php';
    $db = \\Doctrine\\DBAL\\DriverManager::getConnection(['driver'=>'pdo_mysql','host'=>'db','user'=>'contao','password'=>'contao','dbname'=>'contao']);
    $db->delete('tl_user', ['username'=>'withdrawal-audit-denied']);
    $db->insert('tl_user', ['username'=>'withdrawal-audit-denied','name'=>'Permission audit','email'=>'denied@example.test','password'=>password_hash(getenv('CONTAO_ADMIN_PASSWORD'), PASSWORD_BCRYPT),'modules'=>serialize(['article']),'inherit'=>'custom','tstamp'=>time(),'dateAdded'=>time()]);`;
  execFileSync("docker", ["compose", "exec", "-T", "php", "php", "-r", setup]);
  try {
    const env = readFileSync(".env", "utf8");
    await page.goto("/contao?do=article");
    await page.locator("[name=username]").fill("withdrawal-audit-denied");
    await page.locator("[name=password]").fill(env.match(/^CONTAO_ADMIN_PASSWORD=(.*)$/m)![1]);
    await page.locator("button[type=submit],input[type=submit]").first().click();
    for (const path of ["/contao?do=withdrawals", "/contao?do=article&table=tl_withdrawal"]) {
      const response = await page.goto(path);
      expect(response!.status()).toBeGreaterThanOrEqual(400);
      await expect(page.getByRole("row", { name: /AUDIT-XSS/ })).toHaveCount(0);
    }
  } finally {
    execFileSync("docker", [
      "compose",
      "exec",
      "-T",
      "db",
      "mariadb",
      "-ucontao",
      "-pcontao",
      "contao",
      "-e",
      "DELETE FROM tl_user WHERE username='withdrawal-audit-denied'",
    ]);
  }
});
