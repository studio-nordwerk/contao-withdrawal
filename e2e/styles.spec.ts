import { readFileSync } from "node:fs";
import { expect, test, type Page } from "@playwright/test";

async function loginBackend(page: Page): Promise<void> {
  const env = readFileSync(".env", "utf8");
  await page.goto("/contao?do=withdrawal_settings");
  await page.locator("[name=username]").fill(env.match(/^CONTAO_ADMIN_EMAIL=(.*)$/m)![1]);
  await page.locator("[name=password]").fill(env.match(/^CONTAO_ADMIN_PASSWORD=(.*)$/m)![1]);
  await page.locator("button[type=submit],input[type=submit]").first().click();
  await page.goto("/contao?do=withdrawal_settings");
}

async function measure(page: Page, selector: string) {
  return page
    .locator(selector)
    .first()
    .evaluate((element) => {
      const style = getComputedStyle(element);

      return {
        height: element.getBoundingClientRect().height,
        radius: style.borderTopLeftRadius,
        borderWidth: style.borderTopWidth,
        fontSize: style.fontSize,
      };
    });
}

for (const colorScheme of ["light", "dark"] as const) {
  test(`the base styling gives buttons and fields one shape and follows the tokens (${colorScheme})`, async ({
    browser,
  }) => {
    const context = await browser.newContext({ colorScheme, javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto("/withdrawal");
    await expect(page.locator('link[href*="withdrawal-base.css"]')).toHaveCount(1);
    expect((await page.request.get("/bundles/nordwerkwithdrawal/withdrawal-base.css")).ok()).toBe(
      true,
    );

    const field = await measure(page, ".withdrawal input[name=name]");
    const button = await measure(page, ".withdrawal__button--primary");
    expect(field.height).toBeGreaterThanOrEqual(44);
    expect(button.height).toBe(field.height);
    expect(button.radius).toBe(field.radius);
    expect(button.borderWidth).toBe(field.borderWidth);
    expect(button.fontSize).toBe(field.fontSize);
    expect(field.radius).toBe("12px");
    await expect(page.getByRole("heading", { level: 1 })).toHaveCount(1);

    // A theme changes the tokens once, and the form follows.
    await page.addStyleTag({
      content: ":root { --nw-radius-control: 5px; --nw-control-height: 3.5rem; }",
    });
    const themedField = await measure(page, ".withdrawal input[name=name]");
    const themedButton = await measure(page, ".withdrawal__button--primary");
    expect(themedField.radius).toBe("5px");
    expect(themedButton.radius).toBe("5px");
    expect(themedField.height).toBe(56);
    expect(themedButton.height).toBe(56);

    // The focus ring is drawn from the token as well.
    await page.keyboard.press("Tab");
    const outline = await page.evaluate(() => {
      const style = getComputedStyle(document.activeElement as Element);

      return { style: style.outlineStyle, width: style.outlineWidth };
    });
    expect(outline).toEqual({ style: "solid", width: "3px" });
    await context.close();
  });
}

test("the base styling can be switched off in the backend", async ({ page }) => {
  await loginBackend(page);
  const checkbox = page.locator("#ctrl_baseStylesEnabled");
  await expect(checkbox).toBeChecked();

  try {
    await checkbox.uncheck();
    await page.getByRole("button", { name: "Speichern" }).click();
    await expect(page.getByText("Einstellungen gespeichert.")).toBeVisible();
    await page.goto("/withdrawal");
    await expect(page.locator('link[href*="withdrawal-base.css"]')).toHaveCount(0);
    await expect(page.locator(".withdrawal input[name=name]")).toBeVisible();
    await expect(page.getByRole("button", { name: "Angaben prüfen" })).toBeVisible();
  } finally {
    await page.goto("/contao?do=withdrawal_settings");
    await page.locator("#ctrl_baseStylesEnabled").check();
    await page.getByRole("button", { name: "Speichern" }).click();
    await expect(page.getByText("Einstellungen gespeichert.")).toBeVisible();
  }
  await page.goto("/withdrawal");
  await expect(page.locator('link[href*="withdrawal-base.css"]')).toHaveCount(1);
});
