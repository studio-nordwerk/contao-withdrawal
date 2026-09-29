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

test("autofill can proceed immediately without a minimum dwell time", async ({ page }) => {
  await page.goto("/withdrawal");
  const response = await page.request.post("/withdrawal", {
    form: {
      REQUEST_TOKEN: await page.locator("[name=REQUEST_TOKEN]").inputValue(),
      withdrawal_element: await page.locator("[name=withdrawal_element]").inputValue(),
      withdrawal_flow: await page.locator("[name=withdrawal_flow]").inputValue(),
      withdrawal_action: "review",
      name: "Fast Autofill",
      contractReference: "FAST",
      email: "fast@example.test",
    },
  });
  expect(await response.text()).toContain('value="confirm"');
});

test("an autofilled honeypot can be recovered without hidden field access", async ({ page }) => {
  await page.goto("/withdrawal");
  await page.locator("[name=name]").fill("Autofill Tester");
  await page.locator("[name=contractReference]").fill("AUTOFILL");
  await page.locator("[name=email]").fill("autofill@example.test");
  await page.locator("[name=website]").fill("https://example.test", { force: true });
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.getByRole("alert")).toContainText("automatisch");
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.getByRole("button", { name: "Widerruf bestätigen" })).toBeVisible();
});

test("Unicode email domains can pass browser and server validation", async ({ page }) => {
  await page.goto("/withdrawal");
  await page.locator("[name=name]").fill("李 Müller");
  await page.locator("[name=contractReference]").fill("Teil: Größe 🧥");
  await page.locator("[name=email]").fill("kunde@bücher.example");
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.getByRole("button", { name: "Widerruf bestätigen" })).toBeVisible();
});

test("parallel confirmations store once and browser Back cannot replace the reviewed snapshot", async ({
  page,
}) => {
  const count = () =>
    Number(
      execFileSync(
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
      ).trim(),
    );
  const before = count();
  await review(page, "IMMUTABLE-A");
  const form = {
    REQUEST_TOKEN: await page.locator("[name=REQUEST_TOKEN]").inputValue(),
    withdrawal_element: await page.locator("[name=withdrawal_element]").inputValue(),
    withdrawal_flow: await page.locator("[name=withdrawal_flow]").inputValue(),
    withdrawal_action: "confirm",
  };
  await page.getByRole("button", { name: "Angaben ändern" }).click();
  await page.locator("[name=contractReference]").fill("IMMUTABLE-B");
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  const responses = await Promise.all([
    page.request.post("/withdrawal", { form }),
    page.request.post("/withdrawal", { form }),
  ]);
  for (const response of responses)
    expect(await response.text()).toContain("Ihr Widerruf ist eingegangen.");
  expect(count()).toBe(before + 1);
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
  expect(stored.trim()).toBe("IMMUTABLE-A");
  await page.getByRole("button", { name: "Widerruf bestätigen" }).click();
  await expect(page.getByText("Ihr Widerruf ist eingegangen.")).toBeVisible();
  expect(count()).toBe(before + 2);
});

test("Unicode code points within server limits are not silently truncated by the browser", async ({
  page,
}) => {
  await page.goto("/withdrawal");
  const name = "𠮷".repeat(200);
  const reference = `ORDER ${"🧥".repeat(1200)}`;
  await page.locator("[name=name]").fill(name);
  await page.locator("[name=contractReference]").fill(reference);
  await page.locator("[name=email]").fill("unicode-length@example.test");
  expect([...(await page.locator("[name=name]").inputValue())].length).toBe(200);
  expect([...(await page.locator("[name=contractReference]").inputValue())].length).toBe(1206);
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.getByRole("button", { name: "Widerruf bestätigen" })).toBeVisible();
});

test("overlong pasted details are preserved for an explicit validation error", async ({ page }) => {
  await page.goto("/withdrawal");
  await page.locator("[name=name]").fill("x".repeat(256));
  await page.locator("[name=contractReference]").fill("y".repeat(2001));
  await page.locator("[name=email]").fill("length@example.test");
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.getByRole("alert")).toBeVisible();
  expect((await page.locator("[name=name]").inputValue()).length).toBe(256);
  expect((await page.locator("[name=contractReference]").inputValue()).length).toBe(2001);
});
