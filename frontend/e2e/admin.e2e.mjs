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
  permissions: ["audit.read", "content.edit", "content.publish", "enquiries.read", "enquiries.update", "users.manage"],
  last_login_at: "2026-10-02 09:00:00",
};
const BD = {
  ...ADMIN,
  id: "22222222-2222-4222-8222-222222222222",
  email: "ben@paxofi.com",
  display_name: "Ben Business",
  role: "business_development",
  role_label: "Business Development",
  permissions: ["content.edit", "enquiries.read", "enquiries.update"],
};

function enquiries() {
  return [
    { id: "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa", name: "Grace Hopper", email: "grace@example.com", company: "Navy Labs", message: "We need a payments partner.\nCan we talk next week?", status: "new", source_ip: "203.0.113.7", user_agent: "Mozilla/5.0", request_id: "req-123", created_at: "2026-10-03 08:15:00" },
    { id: "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb", name: "Alan Turing", email: "alan@example.com", company: null, message: "Question about Paxofi Core.", status: "replied", source_ip: null, user_agent: null, request_id: "req-456", created_at: "2026-10-01 14:00:00" },
  ];
}

/** A stateful fake of /api/v1/admin/* answering inside the browser. */
async function fakeAdminApi(page, { user = ADMIN, signedIn = false, setupAvailable = false, twoFactor = "off", setupStatus } = {}) {
  // twoFactor: "off", "on" (code asked after the password) or "enrol" (administrator must set it up first).
  const PAY = "7b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f01";
  const payContent = { name: "Paxofi Pay", label: "Paxofi Product", icon: "shield-check", summary: "Digital payments infrastructure designed around reliability.", points: ["Transaction certainty"], sort_order: 10 };
  const state = {
    signedIn, user, rows: enquiries(), calls: [], pending: false, twoFactor,
    catalog: { [PAY]: { kind: "products", item: { id: PAY, slug: "paxofi-pay", name: "Paxofi Pay", visible: true, has_draft: false, sort_order: 10, updated_at: "2026-10-03 09:00:00", content: payContent }, draft: null, revisions: [] } },
  };
  const detail = (id) => {
    const entry = state.catalog[id];
    return { item: { ...entry.item, has_draft: entry.draft !== null }, draft: entry.draft, revisions: entry.revisions };
  };
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

    if (path === "/setup" && method === "GET") {
      if (setupStatus === "down") return route.abort("connectionrefused");
      return reply(route, 200, { available: setupAvailable });
    }
    if (path === "/setup" && method === "POST") {
      if (body.setup_token !== "s".repeat(32)) return reply(route, 403, { code: "FORBIDDEN", message: "The setup code is incorrect." });
      state.signedIn = true;
      return reply(route, 201, state.user);
    }
    if (path === "/session" && method === "POST") {
      if (body.email !== state.user.email || body.password !== PASSWORD) {
        return reply(route, 401, { code: "UNAUTHENTICATED", message: "The email or password is incorrect." });
      }
      if (state.twoFactor === "on") {
        state.pending = true;
        return reply(route, 200, { mfa_required: true });
      }
      state.signedIn = true;
      return reply(route, 200, state.user);
    }
    if (path === "/session/mfa") {
      if (!state.pending) return reply(route, 401, unauthenticated);
      if (body.code !== "123456" && body.code !== "abcde-fghjk") {
        return reply(route, 422, { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { code: "That code is not valid." } } });
      }
      state.pending = false;
      state.signedIn = true;
      return reply(route, 200, { ...state.user, two_factor_enabled: true, mfa_required: false });
    }
    if (state.pending) return reply(route, 401, { code: "MFA_REQUIRED", message: "Enter the code from your authenticator app to finish signing in." });
    if (!state.signedIn) return reply(route, 401, unauthenticated);

    if (path === "/session" && method === "GET") {
      return reply(route, 200, { ...state.user, two_factor_enabled: state.twoFactor === "on", two_factor_enrollment_required: state.twoFactor === "enrol" });
    }
    if (path === "/account/two-factor" && method === "GET") {
      return reply(route, 200, { configured: true, enabled: state.twoFactor === "on", required: state.user.role === "administrator", recovery_codes_left: state.twoFactor === "on" ? 10 : 0 });
    }
    if (path === "/account/two-factor/setup") {
      return reply(route, 200, { secret: "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP", otpauth_uri: "otpauth://totp/Paxofi:ada%40paxofi.com?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=Paxofi" });
    }
    if (path === "/account/two-factor/enable") {
      if (body.code !== "654321") {
        return reply(route, 422, { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { code: "That code does not match." } } });
      }
      state.twoFactor = "on";
      return reply(route, 200, { enabled: true, recovery_codes: ["abcde-fghjk", "mnpqr-stuvw", "xyz23-45678", "aaaaa-bbbbb", "ccccc-ddddd", "eeeee-fffff", "ggggg-hhhhh", "jjjjj-kkkkk", "mmmmm-nnnnn", "ppppp-qqqqq"] });
    }
    if (/^\/users\/[0-9a-f-]+\/two-factor\/reset$/.test(path)) return reply(route, 200, { ...BD, two_factor_enabled: false });
    if (state.twoFactor === "enrol") return reply(route, 403, { code: "MFA_ENROLLMENT_REQUIRED", message: "Set up two-factor sign-in to continue." });
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
    const catalogList = path.match(/^\/catalog\/(products|services)$/);
    if (catalogList && method === "GET") {
      const items = Object.values(state.catalog).filter((e) => e.kind === catalogList[1]).map((e) => ({ ...e.item, has_draft: e.draft !== null }));
      return reply(route, 200, items, { icons: ["shield-check", "cog", "code", "layers", "database"] });
    }
    if (catalogList && method === "POST") {
      const id = "9b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f99";
      state.catalog[id] = { kind: catalogList[1], item: { id, slug: "new-item", name: body.name, visible: false, has_draft: false, sort_order: 100, updated_at: null, content: { ...body, label: body.label || null } }, draft: null, revisions: [] };
      return reply(route, 201, detail(id));
    }
    const catalogItem = path.match(/^\/catalog\/(products|services)\/([0-9a-f-]+)(\/.*)?$/);
    if (catalogItem) {
      const [, , id, action = ""] = catalogItem;
      const entry = state.catalog[id];
      if (!entry) return reply(route, 404, { code: "NOT_FOUND", message: "Item not found." });
      const publisher = state.user.permissions.includes("content.publish");
      if (action === "/draft" && method === "POST") {
        if (!body.summary || body.summary.length < 10) {
          return reply(route, 422, { code: "VALIDATION_ERROR", message: "Please correct the highlighted fields.", details: { fields: { summary: "Enter 10 to 300 characters." } } });
        }
        entry.draft = { content: { ...body, label: body.label || null, sort_order: Number(body.sort_order) }, saved_at: "2026-10-03 10:00:00", author_name: state.user.display_name };
      } else if (action === "/draft" && method === "DELETE") {
        entry.draft = null;
      } else if (action === "/publish") {
        if (!publisher) return reply(route, 403, { code: "FORBIDDEN", message: "An administrator publishes changes." });
        entry.item.content = entry.draft.content;
        entry.item.name = entry.draft.content.name;
        entry.revisions.unshift({ id: "r" + entry.revisions.length, state: "published", created_at: "2026-10-03 10:01:00", author_name: state.user.display_name, content: entry.draft.content });
        entry.draft = null;
      } else if (action === "/visibility") {
        if (!publisher) return reply(route, 403, { code: "FORBIDDEN", message: "An administrator decides what is shown." });
        entry.item.visible = body.visible;
      }
      return reply(route, 200, detail(id));
    }
    if (!state.user.permissions.includes("users.manage") && path.startsWith("/users")) {
      return reply(route, 403, { code: "FORBIDDEN", message: "You do not have permission." });
    }
    if (path === "/users" && method === "GET") {
      return reply(route, 200, [ADMIN, { ...BD, two_factor_enabled: true }], { roles: [{ value: "administrator", label: "Administrator" }, { value: "business_development", label: "Business Development" }] });
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

  test("/admin redirects to the inbox with a tiny, typed body", async () => {
    const response = await fetch(`${BASE}/admin`, { redirect: "manual" });
    assert.equal(response.status, 307);
    assert.match(response.headers.get("location"), /\/admin\/enquiries$/);
    assert.equal(response.headers.get("x-frame-options"), "DENY");
    // A tiny, typed body: no "Big Redirect" (ZAP 10044), no untyped content (ZAP 10019).
    assert.match(response.headers.get("content-type") ?? "", /^text\/plain/);
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

  test("with two-factor on, sign-in asks for the authenticator code", async () => {
    const page = await newPage();
    const api = await fakeAdminApi(page, { twoFactor: "on" });
    await page.goto(`${BASE}/admin/login?next=${encodeURIComponent("/admin/audit")}`);
    await page.getByLabel("Email").fill(ADMIN.email);
    await page.getByLabel("Password").fill(PASSWORD);
    await page.getByRole("button", { name: "Sign in" }).click();

    await page.getByRole("heading", { name: "Enter your code" }).waitFor();
    assert.equal(await page.getByLabel("Authenticator code").getAttribute("autocomplete"), "one-time-code");
    await page.getByText("Lost your phone? Enter one of your recovery codes instead.").waitFor();
    await assertAccessible(page, "on the code step");

    await page.getByLabel("Authenticator code").fill("000000");
    await page.getByRole("button", { name: "Verify and sign in" }).click();
    await page.getByText("That code is not valid.").waitFor();
    assert.equal(await page.getByLabel("Authenticator code").getAttribute("aria-invalid"), "true");

    await page.getByLabel("Authenticator code").fill("123456");
    await page.getByRole("button", { name: "Verify and sign in" }).click();
    await page.waitForURL(`${BASE}/admin/audit`);
    assert.ok(api.calls.some((c) => c.path === "/session/mfa" && c.body.code === "123456"));
    await page.context().close();
  });

  test("a session still waiting for its code is sent back to the code step", async () => {
    const page = await newPage();
    const api = await fakeAdminApi(page, { twoFactor: "on" });
    api.pending = true;
    await page.goto(`${BASE}/admin/enquiries`);
    await page.waitForURL(/\/admin\/login\?next=%2Fadmin%2Fenquiries&mfa=1/);
    await page.getByLabel("Authenticator code").fill("abcde-fghjk");
    await page.getByRole("button", { name: "Verify and sign in" }).click();
    await page.waitForURL(`${BASE}/admin/enquiries`);
    await page.context().close();
  });

  test("an administrator without two-factor sets it up before anything else", async () => {
    const page = await newPage();
    await fakeAdminApi(page, { signedIn: true, twoFactor: "enrol" });
    await page.goto(`${BASE}/admin/enquiries`);
    await page.waitForURL(`${BASE}/admin/account/two-factor`);
    await page.getByText("Administrators must use two-factor sign-in.").waitFor();
    const nav = page.getByRole("navigation", { name: "Staff area" });
    assert.equal(await nav.getByRole("link", { name: "Enquiries" }).count(), 0, "only My account while enrolling");

    await page.getByRole("button", { name: "Set up two-factor sign-in" }).click();
    const qr = page.getByRole("img", { name: "QR code to add Paxofi to your authenticator app" });
    await qr.waitFor();
    assert.match(await qr.getAttribute("src"), /^data:image\/svg\+xml;utf8,/);
    await page.getByText("JBSW Y3DP EHPK 3PXP").waitFor();
    await assertAccessible(page, "on two-factor set-up");

    await page.getByLabel("6-digit code").fill("111111");
    await page.getByRole("button", { name: "Turn on two-factor sign-in" }).click();
    await page.getByText("That code does not match.").waitFor();
    await page.getByLabel("6-digit code").fill("654321");
    await page.getByRole("button", { name: "Turn on two-factor sign-in" }).click();

    await page.getByRole("heading", { name: "Save your recovery codes" }).waitFor();
    assert.equal(await page.locator(".admin-codes li").count(), 10);
    const done = page.getByRole("button", { name: "Done" });
    assert.equal(await done.isDisabled(), true, "Done waits for the saved checkbox");
    await assertAccessible(page, "on recovery codes");
    await page.getByLabel("I have saved my recovery codes").check();
    await done.click();
    await page.getByText("Recovery codes left:").waitFor();
    await nav.getByRole("link", { name: "Enquiries" }).waitFor();
    await page.context().close();
  });

  test("administrators can reset another person's two-factor", async () => {
    const page = await newPage();
    const api = await fakeAdminApi(page, { signedIn: true });
    await page.goto(`${BASE}/admin/users`);
    await page.getByText("Two-factor on").first().waitFor();
    await page.getByText("Edit Ben Business").click();
    await page.getByRole("button", { name: "Reset two-factor for Ben Business" }).click();
    await page.getByText("Two-factor sign-in was reset for Ben Business.").waitFor();
    assert.ok(api.calls.some((c) => c.method === "POST" && c.path === `/users/${BD.id}/two-factor/reset`));
    await page.context().close();
  });

  test("first-time setup says when the staff service cannot be reached", async () => {
    const page = await newPage();
    await fakeAdminApi(page, { setupStatus: "down" });
    await page.goto(`${BASE}/admin/setup`);
    await page.getByText("The staff service could not be reached").waitFor();
    assert.equal(await page.getByText("Setup is not available").count(), 0);
    await page.context().close();
  });

  test("administrators edit a product with a live preview, then publish it", async () => {
    const page = await newPage();
    const api = await fakeAdminApi(page, { signedIn: true });
    await page.goto(`${BASE}/admin/content`);
    await page.getByRole("link", { name: "Paxofi Pay" }).click();
    await page.getByRole("heading", { name: "Paxofi Pay", level: 1 }).waitFor();
    await page.getByText("Shown on the website").waitFor();
    await assertAccessible(page, "on the content editor");

    await page.getByLabel("Summary").fill("Payments you can rely on, with recovery built in.");
    await page.locator(".admin-preview").getByText("Payments you can rely on, with recovery built in.").waitFor();

    await page.getByRole("button", { name: "Publish" }).click();
    await page.getByText("Published. The website shows the new version now.").waitFor();
    const order = api.calls.filter((c) => c.path.startsWith("/catalog/products/") && c.method === "POST").map((c) => c.path.split("/").pop());
    assert.deepEqual(order, ["draft", "publish"], "saves the form, then publishes it");
    await page.getByText("Restore as draft").waitFor();

    await page.getByRole("button", { name: "Hide from website" }).click();
    await page.getByText("Hidden from the website.").waitFor();
    await page.context().close();
  });

  test("Business Development saves drafts but cannot publish", async () => {
    const page = await newPage();
    await fakeAdminApi(page, { user: BD, signedIn: true });
    await page.goto(`${BASE}/admin/content`);
    await page.getByRole("link", { name: "Paxofi Pay" }).click();
    await page.getByLabel("Summary").waitFor();
    assert.equal(await page.getByRole("button", { name: "Publish" }).count(), 0);
    assert.equal(await page.getByRole("button", { name: /from website|on website/ }).count(), 0);

    await page.getByLabel("Summary").fill("short");
    await page.getByRole("button", { name: "Save draft" }).click();
    await page.getByText("Enter 10 to 300 characters.").waitFor();
    await page.getByLabel("Summary").fill("Wording proposed by business development.");
    await page.getByRole("button", { name: "Save draft" }).click();
    await page.getByText("Draft saved. An administrator will publish it.").waitFor();
    await page.getByText(/Unpublished changes saved .* by Ben Business/).waitFor();
    await page.context().close();
  });

  test("a new service is added hidden and opens in the editor", async () => {
    const page = await newPage();
    await fakeAdminApi(page, { signedIn: true });
    await page.goto(`${BASE}/admin/content`);
    await page.getByRole("button", { name: "Services" }).click();
    await page.getByRole("link", { name: "Add a service" }).click();
    await page.waitForURL(`${BASE}/admin/content/services/new`);
    assert.equal(await page.getByLabel("Label (optional)").count(), 0, "services have no label");
    await page.getByLabel("Name").fill("Data & AI Engineering");
    await page.getByLabel("Icon").selectOption("database");
    await page.getByLabel("Summary").fill("Data platforms and practical AI features.");
    await assertAccessible(page, "on add content");
    await page.getByRole("button", { name: "Add service (hidden)" }).click();
    await page.waitForURL(/\/admin\/content\/services\/9b0f3a0e/);
    await page.getByText("Hidden from the website").first().waitFor();
    await page.context().close();
  });

  test("on a phone the staff area fits the screen", async () => {
    const page = await newPage({ viewport: { width: 360, height: 740 } });
    await fakeAdminApi(page, { signedIn: true });
    for (const path of ["/admin/enquiries", "/admin/enquiries/aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa", "/admin/users", "/admin/account", "/admin/content", "/admin/content/products/7b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f01"]) {
      await page.goto(BASE + path);
      await page.locator("h1").waitFor();
      await page.waitForLoadState("networkidle");
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      assert.ok(overflow <= 0, `${path} scrolls sideways by ${overflow}px`);
    }
    await page.context().close();
  });
});
