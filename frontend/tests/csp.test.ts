import { test } from "node:test";
import assert from "node:assert/strict";
import { apiOrigin, contentSecurityPolicy } from "../lib/csp.ts";

test("connect-src allows only this site and the configured API origin", () => {
  const policy = contentSecurityPolicy("https://api.paxofi.com/api/v1");
  assert.match(policy, /connect-src 'self' https:\/\/api\.paxofi\.com;/);
  assert.doesNotMatch(policy, /https:(?!\/\/)/, "no scheme-wide wildcard");
});

test("without an absolute API base, connect-src is same-origin only", () => {
  for (const base of [undefined, "", "/api/v1", "not a url", "javascript:alert(1)"]) {
    assert.match(contentSecurityPolicy(base), /connect-src 'self';/, String(base));
  }
});

test("apiOrigin strips path, query and credentials", () => {
  assert.equal(apiOrigin("https://user:pw@api.paxofi.com:8443/api/v1?x=1"), "https://api.paxofi.com:8443");
});

test("framing, plugins and base-uri stay locked down", () => {
  const policy = contentSecurityPolicy("https://api.paxofi.com/api/v1");
  for (const directive of ["frame-ancestors 'none'", "object-src 'none'", "base-uri 'self'", "default-src 'self'", "form-action 'self'"]) {
    assert.ok(policy.includes(directive), directive);
  }
});
