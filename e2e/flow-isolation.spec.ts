import { execFileSync } from "node:child_process";
import { expect, test, type Page } from "@playwright/test";

async function review(page: Page, reference: string) {
  await page.goto("/withdrawal");
  await page.locator("[name=name]").fill("Tab Tester");
  await page.locator("[name=contractReference]").fill(reference);
  await page.locator("[name=email]").fill("tabs@example.test");
  await page.waitForTimeout(1100);
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.getByRole("button", { name: "Widerruf bestätigen" })).toBeVisible();
}

test("each tab confirms exactly the declaration it reviewed", async ({ page, context }) => {
  const second = await context.newPage();
  const reference = `TAB-A-${Date.now()}`;
  await review(page, reference);
  await review(second, `TAB-B-${Date.now()}`);
  await page.getByRole("button", { name: "Widerruf bestätigen" }).click();
  await expect(page.getByText("Ihr Widerruf ist eingegangen.")).toBeVisible();
  const stored = execFileSync(
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
      "SELECT contractReference FROM tl_withdrawal ORDER BY id DESC LIMIT 1",
    ],
    { encoding: "utf8" },
  );
  expect(stored.trim()).toBe(reference);
  await second.getByRole("button", { name: "Widerruf bestätigen" }).click();
  await expect(second.getByText("Ihr Widerruf ist eingegangen.")).toBeVisible();
  await page.reload();
  await expect(page.getByText("Ihr Widerruf ist eingegangen.")).toBeVisible();
});
