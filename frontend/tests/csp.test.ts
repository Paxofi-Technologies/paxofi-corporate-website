import { test } from "node:test";
import assert from "node:assert/strict";
import { apiOrigin, contentSecurityPolicy, createNonce } from "../lib/csp.ts";

test("connect-src allows only this site and the configured API origin", () => {
  const policy = contentSecurityPolicy("abc", "https://api.paxofi.com/api/v1");
  assert.match(policy, /connect-src 'self' https:\/\/api\.paxofi\.com;/);
  assert.doesNotMatch(policy, /https:(?!\/\/)/, "no scheme-wide wildcard");
});

test("without an absolute API base, connect-src is same-origin only", () => {
  for (const base of [undefined, "", "/api/v1", "not a url", "javascript:alert(1)"]) {
    assert.match(contentSecurityPolicy("abc", base), /connect-src 'self';/, String(base));
  }
});

test("scripts need the nonce; no inline scripts or styles are allowed", () => {
  const policy = contentSecurityPolicy("n0nce", "https://api.paxofi.com/api/v1");
  assert.match(policy, /script-src 'self' 'nonce-n0nce' 'strict-dynamic';/);
  assert.doesNotMatch(policy, /unsafe-inline|unsafe-eval/);
});

test("apiOrigin strips path, query and credentials", () => {
  assert.equal(apiOrigin("https://user:pw@api.paxofi.com:8443/api/v1?x=1"), "https://api.paxofi.com:8443");
});

test("framing, plugins and base-uri stay locked down", () => {
  const policy = contentSecurityPolicy("abc", "https://api.paxofi.com/api/v1");
  for (const directive of ["frame-ancestors 'none'", "object-src 'none'", "base-uri 'self'", "default-src 'self'", "form-action 'self'"]) {
    assert.ok(policy.includes(directive), directive);
  }
});

test("nonces are 128-bit base64 values and differ every time", () => {
  const a = createNonce();
  assert.match(a, /^[A-Za-z0-9+/]{22}==$/);
  assert.notEqual(a, createNonce());
});
