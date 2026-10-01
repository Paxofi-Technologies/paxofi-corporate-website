import { test } from "node:test";
import assert from "node:assert/strict";
import { contactEndpoint, describeFailure } from "../lib/contact.ts";

test("appends the form path to a configured API base", () => {
  assert.equal(contactEndpoint("https://api.paxofi.com/api/v1"), "https://api.paxofi.com/api/v1/forms/contact/submit");
  assert.equal(contactEndpoint("https://api.paxofi.com/api/v1/"), "https://api.paxofi.com/api/v1/forms/contact/submit");
});

test("falls back to the same-origin API path", () => {
  assert.equal(contactEndpoint(undefined), "/api/v1/forms/contact/submit");
  assert.equal(contactEndpoint("  "), "/api/v1/forms/contact/submit");
});

test("does not double-append when given the full endpoint", () => {
  assert.equal(contactEndpoint("https://api.paxofi.com/api/v1/forms/contact/submit"), "https://api.paxofi.com/api/v1/forms/contact/submit");
});

test("maps validation errors to fields", () => {
  const failure = describeFailure(422, {
    success: false,
    error: { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { email: "Enter a valid email address.", unknown: "x" } } },
  });
  assert.deepEqual(failure.fields, { email: "Enter a valid email address." });
  assert.equal(failure.message, "Please correct the highlighted fields.");
});

test("explains rate limiting", () => {
  assert.match(describeFailure(429, null).message, /wait a few minutes/);
});

test("never shows raw server errors", () => {
  const failure = describeFailure(500, { error: { message: "SQLSTATE[HY000]" } });
  assert.doesNotMatch(failure.message, /SQLSTATE/);
  assert.deepEqual(failure.fields, {});
});
