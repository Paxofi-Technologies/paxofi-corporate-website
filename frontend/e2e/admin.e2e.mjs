// End-to-end and accessibility checks for the staff area (/admin, D-009).
// The admin API is faked inside the browser, including CORS with credentials,
// so no backend is needed. Run after `npm run build`: npm run test:e2e
import { describe, test } from "node:test";
import assert from "node:assert/strict";
import { API_BASE, assertAccessible, startHarness } from "./harness.mjs";

const { base: BASE, newPage } = startHarness(Number(process.env.E2E_PORT || 3123) + 1);
const PASSWORD = "correct horse battery staple";

const ADMIN = {
  id: "11111111-1111-4111-8111-111111111111",
  email: "ada@paxofi.com",
  display_name: "Ada Admin",
  status: "active",
  role: "administrator",
  role_label: "Administrator",
  permissions: ["enquiries.read", "enquiries.update", "users.manage", "audit.read"],
  last_login_at: "2026-10-02 09:00:00",
};
const BD = {
  ...ADMIN,
  id: "22222222-2222-4222-8222-222222222222",
  email: "ben@paxofi.com",
  display_name: "Ben Business",
  role: "business_development",
  role_label: "Business Development",
  permissions: ["enquiries.read", "enquiries.update"],
};

function enquiries() {
  return [
    { id: "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa", name: "Grace Hopper", email: "grace@example.com", company: "Navy Labs", message: "We need a payments partner.\nCan we talk next week?", status: "new", source_ip: "203.0.113.7", user_agent: "Mozilla/5.0", request_id: "req-123", created_at: "2026-10-03 08:15:00" },
    { id: "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb", name: "Alan Turing", email: "alan@example.com", company: null, message: "Question about Paxofi Core.", status: "replied", source_ip: null, user_agent: null, request_id: "req-456", created_at: "2026-10-01 14:00:00" },
  ];
}

