import { expect, test } from "@playwright/test";

test("server errors identify fields and receive focus", async ({ page }) => {
  await page.goto("/withdrawal");
  await page.locator(".withdrawal form").evaluate((form: HTMLFormElement) => {
    form.noValidate = true;
  });
  await page.locator("[name=email]").fill("not-an-email");
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.getByRole("alert")).toBeFocused();
  for (const field of ["name", "contractReference", "email"]) {
    const input = page.locator(`[name=${field}]`);
    await expect(input).toHaveAttribute("aria-invalid", "true");
    const errorId = await input.getAttribute("aria-describedby");
    await expect(page.locator(`[id="${errorId}"]`)).not.toBeEmpty();
  }
  await page.getByRole("alert").getByRole("link").first().click();
  await expect(page.locator("[name=name]")).toBeFocused();
});

test("review supports keyboard correction without JavaScript", async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  await page.goto("http://127.0.0.1:8081/withdrawal");
  await page.getByLabel("Name", { exact: true }).fill("Keyboard Tester");
  await page.getByLabel(/Vertragsangaben/).fill("OLD");
  await page.getByLabel("E-Mail für die Bestätigung").fill("keyboard@example.test");
  await page.getByRole("button", { name: "Angaben prüfen" }).focus();
  await page.keyboard.press("Enter");
  await expect(page.getByRole("heading", { name: "Vertrag widerrufen" })).toBeFocused();
  await page.getByRole("button", { name: "Angaben ändern" }).focus();
  await page.keyboard.press("Enter");
  await expect(page.getByLabel("Name", { exact: true })).toHaveValue("Keyboard Tester");
  await page.getByLabel(/Vertragsangaben/).fill("CORRECTED");
  await page.getByRole("button", { name: "Angaben prüfen" }).click();
  await expect(page.locator("dl")).toContainText("CORRECTED");
  await context.close();
});
