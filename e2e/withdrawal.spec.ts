import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { expect, test } from "@playwright/test";

const mailpit = "http://127.0.0.1:8026";

function countRecords(): number {
  const output = execFileSync(
    "docker",
    [
      "compose",
      "exec",
      "-T",
      "db",
      "mariadb",
      "-ucontao",
      "-pcontao",
      "contao",
      "-N",
      "-e",
      "SELECT COUNT(*) FROM tl_withdrawal",
    ],
    { encoding: "utf8" },
  );
  return Number(output.trim());
}

async function messages(
  request: import("@playwright/test").APIRequestContext,
): Promise<{ total: number; messages: { ID: string }[] }> {
  const response = await request.get(`${mailpit}/api/v1/messages?limit=100`);
  expect(response.ok()).toBeTruthy();
  return response.json();
}

test("footer, two steps, immediate mails and idempotency", async ({ page, request }) => {
  const before = countRecords();
  const mailBefore = (await messages(request)).total;

  for (const path of ["/home", "/kontakt"]) {
    await page.goto(path);
    const link = page.getByRole("link", { name: "Vertrag widerrufen" });
    await expect(link).toBeVisible();
    await expect(link).toHaveAttribute("href", "/withdrawal");
  }

  await page.getByRole("link", { name: "Vertrag widerrufen" }).click();
  await expect(page.getByRole("heading", { name: "Vertrag widerrufen" })).toBeVisible();
  await page.locator("[name=name]").fill("Ada E2E");
  await page.locator("[name=contractReference]").fill("ORDER-E2E-123");
  await page.locator("[name=email]").fill("ada-e2e@example.test");
  await page.waitForTimeout(1100);
  await page.getByRole("button", { name: "Angaben prüfen" }).click();

  await expect(page.getByRole("button", { name: "Widerruf bestätigen" })).toBeVisible();
  expect(countRecords()).toBe(before);
  expect((await messages(request)).total).toBe(mailBefore);

  const csrf = await page.locator("input[name=REQUEST_TOKEN]").inputValue();
  const element = await page.locator("input[name=withdrawal_element]").inputValue();
  await page.getByRole("button", { name: "Widerruf bestätigen" }).click();
  await expect(page.getByText("Ihr Widerruf ist eingegangen.")).toBeVisible();
  await expect(page.locator("time")).toContainText(/\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}/);
  expect(countRecords()).toBe(before + 1);

  await expect.poll(async () => (await messages(request)).total).toBe(mailBefore + 2);
  const recent = (await messages(request)).messages.slice(0, 2);
  for (const summary of recent) {
    const response = await request.get(`${mailpit}/api/v1/message/${summary.ID}`);
    expect(response.ok()).toBeTruthy();
    const mail = JSON.stringify(await response.json());
    expect(mail).toContain("Ada E2E");
    expect(mail).toContain("ORDER-E2E-123");
    expect(mail).toMatch(/\d{2}\.\d{2}\.2026 \d{2}:\d{2}:\d{2}/);
  }

  await page.reload();
  expect(countRecords()).toBe(before + 1);
  expect((await messages(request)).total).toBe(mailBefore + 2);
  const repeated = await page.request.post("http://127.0.0.1:8081/withdrawal", {
    form: { REQUEST_TOKEN: csrf, withdrawal_element: element, withdrawal_action: "confirm" },
  });
  expect(repeated.ok()).toBeTruthy();
  expect(countRecords()).toBe(before + 1);
  expect((await messages(request)).total).toBe(mailBefore + 2);

  const env = readFileSync(".env", "utf8");
  const email = env.match(/^CONTAO_ADMIN_EMAIL=(.*)$/m)?.[1];
  const password = env.match(/^CONTAO_ADMIN_PASSWORD=(.*)$/m)?.[1];
  expect(email).toBeTruthy();
  expect(password).toBeTruthy();
  await page.goto("/contao?do=withdrawals");
  await page.locator("input[name=username]").fill(email!);
  await page.locator("input[name=password]").fill(password!);
  await page.locator("button[type=submit],input[type=submit]").first().click();
  const row = page.getByRole("row", { name: /Ada E2E/ }).first();
  await expect(row).toBeVisible();
  await row.locator('a[href*="act=edit"]').first().click();
  await page.locator("select[name=status]").selectOption("reviewed");
  await page.getByRole("button", { name: "Save and close" }).click();
  await expect(page.getByRole("row", { name: /Ada E2E.*Reviewed/ }).first()).toBeVisible();
});

test("honeypot prevents storage", async ({ page, request }) => {
  const before = countRecords();
  const mailBefore = (await messages(request)).total;
  await page.goto("/withdrawal");
  await page.locator("[name=name]").fill("Spam E2E");
  await page.locator("[name=contractReference]").fill("SPAM-123");
  await page.locator("[name=email]").fill("spam@example.test");
  await page.locator("[name=website]").fill("https://spam.example", { force: true });
  await page.waitForTimeout(1100);
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.getByRole("button", { name: "Widerruf bestätigen" })).toHaveCount(0);
  expect(countRecords()).toBe(before);
  expect((await messages(request)).total).toBe(mailBefore);
});

test("complete flow without JavaScript", async ({ browser, request }) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  const before = countRecords();
  const mailBefore = (await messages(request)).total;

  await page.goto("http://127.0.0.1:8081/withdrawal");
  await page.locator("[name=name]").fill("No JS E2E");
  await page.locator("[name=contractReference]").fill("ORDER-NOJS-123");
  await page.locator("[name=email]").fill("nojs@example.test");
  await page.waitForTimeout(1100);
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.getByRole("button", { name: "Widerruf bestätigen" })).toBeVisible();
  await page.getByRole("button", { name: "Widerruf bestätigen" }).click();
  await expect(page.getByText("Ihr Widerruf ist eingegangen.")).toBeVisible();
  expect(countRecords()).toBe(before + 1);
  await expect.poll(async () => (await messages(request)).total).toBe(mailBefore + 2);
  await context.close();
});
