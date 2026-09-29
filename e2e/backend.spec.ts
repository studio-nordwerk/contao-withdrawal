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
  await expect(page.getByRole("row", { name: /AUDIT-XSS/ }).first()).toContainText(payload);
});
