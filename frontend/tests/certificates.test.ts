import { test } from "node:test";
import assert from "node:assert/strict";
import { checkCertificate, formatCertificateDate, normaliseCertificateCode } from "../lib/certificates.ts";

const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { "content-type": "application/json" } });

test("codes are normalised the way the API does", () => {
  assert.equal(normaliseCertificateCode(" pif k7m2-9qxa r4td "), "PIF-K7M2-9QXA-R4TD");
  assert.equal(normaliseCertificateCode("PIFK7M29QXAR4TD"), "PIF-K7M2-9QXA-R4TD");
  for (const bad of ["", "PIF-K7M2", "PIF-K7M2-9QXA-R4T0", "XYZ-K7M2-9QXA-R4TD", "PIF-K7M2-9QXA-R4TD/../x", "PIF-K7M2-9QXA-R4TD-EXTRA"]) {
    assert.equal(normaliseCertificateCode(bad), null, bad);
  }
});

test("a malformed code never reaches the API", async () => {
  let calls = 0;
  const result = await checkCertificate("https://api.paxofi.com/api/v1", "../../admin", async () => {
    calls++;
    return json(200, {});
  });
  assert.deepEqual(result, { kind: "not_found" });
  assert.equal(calls, 0);
});

test("a valid certificate shows only the printed facts", async () => {
  let url = "";
  const result = await checkCertificate("https://api.paxofi.com/api/v1/", "pif k7m2 9qxa r4td", async (input) => {
    url = String(input);
    return json(200, {
      data: { code: "PIF-K7M2-9QXA-R4TD", valid: true, status: "valid", holder_name: "Ada Lovelace", role_title: "Software Engineer Fellow", engagement: { type: "fellow", label: "Fellow" }, cohort: "Cohort 1", started_on: "2026-11-02", completed_on: "2027-04-20", issued_on: "2027-04-21", programme: "Paxofi Innovation Fellowship (PIF) 2026", message: "Genuine.", email: "ada@example.com" },
    });
  });
  assert.equal(url, "https://api.paxofi.com/api/v1/careers/certificates/PIF-K7M2-9QXA-R4TD");
  assert.equal(result.kind, "valid");
  if (result.kind !== "valid") return;
  assert.deepEqual([result.certificate.holderName, result.certificate.roleTitle, result.certificate.engagementLabel, result.certificate.cohort, result.certificate.startedOn, result.certificate.completedOn], ["Ada Lovelace", "Software Engineer Fellow", "Fellow", "Cohort 1", "2026-11-02", "2027-04-20"]);
  assert.equal(JSON.stringify(result).includes("ada@example.com"), false, "unknown fields are dropped");
});

test("revoked, unknown and unreachable are told apart", async () => {
  const revoked = await checkCertificate(undefined, "PIF-K7M2-9QXA-R4TD", async () => json(200, { data: { status: "revoked", valid: false, code: "PIF-K7M2-9QXA-R4TD", message: "Withdrawn." } }));
  assert.deepEqual(revoked, { kind: "revoked", code: "PIF-K7M2-9QXA-R4TD", message: "Withdrawn." });
  assert.deepEqual(await checkCertificate(undefined, "PIF-K7M2-9QXA-R4TD", async () => json(404, { success: false })), { kind: "not_found" });
  assert.deepEqual(await checkCertificate(undefined, "PIF-K7M2-9QXA-R4TD", async () => json(503, {})), { kind: "error" });
  assert.deepEqual(await checkCertificate(undefined, "PIF-K7M2-9QXA-R4TD", async () => { throw new Error("down"); }), { kind: "error" });
  assert.deepEqual(await checkCertificate(undefined, "PIF-K7M2-9QXA-R4TD", async () => json(200, { data: { status: "surprise" } })), { kind: "error" });
});

test("dates read as day month year", () => {
  assert.equal(formatCertificateDate("2027-04-20"), "20 April 2027");
  assert.equal(formatCertificateDate("2027-04-21 09:00:00"), "21 April 2027");
  assert.equal(formatCertificateDate("soon"), "soon");
});
