import { test } from "node:test";
import assert from "node:assert/strict";
import { AdminApiError, adminRequest, adminUrl, safeNextPath, statusLabel, toError } from "../lib/admin-api.ts";

test("builds admin URLs from the configured API base", () => {
  assert.equal(adminUrl("https://api.paxofi.com/api/v1/", "/session"), "https://api.paxofi.com/api/v1/admin/session");
  assert.equal(adminUrl(undefined, "/session"), "/api/v1/admin/session");
});

test("sends the session cookie and JSON, and unwraps the envelope", async () => {
  let seen: RequestInit | undefined;
  const fake = (async (_url: string, init: RequestInit) => {
    seen = init;
    return new Response(JSON.stringify({ success: true, data: { ok: 1 }, meta: { page: 1 } }), { status: 200 });
  }) as unknown as typeof fetch;

  const result = await adminRequest<{ ok: number }>("https://api.example/api/v1", "/enquiries/1", { method: "PATCH", body: { status: "closed" } }, fake);

  assert.equal(seen?.credentials, "include");
  assert.equal(seen?.body, '{"status":"closed"}');
  assert.deepEqual(result, { data: { ok: 1 }, meta: { page: 1 } });
});

test("maps API errors to messages and field errors", () => {
  const error = toError(422, { error: { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { email: "Enter a valid email address.", x: 1 } } } });
  assert.equal(error.code, "VALIDATION_ERROR");
  assert.deepEqual(error.fields, { email: "Enter a valid email address." });
  assert.match(toError(429, null).message, /15 minutes/);
  assert.doesNotMatch(toError(500, { error: { message: "SQLSTATE secret" } }).message, /SQLSTATE/);
});

test("network failures become a friendly error", async () => {
  const failing = (async () => {
    throw new TypeError("Failed to fetch");
  }) as unknown as typeof fetch;
  await assert.rejects(adminRequest(undefined, "/session", {}, failing), (e: unknown) => e instanceof AdminApiError && e.status === 0);
});

test("only admin paths are accepted as the next page after sign-in", () => {
  assert.equal(safeNextPath("/admin/users"), "/admin/users");
  assert.equal(safeNextPath("https://evil.example"), "/admin/enquiries");
  assert.equal(safeNextPath("//evil.example/admin"), "/admin/enquiries");
  assert.equal(safeNextPath("/admin/../x"), "/admin/enquiries");
  assert.equal(safeNextPath(null), "/admin/enquiries");
});

test("labels enquiry statuses", () => {
  assert.equal(statusLabel("in_progress"), "In progress");
  assert.equal(statusLabel("weird"), "weird");
});