/** A stateful fake of /api/v1/admin/* answering inside the browser. */
async function fakeAdminApi(page, { user = ADMIN, signedIn = false, setupAvailable = false } = {}) {
  const state = { signedIn, user, rows: enquiries(), calls: [] };
  const cors = {
    "Access-Control-Allow-Origin": BASE,
    "Access-Control-Allow-Credentials": "true",
    "Access-Control-Allow-Methods": "GET, POST, PATCH, DELETE, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type, Accept",
    Vary: "Origin",
  };
  const reply = (route, status, body, meta = {}) =>
    route.fulfill({
      status,
      headers: cors,
      contentType: "application/json",
      body: JSON.stringify(status < 400 ? { success: true, data: body, meta } : { success: false, error: body }),
    });
  const unauthenticated = { code: "UNAUTHENTICATED", message: "Please sign in." };

  await page.route(`${API_BASE}/admin/**`, async (route) => {
    const request = route.request();
    const method = request.method();
    if (method === "OPTIONS") return route.fulfill({ status: 204, headers: cors });
    const url = new URL(request.url());
    const path = url.pathname.replace("/api/v1/admin", "");
    const body = request.postData() ? request.postDataJSON() : null;
    state.calls.push({ method, path, body });

    if (path === "/setup" && method === "GET") return reply(route, 200, { available: setupAvailable });
    if (path === "/setup" && method === "POST") {
      if (body.setup_token !== "s".repeat(32)) return reply(route, 403, { code: "FORBIDDEN", message: "The setup code is incorrect." });
      state.signedIn = true;
      return reply(route, 201, state.user);
    }
    if (path === "/session" && method === "POST") {
      if (body.email !== state.user.email || body.password !== PASSWORD) {
        return reply(route, 401, { code: "UNAUTHENTICATED", message: "The email or password is incorrect." });
      }
      state.signedIn = true;
      return reply(route, 200, state.user);
    }
    if (!state.signedIn) return reply(route, 401, unauthenticated);

    if (path === "/session" && method === "GET") return reply(route, 200, state.user);
    if (path === "/session" && method === "DELETE") {
      state.signedIn = false;
      return reply(route, 200, { signed_out: true });
    }
    if (path === "/session/password") {
      if (body.current_password !== PASSWORD) {
        return reply(route, 422, { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { current_password: "Your current password is incorrect." } } });
      }
      return reply(route, 200, { changed: true });
    }
    if (path === "/enquiries" && method === "GET") {
      const status = url.searchParams.get("status");
      const items = state.rows.filter((row) => !status || row.status === status).map(({ message, ...row }) => ({ ...row, excerpt: message.slice(0, 160) }));
      const counts = {};
      for (const row of state.rows) counts[row.status] = (counts[row.status] ?? 0) + 1;
      return reply(route, 200, items, { page: 1, per_page: 25, total: items.length, total_pages: 1, counts });
    }
    const match = path.match(/^\/enquiries\/([0-9a-f-]+)$/);
    if (match) {
      const row = state.rows.find((r) => r.id === match[1]);
      if (!row) return reply(route, 404, { code: "NOT_FOUND", message: "Enquiry not found." });
      if (method === "PATCH") row.status = body.status;
      return reply(route, 200, row);
    }
    if (!state.user.permissions.includes("users.manage") && path.startsWith("/users")) {
      return reply(route, 403, { code: "FORBIDDEN", message: "You do not have permission." });
    }
    if (path === "/users" && method === "GET") {
      return reply(route, 200, [ADMIN, BD], { roles: [{ value: "administrator", label: "Administrator" }, { value: "business_development", label: "Business Development" }] });
    }
    if (path === "/audit") {
      return reply(route, 200, [
        { id: "e1", action: "enquiry.status.replied", outcome: "success", target_type: "enquiry", target_id: "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb", request_id: "r1", created_at: "2026-10-02 10:00:00", actor_name: "Ada Admin", actor_email: "ada@paxofi.com" },
        { id: "e2", action: "staff.sign_in", outcome: "failure", target_type: null, target_id: null, request_id: "r2", created_at: "2026-10-02 09:00:00", actor_name: null, actor_email: null },
      ], { page: 1, per_page: 50, total: 2, total_pages: 1 });
    }
    return reply(route, 404, { code: "NOT_FOUND", message: "Not found." });
  });
  return state;
}

async function signIn(page, next = "/admin/enquiries") {
  await page.goto(`${BASE}/admin/login?next=${encodeURIComponent(next)}`);
  await page.getByLabel("Email").fill(ADMIN.email);
  await page.getByLabel("Password").fill(PASSWORD);
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.waitForURL(BASE + next);
}

