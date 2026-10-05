// The staff Recruitment section (D-019) for an HR staff member, and the roles
// editor (D-018). The admin API is faked inside the browser, as in admin.e2e.mjs.
import { describe, test } from "node:test";
import assert from "node:assert/strict";
import { API_BASE, assertAccessible, startHarness } from "./harness.mjs";

const { base: BASE, newPage } = startHarness(Number(process.env.E2E_PORT || 3123) + 8);
const APP_ID = "aaaaaaaa-0000-4000-8000-000000000001";

const HR = {
  id: "33333333-3333-4333-8333-333333333333", email: "hr@paxofi.com", display_name: "Hana HR", status: "active",
  role: "human_resources", role_label: "Human Resources", permissions: ["careers.edit", "recruitment.manage", "recruitment.read"], last_login_at: null,
};
const STAGES = [["applied", "Applied", false], ["screening", "Screening", false], ["shortlisted", "Shortlisted", false], ["assessment", "Assessment", false], ["interview", "Interview", false], ["rejected", "Not selected", true]]
  .map(([value, label, closed]) => ({ value, label, closed }));
const CRITERIA = { capability: "Role capability", evidence: "Evidence or portfolio", communication: "Communication", reliability: "Reliability and ownership", learning: "Learning agility", collaboration: "Collaboration", readiness: "Role-specific readiness" };
const INTERVIEW = { motivation: "Motivation and programme fit", competence: "Role competence", problem_solving: "Problem solving", communication: "Communication", collaboration: "Collaboration", accountability: "Accountability", learning: "Learning orientation" };

