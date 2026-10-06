import { test } from "node:test";
import assert from "node:assert/strict";
import { loadRedirects, normalizePath, parseRedirects, redirectTarget, resetRedirectCache } from "../lib/redirects.ts";

const API = "https://api.paxofi.com/api/v1";

test("addresses match whatever their case or trailing slash (D-025)", () => {
  const map = parseRedirects({
    data: [
      { from: "/insights/old-article", to: "/insights/new-article" },
      { from: "/brochure", to: "https://paxofi.com/brochure.pdf" },
      { from: "/old-news", to: "/insights?category=news#latest" },
      { from: "/to-anchor", to: "/products#paxoficloud" },
      { from: "/evil", to: "//evil.example" },
      { from: "/script", to: "javascript:alert(1)" },
      { from: "relative", to: "/" },
      { nope: true },
    ],
  });
  assert.deepEqual([...map.keys()], ["/insights/old-article", "/brochure", "/old-news", "/to-anchor"], "unsafe entries are dropped");
  assert.equal(normalizePath("/Insights/Old-Article/"), "/insights/old-article");
  assert.equal(normalizePath("/"), "/");
  assert.equal(redirectTarget(map, "/Insights/Old-Article/", ""), "/insights/new-article");
  assert.equal(redirectTarget(map, "/insights/old-article", "?utm_source=linkedin"), "/insights/new-article?utm_source=linkedin", "campaign tags survive");
  assert.equal(redirectTarget(map, "/to-anchor", "?a=1"), "/products?a=1#paxoficloud");
  assert.equal(redirectTarget(map, "/old-news", "?a=1"), "/insights?category=news#latest", "a target with its own query keeps it");
  assert.equal(redirectTarget(map, "/brochure", "?a=1"), "https://paxofi.com/brochure.pdf");
  assert.equal(redirectTarget(map, "/about", ""), null);
  assert.equal(parseRedirects(null).size, 0);
});

test("the list is read at most once a minute and kept when the API is down", async () => {
  resetRedirectCache();
  let calls = 0;
  let up = true;
  const fetchImpl = (async (url: string) => {
    calls++;
    assert.equal(url, `${API}/redirects`);
    if (!up) throw new Error("down");
    return new Response(JSON.stringify({ data: [{ from: "/old", to: "/new" }] }), { status: 200 });
  }) as unknown as typeof fetch;
  const silenced = console.error;
  console.error = () => {};
  try {
    const first = await loadRedirects(API, 1_000, fetchImpl);
    assert.equal(first.get("/old"), "/new");
    await loadRedirects(API, 30_000, fetchImpl);
    assert.equal(calls, 1, "cached for a minute");
    up = false;
    const kept = await loadRedirects(API, 62_000, fetchImpl);
    assert.equal(calls, 2);
    assert.equal(kept.get("/old"), "/new", "the last list is kept while the API is down");
    resetRedirectCache();
    assert.equal((await loadRedirects(API, 0, fetchImpl)).size, 0, "no list yet and API down: no redirects");
  } finally {
    console.error = silenced;
    resetRedirectCache();
  }
});
