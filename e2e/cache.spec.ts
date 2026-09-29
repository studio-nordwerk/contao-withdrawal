import { expect, test } from "@playwright/test";

test("personal forms explicitly forbid storage by browsers and proxies", async ({
  page,
  browser,
}) => {
  const response = await page.goto("/withdrawal");
  expect(response!.headers()["cache-control"]).toContain("no-store");
  expect(response!.headers()["cache-control"]).toContain("private");
  const first = await page.locator("[name=withdrawal_flow]").inputValue();
  const secondContext = await browser.newContext();
  const second = await secondContext.newPage();
  await second.goto("http://127.0.0.1:8081/withdrawal");
  expect(await second.locator("[name=withdrawal_flow]").inputValue()).not.toBe(first);
  await secondContext.close();
});

test("footer survives repeated page cache requests and ESI-capable proxies", async ({
  request,
}) => {
  for (const path of ["/home", "/kontakt"]) {
    for (let i = 0; i < 2; ++i) {
      const response = await request.get(path, {
        headers: { "Surrogate-Capability": 'audit="ESI/1.0"' },
      });
      expect(response.ok()).toBeTruthy();
      expect(await response.text()).toContain('class="withdrawal-link" href="/withdrawal"');
      if (process.env.APP_ENV === "prod") {
        expect(response.headers()["cache-control"]).toContain("public");
        expect(response.headers()["cache-control"]).toContain("s-maxage=300");
      }
    }
  }
});
