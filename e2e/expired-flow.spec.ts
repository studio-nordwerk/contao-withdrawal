import { expect, test } from "@playwright/test";

test("expired CSRF offers a normal reload link without JavaScript", async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  await page.goto("http://127.0.0.1:8081/withdrawal");
  await page.locator("[name=name]").fill("Expired Tester");
  await page.locator("[name=contractReference]").fill("EXPIRED");
  await page.locator("[name=email]").fill("expired@example.test");
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await page.route("**/withdrawal", async (route) => {
    const data = new URLSearchParams(route.request().postData()!);
    data.set("REQUEST_TOKEN", "expired-token");
    await route.continue({ postData: data.toString() });
  });
  await page.getByRole("button", { name: "Widerruf bestätigen" }).click();
  await page.unrouteAll();
  const reload = page.getByRole("link", { name: "Formular neu öffnen" });
  await expect(reload).toHaveAttribute("href", "/withdrawal");
  await expect(page.getByText("Ihr Widerruf ist eingegangen.")).toHaveCount(0);
  await reload.click();
  await expect(page.getByLabel("Name", { exact: true })).toBeVisible();
  await context.close();
});

test("an expired review gives an actionable error instead of an empty form", async ({ page }) => {
  await page.goto("/withdrawal");
  const response = await page.request.post("/withdrawal", {
    form: {
      REQUEST_TOKEN: await page.locator("[name=REQUEST_TOKEN]").inputValue(),
      withdrawal_element: await page.locator("[name=withdrawal_element]").inputValue(),
      withdrawal_flow: "a".repeat(64),
      withdrawal_action: "confirm",
    },
  });
  expect(await response.text()).toContain("Sitzung ist abgelaufen");
});