async function fakeApi(page) {
  const state = {
    calls: [],
    app: {
      id: APP_ID, reference: "PIF-7KQ3MZ", role: { id: "r1", title: "Software Engineer Fellow", slug: "software-engineer-fellow" },
      full_name: "Ada Lovelace", email: "ada@example.com", phone: null, location: "Lagos, Nigeria", hours_per_week: 20,
      portfolio_url: "https://github.com/ada", linkedin_url: null, motivation: "I want to build real products.", experience: "Payments dashboard in React.",
      source: { value: "linkedin", label: "LinkedIn" }, campaign: { source: "linkedin", medium: "social", campaign: "pif-2026" },
      cv: { filename: "Ada CV.pdf", size_bytes: 20480, media_type: "application/pdf" }, stage: "applied", stage_label: "Applied",
      stage_changed_at: "2026-10-04 08:00:00", closed_at: null, created_at: "2026-10-04 08:00:00", evidence: null, interview: null, notes: [],
      stages: STAGES, scorecards: { evidence: { label: "Gate 2: evidence review", pass: 21, criteria: CRITERIA }, interview: { label: "Gate 4: interview", pass: 24, criteria: INTERVIEW } },
      email_templates: [{ key: "selection", label: "Selected (with PIF Participant Agreement)", subject: "You have been selected: Software Engineer Fellow (PIF-7KQ3MZ)", body: "Hello Ada,\n\nAttached is the PIF Participant Agreement.\n\nPaxofi", stage: "agreement_pending" }, { key: "assessment_invitation", label: "Assessment invitation", subject: "Your role assessment: Software Engineer Fellow (PIF-7KQ3MZ)", body: "Hello Ada,\n\nDeadline: [date and time, with time zone]\n\nPaxofi", stage: "assessment" }],
      email_available: true,
    },
    roles: [{ id: "r1", slug: "software-engineer-fellow", code: "SE", title: "Software Engineer Fellow", family: "Technology and engineering", summary: "Build software.", purpose: "Build and test Paxofi software.", responsibilities: ["Write code"], deliverables: [], competencies: [], tools: [], evidence: "", assessment: "", interview: "", state: "published", sort_order: 40, published_at: "2026-10-04 08:00:00", updated_at: "2026-10-04 08:00:00" }],
  };
  const cors = { "Access-Control-Allow-Origin": BASE, "Access-Control-Allow-Credentials": "true", "Access-Control-Allow-Methods": "GET, POST, PATCH, DELETE, OPTIONS", "Access-Control-Allow-Headers": "Content-Type, Accept", "Access-Control-Expose-Headers": "Content-Disposition", Vary: "Origin" };
  const reply = (route, status, body, meta = {}) => route.fulfill({ status, headers: cors, contentType: "application/json", body: JSON.stringify(status < 400 ? { success: true, data: body, meta } : { success: false, error: body }) });
  const note = (kind, body) => state.app.notes.unshift({ id: String(state.app.notes.length + 1), kind, body, author_name: "Hana HR", created_at: "2026-10-04 09:00:00" });

  await page.route(`${API_BASE}/admin/**`, async (route) => {
    const request = route.request();
    const method = request.method();
    if (method === "OPTIONS") return route.fulfill({ status: 204, headers: cors });
    const path = new URL(request.url()).pathname.replace("/api/v1/admin", "");
    const body = request.postData() ? request.postDataJSON() : null;
    state.calls.push({ method, path, body });
    const app = state.app;

    if (path === "/session") return reply(route, 200, { ...HR, two_factor_enabled: true });
    if (path === "/applications" && method === "GET") {
      return reply(route, 200, [{ id: APP_ID, reference: app.reference, full_name: app.full_name, email: app.email, role_title: app.role.title, stage: app.stage, stage_label: app.stage_label, created_at: app.created_at, evidence_total: app.evidence?.total ?? null, interview_total: null, has_cv: true }],
        { page: 1, per_page: 25, total: 1, total_pages: 1, counts: { [app.stage]: 1 }, stages: STAGES, roles: [{ id: "r1", title: app.role.title }] });
    }
    if (path === "/applications/report") {
      return reply(route, 200, {
        days: 30, total: 3,
        by_role: [{ key: "Software Engineer Fellow", label: "Software Engineer Fellow", count: 3 }],
        by_stage: [{ key: "applied", label: "Applied", count: 2 }, { key: "screening", label: "Screening", count: 1 }],
        by_source: [{ key: "linkedin", label: "LinkedIn", count: 2 }, { key: null, label: "Not given", count: 1 }],
        by_campaign: [{ key: "linkedin / social / pif-2026", label: "linkedin / social / pif-2026", count: 2 }, { key: null, label: "No campaign link", count: 1 }],
        by_day: [{ day: "2026-10-04", count: 1 }, { day: "2026-10-05", count: 2 }],
        review: { target_working_days: 2, reviewed: 1, on_time: 1, waiting: 2, overdue: 0, median_hours: 3.5 },
      });
    }
    if (path === `/applications/${APP_ID}` && method === "GET") return reply(route, 200, app);
    if (path === `/applications/${APP_ID}` && method === "PATCH") {
      const to = STAGES.find((s) => s.value === body.stage);
      note("stage", `Stage changed from ${app.stage_label} to ${to.label}.`);
      Object.assign(app, { stage: to.value, stage_label: to.label });
      return reply(route, 200, app);
    }
    if (path === `/applications/${APP_ID}/scores`) {
      const total = Object.values(body.scores).reduce((a, b) => a + b, 0);
      app[body.gate] = { scores: body.scores, total, out_of: 35, pass: 21, passed: total >= 21 };
      note("score", `Gate 2: evidence review scored ${total}/35.`);
      return reply(route, 200, app);
    }
    if (path === `/applications/${APP_ID}/emails`) {
      if (body.template === "selection" && !body.attachment) return reply(route, 422, { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { attachment: "Attach the PIF Participant Agreement (PDF or Word) before sending." } } });
      if (/\[[^\]]{2,60}\]/.test(body.body)) return reply(route, 422, { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { body: "Replace the [placeholders] in square brackets before sending." } } });
      note("email", `Email sent: ${body.subject}${body.attachment ? `\nAttached: ${body.attachment.filename}` : ""}`);
      return reply(route, 200, app);
    }
    if (path === `/applications/${APP_ID}/cv`) {
      return route.fulfill({ status: 200, headers: { ...cors, "Content-Disposition": 'attachment; filename="CV-7KQ3MZ.pdf"', "Cache-Control": "no-store" }, contentType: "application/pdf", body: "%PDF-1.7\n%%EOF\n" });
    }
    if (path === "/career-roles" && method === "GET") return reply(route, 200, state.roles);
    if (path === "/career-roles" && method === "POST") {
      state.roles.push({ ...body, id: "r2", slug: "data-analyst-fellow", state: "draft", responsibilities: body.responsibilities.split("\n"), deliverables: [], competencies: [], tools: [], published_at: null, updated_at: "2026-10-04 09:00:00" });
      return reply(route, 201, state.roles.at(-1));
    }
    const stateChange = path.match(/^\/career-roles\/(r\d)\/state$/);
    if (stateChange) {
      const role = state.roles.find((r) => r.id === stateChange[1]);
      role.state = body.state;
      return reply(route, 200, role);
    }
    return reply(route, 404, { code: "NOT_FOUND", message: "Not found." });
  });
  return state;
}

