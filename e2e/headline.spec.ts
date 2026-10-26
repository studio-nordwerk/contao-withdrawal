import { execFileSync } from "node:child_process";
import { expect, test } from "@playwright/test";

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

test("without an element headline the translated title is the only h1", async ({ page }) => {
  await page.goto("/withdrawal");
  const section = page.locator("section.withdrawal");
  await expect(section.getByRole("heading")).toHaveCount(1);
  await expect(
    section.getByRole("heading", { level: 1, name: "Vertrag widerrufen" }),
  ).toBeVisible();
  // Stylesheets belong in the document head, once per page.
  await expect(page.locator("#container link, #container style")).toHaveCount(0);
  await expect(page.locator('head link[href*="withdrawal-base.css"]')).toHaveCount(1);
  await expect(page.locator('head link[href*="withdrawal-base.css"]')).toHaveAttribute(
    "href",
    /\/bundles\/nordwerkwithdrawal\/withdrawal-base\.css\?v=[0-9a-f]{12}$/,
  );
});

test("an element headline replaces the title and keeps its level and the focus handling", async ({
  page,
}) => {
  const before = sql("SELECT HEX(headline) FROM tl_content WHERE type = 'withdrawal' LIMIT 1");
  const headline = 'a:2:{s:4:"unit";s:2:"h2";s:5:"value";s:18:"Widerruf erklären";}';
  sql(`UPDATE tl_content SET headline = '${headline}' WHERE type = 'withdrawal'`);

  try {
    await page.goto("/withdrawal");
    const section = page.locator("section.withdrawal");
    await expect(section.getByRole("heading")).toHaveCount(1);
    await expect(page.getByRole("heading", { level: 1 })).toHaveCount(0);
    const heading = section.getByRole("heading", { level: 2, name: "Widerruf erklären" });
    await expect(heading).toBeVisible();
    await expect(section).toHaveAttribute("aria-labelledby", (await heading.getAttribute("id"))!);

    await page.locator("[name=name]").fill("Headline Tester");
    await page.locator("[name=contractReference]").fill("HEADLINE-1");
    await page.locator("[name=email]").fill("headline@example.test");
    await page.getByRole("button", { name: "Angaben prüfen" }).click();
    await expect(heading).toBeFocused();
  } finally {
    const restore = before === "NULL" ? "NULL" : `UNHEX('${before}')`;
    sql(`UPDATE tl_content SET headline = ${restore} WHERE type = 'withdrawal'`);
  }
});
