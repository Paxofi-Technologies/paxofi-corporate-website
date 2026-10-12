import { test } from "node:test";
import assert from "node:assert/strict";
import { ENGAGEMENTS, applyUrl, careersSiteUrl, checkCvFile, cvUploadUrl, describeApplicationFailure, groupRoles, hoursOptions, isCareersSite, loadRole, loadRoles, loadStatus, parseRole, sectionPath } from "../lib/careers.ts";

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

test("each engagement type has its own hours and term (Launch Decision Pack Revision 1)", () => {
  assert.deepEqual([ENGAGEMENTS.fellow.hoursMin, ENGAGEMENTS.intern.hoursMin, ENGAGEMENTS.volunteer.hoursMin], [15, 15, 10]);
  assert.deepEqual([ENGAGEMENTS.fellow.hoursTarget, ENGAGEMENTS.intern.hoursTarget, ENGAGEMENTS.volunteer.hoursTarget], [20, 20, 15]);
  assert.deepEqual([ENGAGEMENTS.fellow.term, ENGAGEMENTS.intern.term, ENGAGEMENTS.volunteer.term], ["24 programme weeks", "12 or 24 weeks", "8 to 24 weeks, project-based"]);
  assert.deepEqual(hoursOptions(15), [15, 20, 25, 30, 35, 40]);
  assert.deepEqual(hoursOptions(10), [10, 15, 20, 25, 30, 35, 40]);
});

test("a role carries its engagement type, defaulting to Fellow", () => {
  assert.equal(parseRole({ slug: "a", title: "A" })?.engagement.type, "fellow");
  const intern = parseRole({ slug: "a-intern", title: "A Intern", engagement_type: "intern", engagement: { type: "intern", label: "Intern", term: "12 or 24 weeks", hours_min: 15, hours_target: 20, for_whom: "Students." } });
  assert.deepEqual([intern?.engagement.type, intern?.engagement.term, intern?.engagement.hoursMin, intern?.engagement.forWhom], ["intern", "12 or 24 weeks", 15, "Students."]);
  assert.equal(parseRole({ slug: "a", title: "A", engagement_type: "nonsense" })?.engagement.type, "fellow", "unknown types fall back");
  assert.equal(parseRole({ slug: "v", title: "V", engagement_type: "volunteer" })?.engagement.hoursMin, 10, "Volunteers need at least 10 hours");
});

test("roles are grouped by role family, Fellow first", () => {
  const role = (slug: string, title: string, code: string, type: string) => parseRole({ slug, title, code, family: "F", summary: `${title} summary`, engagement_type: type })!;
  const groups = groupRoles([
    role("se-f", "Software Engineer Fellow", "SE", "fellow"),
    role("se-i", "Software Engineer Intern", "SE", "intern"),
    role("hr-f", "HR Officer Fellow", "HR", "fellow"),
    role("hr-v", "People Operations Volunteer", "HR", "volunteer"),
    role("cc-v", "Content & Communications Volunteer", "CC", "volunteer"),
  ]);
  assert.deepEqual(groups.map((g) => [g.code, g.name, g.roles.map((r) => r.engagement.label)]), [
    ["SE", "Software Engineer", ["Fellow", "Intern"]],
    ["HR", "HR Officer", ["Fellow", "Volunteer"]],
    ["CC", "Content & Communications", ["Volunteer"]],
  ]);
});

test("the opening status is read defensively", async () => {
  const ok = (body: unknown, status = 200) => (async () => new Response(JSON.stringify(body), { status })) as typeof fetch;
  const closed = await loadStatus("https://api.test/api/v1", ok({ data: { applications_open: false, opens_at: "2026-11-02T00:00:00+01:00", opens_on: "Monday 2 November 2026", cohort: "Cohort 1", cohort1_closes_on: "Sunday 15 November 2026" } }));
  assert.deepEqual(closed, { open: false, closed: false, opensOn: "Monday 2 November 2026", closesOn: null, cohort: "Cohort 1", cohort1ClosesOn: "Sunday 15 November 2026" });
  assert.deepEqual(await loadStatus("https://api.test/api/v1", ok({ data: { applications_open: true } })), { open: true, closed: false, opensOn: null, closesOn: null, cohort: null, cohort1ClosesOn: null });
  const ended = await loadStatus("https://api.test/api/v1", ok({ data: { applications_open: false, applications_closed: true, closes_on: "Friday 27 November 2026" } }));
  assert.deepEqual([ended?.open, ended?.closed, ended?.closesOn], [false, true, "Friday 27 November 2026"], "after the last day to apply (D-030)");
  assert.equal(await loadStatus("https://api.test/api/v1", ok({ data: { applications_open: "yes" } })), null);
  assert.equal(await loadStatus("https://api.test/api/v1", ok({}, 404)), null, "an older API without the endpoint counts as unknown, not closed");
  assert.equal(await loadStatus("https://api.test/api/v1", (async () => { throw new Error("down"); }) as typeof fetch), null);
});

test("a refusal before opening is shown with the API's own message", () => {
  assert.equal(describeApplicationFailure(403, { error: { code: "APPLICATIONS_NOT_OPEN", message: "Applications are not open yet. They open on Monday 2 November 2026." } }).message, "Applications are not open yet. They open on Monday 2 November 2026.");
  assert.match(describeApplicationFailure(403, null).message, /not open/);
});

test("any other refusal shows the API's message instead of a generic one", () => {
  assert.match(describeApplicationFailure(409, { error: { code: "CONFLICT", message: "Something specific went wrong." } }).message, /^Something specific went wrong\. If this keeps happening, email hr@paxofi\.com\.$/);
  assert.match(describeApplicationFailure(500, { error: { message: "Internal details" } }).message, /^We could not send your application/, "server errors stay generic");
  assert.match(describeApplicationFailure(0, null).message, /^We could not send your application/);
});
