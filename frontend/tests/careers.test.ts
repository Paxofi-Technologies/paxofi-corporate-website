import { test } from "node:test";
import assert from "node:assert/strict";
import { applyUrl, careersSiteUrl, checkCvFile, cvUploadUrl, describeApplicationFailure, isCareersSite, loadRole, loadRoles, parseRole, sectionPath } from "../lib/careers.ts";

test("the careers site is switched on by SITE_SECTION", () => {
  assert.equal(isCareersSite({ SITE_SECTION: "careers" }), true);
  assert.equal(isCareersSite({ SITE_SECTION: " Careers " }), true);
  assert.equal(isCareersSite({}), false);
  assert.equal(careersSiteUrl({}), "https://careers.paxofi.com");
  assert.equal(careersSiteUrl({ CAREERS_SITE_URL: "https://staging-careers.paxofi.com/" }), "https://staging-careers.paxofi.com");
});

test("careers paths are served from the internal folder, and hidden on the corporate site", () => {
  assert.equal(sectionPath("/", true), "/careers-site");
  assert.equal(sectionPath("/roles/software-engineer-fellow", true), "/careers-site/roles/software-engineer-fellow");
  assert.equal(sectionPath("/privacy/", true), "/careers-site/privacy");
  assert.equal(sectionPath("/robots.txt", true), null);
  assert.equal(sectionPath("/og-image.png", true), null);
  assert.equal(sectionPath("/_next/data/x", true), null);
  assert.equal(sectionPath("/admin/login", true), "not-found", "no staff area on the careers address");
  assert.equal(sectionPath("/careers-site", true), "not-found");
  assert.equal(sectionPath("/careers-site/privacy", false), "not-found");
  assert.equal(sectionPath("/careers", false), null);
  assert.equal(sectionPath("/", false), null);
});

test("API addresses", () => {
  assert.equal(cvUploadUrl("https://api.paxofi.com/api/v1/", "My CV.pdf"), "https://api.paxofi.com/api/v1/careers/cv?filename=My+CV.pdf");
  assert.equal(applyUrl(undefined, "hr-officer-fellow"), "/api/v1/careers/roles/hr-officer-fellow/apply");
});

test("roles are read defensively", async () => {
  assert.equal(parseRole({ title: "x" }), null);
  const role = parseRole({ slug: "a", title: "A", code: "AA", responsibilities: ["x", 3], tools: "nope" });
  assert.deepEqual([role?.responsibilities, role?.tools, role?.evidence], [["x"], [], ""]);

  const ok = (body: unknown, status = 200) => (async () => new Response(JSON.stringify(body), { status })) as typeof fetch;
  assert.equal((await loadRoles("https://api.test/api/v1", ok({ data: [{ slug: "a", title: "A" }, { bad: 1 }] })))?.length, 1);
  assert.equal(await loadRoles("https://api.test/api/v1", ok({}, 500)), null);
  assert.equal(await loadRoles("https://api.test/api/v1", (async () => { throw new Error("down"); }) as typeof fetch), null);
  assert.equal(await loadRole("https://api.test/api/v1", "a", ok({ error: {} }, 404)), "missing");
  assert.equal(await loadRole("https://api.test/api/v1", "../etc", ok({})), "missing", "only role addresses are requested");
  assert.equal((await loadRole("https://api.test/api/v1", "a", ok({ data: { slug: "a", title: "A" } })) as { title: string }).title, "A");
});

test("application errors and CV checks", () => {
  const failure = describeApplicationFailure(422, { error: { message: "Please correct the highlighted fields.", details: { fields: { email: "Enter a valid email address.", cv: "Upload again." } } } });
  assert.deepEqual(failure.fields, { email: "Enter a valid email address.", cv: "Upload again." });
  assert.match(describeApplicationFailure(429, null).message, /wait an hour/);
  assert.match(describeApplicationFailure(500, null).message, /hr@paxofi.com/);
  assert.equal(checkCvFile("cv.pdf", 1000), null);
  assert.equal(checkCvFile("cv.DOCX", 1000), null);
  assert.match(checkCvFile("cv.doc", 1000) ?? "", /PDF or Word/);
  assert.match(checkCvFile("cv.pdf", 6 * 1024 * 1024) ?? "", /5 MB/);
});

test("campaign tags are read from links, and anything unsafe is dropped", async () => {
  const { campaignFrom } = await import("../lib/careers.ts");
  assert.deepEqual(campaignFrom("?utm_source=LinkedIn&utm_medium=social&utm_campaign=pif-2026&ref=x"), { utm_source: "linkedin", utm_medium: "social", utm_campaign: "pif-2026" });
  assert.deepEqual(campaignFrom("?utm_campaign=%3Cscript%3E&utm_source=whatsapp"), { utm_source: "whatsapp" });
  assert.equal(campaignFrom(""), null);
});
