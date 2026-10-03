// The staging copy (D-013): the same build, switched to staging mode by the
// Node app's settings. Every page needs the staging password, nothing is
// indexed, and a banner marks the site as a test copy.
import { describe, test } from "node:test";
import assert from "node:assert/strict";
import { assertAccessible, startHarness } from "./harness.mjs";

const PASSWORD = "staging pass phrase 42";
const { base: BASE, newPage } = startHarness(Number(process.env.E2E_PORT || 3123) + 4, {
  env: { SITE_ENVIRONMENT: "staging", STAGING_PASSWORD: PASSWORD },
});
const auth = { Authorization: "Basic " + Buffer.from(`paxofi:${PASSWORD}`).toString("base64") };

describe("staging site", () => {
  test("every page asks for the staging password", async () => {
    for (const path of ["/", "/products", "/admin/login", "/robots.txt", "/og-image.png"]) {
      const response = await fetch(BASE + path, { redirect: "manual" });
      assert.equal(response.status, 401, path);
      assert.match(response.headers.get("www-authenticate") ?? "", /^Basic realm="Paxofi staging"/, path);
      assert.equal(response.headers.get("x-robots-tag"), "noindex, nofollow", path);
      assert.match(response.headers.get("content-type") ?? "", /^text\/plain/, path);
    }
    const wrong = await fetch(BASE, { headers: { Authorization: "Basic " + Buffer.from("paxofi:guess").toString("base64") } });
    assert.equal(wrong.status, 401);
  });

  test("with the password it works, marked as staging and never indexed", async () => {
    const home = await fetch(BASE, { headers: auth });
    assert.equal(home.status, 200);
    assert.equal(home.headers.get("x-robots-tag"), "noindex, nofollow");
    assert.ok(home.headers.get("content-security-policy"), "the live site's protections still apply");

    const robots = await (await fetch(`${BASE}/robots.txt`, { headers: auth })).text();
    assert.match(robots, /Disallow: \/\s*$/m);
    assert.doesNotMatch(robots, /Sitemap:/);
    assert.doesNotMatch(await (await fetch(`${BASE}/sitemap.xml`, { headers: auth })).text(), /<loc>/);

    const page = await newPage({ httpCredentials: { username: "paxofi", password: PASSWORD } });
    await page.goto(`${BASE}/services`);
    await page.getByRole("note").getByText("Staging site for testing.").waitFor();
    assert.match(await page.locator('meta[name="robots"]').getAttribute("content"), /noindex/);
    await assertAccessible(page, "on the staging services page");
    await page.goto(`${BASE}/admin/login`);
    await page.getByText("Staging site for testing.").waitFor();
    await page.context().close();
  });
});
