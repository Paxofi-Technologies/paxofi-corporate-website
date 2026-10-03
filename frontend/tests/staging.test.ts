import { test } from "node:test";
import assert from "node:assert/strict";
import { hasStagingAccess, isStaging, stagingGate, stagingPassword } from "../lib/staging.ts";

const basic = (user: string, password: string) => "Basic " + Buffer.from(`${user}:${password}`).toString("base64");
const STAGING = { SITE_ENVIRONMENT: "staging", STAGING_PASSWORD: "correct horse battery" };

test("the live site is never gated", () => {
  assert.equal(isStaging({}), false);
  assert.equal(stagingGate(null, {}), null);
  assert.equal(stagingGate(null, { SITE_ENVIRONMENT: "production", STAGING_PASSWORD: "x" }), null);
});

test("staging asks for the password and lets it in (D-013)", () => {
  const refused = stagingGate(null, STAGING);
  assert.equal(refused?.status, 401);
  assert.match(refused!.headers["WWW-Authenticate"], /^Basic realm="Paxofi staging"/);
  assert.equal(refused!.headers["X-Robots-Tag"], "noindex, nofollow");
  assert.equal(stagingGate(basic("paxofi", "wrong password!!"), STAGING)?.status, 401);
  assert.equal(stagingGate(basic("anyone", "correct horse battery"), STAGING), null, "any user name");
  assert.equal(stagingGate(basic("paxofi", "correct horse battery"), { ...STAGING, SITE_ENVIRONMENT: " Staging " }), null);
});

test("without a usable password the staging site refuses to serve (fails closed)", () => {
  for (const password of [undefined, "", "short"]) {
    const gate = stagingGate(basic("a", password ?? ""), { SITE_ENVIRONMENT: "staging", STAGING_PASSWORD: password });
    assert.equal(gate?.status, 503, String(password));
  }
  assert.equal(stagingPassword({ STAGING_PASSWORD: "  twelve chars  " }), "twelve chars");
});

test("malformed or partial credentials are refused", () => {
  for (const header of ["", "Bearer abc", "Basic !!!", "Basic " + Buffer.from("no-colon").toString("base64"), basic("u", "correct horse batter"), basic("u", "correct horse battery ")]) {
    assert.equal(hasStagingAccess(header, "correct horse battery"), false, header);
  }
  assert.equal(hasStagingAccess(basic("u", "pässwörd-ünïcode"), "pässwörd-ünïcode"), true, "UTF-8 passwords");
  assert.equal(hasStagingAccess(basic("u", "a:b:c-colon-pass"), "b:c-colon-pass") || hasStagingAccess(basic("u", "a:b:c-colon-pass"), "a:b:c-colon-pass"), true);
});
