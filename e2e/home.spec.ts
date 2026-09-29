import { expect, test } from "@playwright/test";

for (const language of ["de-DE,de;q=0.9", "en-US,en;q=0.9", "ja"]) {
  test(`the address / leads to the start page (Accept-Language ${language})`, async ({
    browser,
  }) => {
    const context = await browser.newContext({ extraHTTPHeaders: { "Accept-Language": language } });
    const page = await context.newPage();
    const response = await page.goto("/");
    expect(response?.status()).toBe(200);
    expect(new URL(page.url()).pathname).toBe("/home");
    await expect(page.getByRole("heading", { level: 1, name: "Willkommen" })).toBeVisible();
    await context.close();
  });
}