describe("staff area", () => {
  test("is not indexed and is kept out of robots.txt and the sitemap", async () => {
    const page = await newPage();
    await fakeAdminApi(page);
    await page.goto(`${BASE}/admin/login`);
    assert.match(await page.locator('meta[name="robots"]').getAttribute("content"), /noindex/);
    assert.match(await (await fetch(`${BASE}/robots.txt`)).text(), /Disallow: \/admin/);
    assert.doesNotMatch(await (await fetch(`${BASE}/sitemap.xml`)).text(), /\/admin/);
    await page.context().close();
  });

  test("/admin redirects to the inbox without a page body (ZAP 10044)", async () => {
    const response = await fetch(`${BASE}/admin`, { redirect: "manual" });
    assert.equal(response.status, 307);
    assert.match(response.headers.get("location"), /\/admin\/enquiries$/);
    assert.equal(response.headers.get("x-frame-options"), "DENY");
    assert.ok((await response.text()).length < 100, "redirect body stays tiny");
  });

  test("a signed-out visitor is sent to sign in, then back to the page they asked for", async () => {
    const page = await newPage();
    const api = await fakeAdminApi(page);
    await page.goto(`${BASE}/admin/audit`);
    await page.waitForURL(/\/admin\/login\?next=%2Fadmin%2Faudit/);
    assert.equal(await page.locator("h1").textContent(), "Sign in");
    await assertAccessible(page, "on sign in");

    await page.getByLabel("Email").fill(ADMIN.email);
    await page.getByLabel("Password").fill("wrong password!!");
    await page.getByRole("button", { name: "Sign in" }).click();
    await page.getByRole("alert").filter({ hasText: "The email or password is incorrect." }).waitFor();

    await page.getByLabel("Password").fill(PASSWORD);
    await page.getByRole("button", { name: "Sign in" }).click();
    await page.waitForURL(`${BASE}/admin/audit`);
    await page.getByRole("heading", { name: "Audit log" }).waitFor();
    assert.ok(api.calls.some((c) => c.method === "POST" && c.path === "/session"));
    await page.context().close();
  });

  test("the sign-in page never sends staff to another site after signing in", async () => {
    const page = await newPage();
    await fakeAdminApi(page);
    await page.goto(`${BASE}/admin/login?next=${encodeURIComponent("https://evil.example/")}`);
    await page.getByLabel("Email").fill(ADMIN.email);
    await page.getByLabel("Password").fill(PASSWORD);
    await page.getByRole("button", { name: "Sign in" }).click();
    await page.waitForURL(`${BASE}/admin/enquiries`);
    await page.context().close();
  });

  test("the inbox lists new enquiries, filters by status and opens one", async () => {
    const page = await newPage();
    await fakeAdminApi(page, { signedIn: true });
    const violations = [];
    await page.exposeFunction("reportCsp", (v) => violations.push(v));
    await page.addInitScript(() => document.addEventListener("securitypolicyviolation", (e) => window.reportCsp(`${e.violatedDirective} ${e.blockedURI}`)));

    await page.goto(`${BASE}/admin/enquiries`);
    await page.getByRole("link", { name: "Grace Hopper" }).waitFor();
    assert.equal(await page.getByRole("link", { name: "Alan Turing" }).count(), 0, "the default view shows new enquiries only");
    assert.equal(await page.getByRole("link", { name: "Enquiries" }).getAttribute("aria-current"), "page");
    await assertAccessible(page, "on the inbox");

    await page.getByRole("button", { name: /^All/ }).click();
    await page.getByRole("link", { name: "Alan Turing" }).waitFor();

    await page.getByRole("link", { name: "Grace Hopper" }).click();
    await page.waitForURL(/\/admin\/enquiries\/aaaaaaaa/);
    await page.getByRole("heading", { name: "Enquiry from Grace Hopper" }).waitFor();
    assert.match(await page.locator(".admin-message").textContent(), /Can we talk next week\?/);
    assert.match(await page.getByRole("link", { name: "Reply by email" }).getAttribute("href"), /^mailto:grace@example\.com\?subject=/);
    await assertAccessible(page, "on an enquiry");
    assert.deepEqual(violations, [], "no Content-Security-Policy violations");
    await page.context().close();
  });

  test("changing an enquiry's status saves it and confirms", async () => {
    const page = await newPage();
    const api = await fakeAdminApi(page, { signedIn: true });
    await page.goto(`${BASE}/admin/enquiries/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa`);
    await page.getByLabel("Change status").selectOption("replied");
    await page.getByRole("button", { name: "Save status" }).click();
    await page.getByRole("status").filter({ hasText: "Status changed to “Replied”." }).waitFor();
    const patch = api.calls.find((c) => c.method === "PATCH");
    assert.deepEqual(patch.body, { status: "replied" });
    assert.equal(api.rows[0].status, "replied");
    await page.context().close();
  });

  test("Business Development sees enquiries only, not users or the audit log", async () => {
    const page = await newPage();
    await fakeAdminApi(page, { user: BD, signedIn: true });
    await page.goto(`${BASE}/admin/enquiries`);
    await page.getByRole("link", { name: "Grace Hopper" }).waitFor();
    const nav = page.getByRole("navigation", { name: "Staff area" });
    assert.equal(await nav.getByRole("link", { name: "Users" }).count(), 0);
    assert.equal(await nav.getByRole("link", { name: "Audit log" }).count(), 0);

    await page.goto(`${BASE}/admin/users`);
    await page.getByText("Your role does not include access to this page.").waitFor();
    await page.context().close();
  });

  test("administrators can see users and the audit log", async () => {
    const page = await newPage();
    await fakeAdminApi(page, { signedIn: true });
    await page.goto(`${BASE}/admin/users`);
    await page.getByRole("heading", { name: "Ben Business" }).waitFor();
    assert.match(await page.getByText("This is you.").textContent(), /My account/);
    await page.getByText("Edit Ben Business").click();
    await page.getByText("Add a user").click();
    await assertAccessible(page, "on users");

    await page.goto(`${BASE}/admin/audit`);
    await page.getByRole("cell", { name: "enquiry.status.replied" }).waitFor();
    await assertAccessible(page, "on the audit log");
    await page.context().close();
  });

  test("changing your password checks the confirmation and shows API errors by the field", async () => {
    const page = await newPage();
    await fakeAdminApi(page, { signedIn: true });
    await page.goto(`${BASE}/admin/account`);
    await page.getByRole("heading", { name: "Change password" }).waitFor();
    await assertAccessible(page, "on my account");

    await page.getByLabel("Current password").fill("not my password");
    await page.getByLabel("New password", { exact: true }).fill("a brand new passphrase");
    await page.getByLabel("Confirm new password").fill("a different passphrase");
    await page.getByRole("button", { name: "Change password" }).click();
    await page.getByText("The new passwords do not match.").waitFor();

    await page.getByLabel("Confirm new password").fill("a brand new passphrase");
    await page.getByRole("button", { name: "Change password" }).click();
    await page.getByText("Your current password is incorrect.").waitFor();
    assert.equal(await page.getByLabel("Current password").getAttribute("aria-invalid"), "true");

    await page.getByLabel("Current password").fill(PASSWORD);
    await page.getByRole("button", { name: "Change password" }).click();
    await page.getByText("Your password has been changed.").waitFor();
    await page.context().close();
  });

  test("signing out ends the session and returns to sign in", async () => {
    const page = await newPage();
    const api = await fakeAdminApi(page);
    await signIn(page);
    await page.getByRole("button", { name: "Sign out" }).click();
    await page.waitForURL(/\/admin\/login\?signed_out=1/);
    await page.getByText("You have signed out.").waitFor();
    assert.ok(api.calls.some((c) => c.method === "DELETE" && c.path === "/session"));
    assert.equal(api.signedIn, false);
    await page.context().close();
  });

  test("first-time setup creates the administrator with the setup code", async () => {
    const page = await newPage();
    const api = await fakeAdminApi(page, { setupAvailable: true });
    await page.goto(`${BASE}/admin/login`);
    await page.getByRole("link", { name: "Set up the first administrator" }).click();
    await page.waitForURL(`${BASE}/admin/setup`);
    await page.getByLabel("Setup code").waitFor();
    await assertAccessible(page, "on setup");

    await page.getByLabel("Setup code").fill("s".repeat(32));
    await page.getByLabel("Your name").fill(ADMIN.display_name);
    await page.getByLabel("Email").fill(ADMIN.email);
    await page.getByLabel("Password").fill(PASSWORD);
    await page.getByRole("button", { name: "Create administrator" }).click();
    await page.waitForURL(`${BASE}/admin/enquiries`);
    assert.ok(api.calls.some((c) => c.method === "POST" && c.path === "/setup"));
    await page.context().close();
  });

  test("on a phone the staff area fits the screen", async () => {
    const page = await newPage({ viewport: { width: 360, height: 740 } });
    await fakeAdminApi(page, { signedIn: true });
    for (const path of ["/admin/enquiries", "/admin/enquiries/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa", "/admin/users", "/admin/account"]) {
      await page.goto(BASE + path);
      await page.locator("h1").waitFor();
      await page.waitForLoadState("networkidle");
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      assert.ok(overflow <= 0, `${path} scrolls sideways by ${overflow}px`);
    }
    await page.context().close();
  });
});
