import { test } from "node:test";
import assert from "node:assert/strict";
import { canonicalRedirect, redirectHosts } from "../lib/canonical-host.ts";

const live = { NEXT_PUBLIC_SITE_URL: "https://paxofi.com", REDIRECT_HOSTS: "www.paxofi.com,corporate.paxofi.com" };

test("www and the old corporate address redirect to paxofi.com, keeping path and query", () => {
  assert.deepEqual(redirectHosts(live), ["www.paxofi.com", "corporate.paxofi.com"]);
  assert.equal(canonicalRedirect("corporate.paxofi.com", "/products", "?a=1", live), "https://paxofi.com/products?a=1");
  assert.equal(canonicalRedirect("WWW.Paxofi.com:443", "/", "", live), "https://paxofi.com/");
  assert.equal(canonicalRedirect("corporate.paxofi.com", "/admin/enquiries", "", live), "https://paxofi.com/admin/enquiries");
});

test("the canonical host, careers, staging and unknown hosts are served as they are", () => {
  for (const host of ["paxofi.com", "careers.paxofi.com", "staging.corporate.paxofi.com", "localhost:3000", "", null]) {
    assert.equal(canonicalRedirect(host, "/", "", live), null, String(host));
  }
});

test("by default only the www name redirects, so a deploy never moves corporate.paxofi.com by surprise", () => {
  assert.deepEqual(redirectHosts({ NEXT_PUBLIC_SITE_URL: "https://paxofi.com" }), ["www.paxofi.com"]);
  assert.deepEqual(redirectHosts({}), ["www.paxofi.com"]);
  assert.equal(canonicalRedirect("corporate.paxofi.com", "/", "", {}), null);
  assert.deepEqual(redirectHosts({ NEXT_PUBLIC_SITE_URL: "https://corporate.paxofi.com" }), ["www.corporate.paxofi.com"]);
});

test("REDIRECT_HOSTS replaces the defaults and never lists the canonical host", () => {
  const env = { ...live, REDIRECT_HOSTS: " old.example.com , paxofi.com " };
  assert.deepEqual(redirectHosts(env), ["old.example.com"]);
  assert.equal(canonicalRedirect("www.paxofi.com", "/", "", env), null);
});