describe("recruitment", () => {
  test("HR staff land on Recruitment and see only their sections", async () => {
    const page = await newPage();
    await fakeApi(page);
    await page.goto(`${BASE}/admin/enquiries`);
    await page.waitForURL(/\/admin\/recruitment$/);
    await page.getByRole("link", { name: "Ada Lovelace" }).waitFor();
    const nav = await page.getByRole("navigation", { name: "Staff area" }).getByRole("link").allTextContents();
    assert.deepEqual(nav, ["Recruitment", "My account"]);
    await assertAccessible(page, "applications list");
    await page.context().close();
  });

  test("an application is moved, scored, emailed and its CV downloaded", async () => {
    const page = await newPage({ acceptDownloads: true });
    const state = await fakeApi(page);
    await page.goto(`${BASE}/admin/recruitment/${APP_ID}`);
    await page.getByRole("heading", { name: "Ada Lovelace" }).waitFor();
    await assertAccessible(page, "application detail");

    await page.getByLabel("Move to stage").selectOption("screening");
    await page.getByRole("button", { name: "Save stage" }).click();
    await page.getByText("Moved to “Screening”.").waitFor();

    const gate = page.locator("section", { has: page.getByRole("heading", { name: "Gate 2: evidence review" }) });
    for (const label of Object.values(CRITERIA)) await gate.getByLabel(label, { exact: true }).selectOption("4");
    await gate.getByRole("button", { name: "Save scorecard" }).click();
    await page.getByText("Current: 28/35, meets the mark").waitFor();

    await page.getByLabel("Template").selectOption("assessment_invitation");
    assert.match(await page.getByLabel("Subject").inputValue(), /PIF-7KQ3MZ/);
    await page.getByRole("button", { name: "Send email" }).click();
    await page.getByText("Replace the [placeholders] in square brackets before sending.").waitFor();
    await page.getByLabel("Message").fill("Hello Ada,\n\nDeadline: 10 October, 17:00 WAT\n\nPaxofi");
    await page.getByRole("button", { name: "Send email" }).click();
    await page.getByText("Email sent to ada@example.com.").waitFor();
    assert.ok(await page.getByText("Email sent: Your role assessment").isVisible(), "recorded in the history");

    await page.getByLabel("Template").selectOption("selection");
    await page.getByRole("button", { name: "Send email" }).click();
    await page.getByText("Attach the PIF Participant Agreement (PDF or Word) before sending.").waitFor();
    await page.getByLabel(/Attachment: the PIF Participant Agreement/).setInputFiles({ name: "PIF Agreement.pdf", mimeType: "application/pdf", buffer: Buffer.from("%PDF-1.7\n%%EOF\n") });
    await page.getByRole("button", { name: "Send email" }).click();
    await page.getByText("Email sent to ada@example.com with PIF Agreement.pdf.").waitFor();
    const withFile = state.calls.filter((c) => c.path.endsWith("/emails")).at(-1).body.attachment;
    assert.deepEqual(withFile, { filename: "PIF Agreement.pdf", content_base64: Buffer.from("%PDF-1.7\n%%EOF\n").toString("base64") });

    const [download] = await Promise.all([page.waitForEvent("download"), page.getByRole("button", { name: /Download Ada CV\.pdf/ }).click()]);
    assert.equal(download.suggestedFilename(), "CV-7KQ3MZ.pdf");
    assert.deepEqual(state.calls.filter((c) => c.method !== "GET").map((c) => c.path), [`/applications/${APP_ID}`, `/applications/${APP_ID}/scores`, `/applications/${APP_ID}/emails`, `/applications/${APP_ID}/emails`, `/applications/${APP_ID}/emails`, `/applications/${APP_ID}/emails`]);
    await page.context().close();
  });

  test("the report shows channels, campaigns and review times", async () => {
    const page = await newPage();
    await fakeApi(page);
    await page.goto(`${BASE}/admin/recruitment`);
    await page.getByRole("link", { name: "See the recruitment report" }).click();
    await page.getByRole("heading", { name: "Recruitment report" }).waitFor();
    await page.getByText("100%").waitFor();
    assert.ok(await page.getByRole("region", { name: "By channel" }).getByText("LinkedIn").isVisible());
    assert.ok(await page.getByRole("region", { name: "By campaign link" }).getByText("linkedin / social / pif-2026").isVisible());
    await assertAccessible(page, "recruitment report");
    await page.context().close();
  });

  test("roles are drafted and opened in the roles editor", async () => {
    const page = await newPage();
    const state = await fakeApi(page);
    await page.goto(`${BASE}/admin/recruitment/roles`);
    await page.getByText("Software Engineer Fellow").waitFor();
    await assertAccessible(page, "roles editor");
    await page.getByRole("button", { name: "New role" }).click();
    await page.getByLabel("Title").fill("Data Analyst Fellow");
    await page.getByLabel("Code").fill("DA");
    await page.getByLabel("Role family").fill("Data");
    await page.getByLabel("Summary (on the roles list)").fill("Turn data into decisions.");
    await page.getByLabel("Purpose (top of the role page)").fill("Builds dashboards and analysis.");
    await page.getByLabel("What they will do").fill("Build dashboards\nAnalyse funnels");
    await page.getByRole("button", { name: "Create draft" }).click();
    await page.getByText("“Data Analyst Fellow” was created as a draft.").waitFor();
    await page.getByRole("row", { name: /Data Analyst Fellow/ }).getByRole("button", { name: "Open for applications" }).click();
    await page.getByText("is now open for applications.").waitFor();
    assert.equal(state.roles[1].state, "published");
    await page.context().close();
  });
});
