// Endpoint contract (RTM: docs/ENDPOINT-RTM-V1.md). Admin routes exist from
// Phase 2.1 (decision D-009) and must stay behind the staff session guard.
const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const root = path.join(__dirname, "..");
const read = (...parts) => fs.readFileSync(path.join(root, ...parts), "utf8");

const routes = [
  ["GET", "/api/v1/health", "public"],
  ["GET", "/api/v1/readiness", "public"],
  ["GET", "/api/v1/content", "public"],
  ["GET", "/api/v1/navigation", "public"],
  ["GET", "/api/v1/products", "public"],
  ["GET", "/api/v1/services", "public"],
  ["GET", "/api/v1/careers", "public"],
  ["GET", "/api/v1/careers/roles", "public"],
  ["GET", "/api/v1/careers/roles/{slug}", "public"],
  ["POST", "/api/v1/careers/cv", "public+rate-limit"],
  ["POST", "/api/v1/careers/roles/{slug}/apply", "public+rate-limit"],
  ["POST", "/api/v1/forms/{form_key}/submit", "public+rate-limit"],
  ["GET", "/api/v1/media/{id}/{filename}", "public"],
  ["POST", "/api/v1/analytics/pageview", "public"],
  ["GET", "/api/v1/pages/{page}", "public"],
  ["GET", "/api/v1/admin/setup", "admin-entry"],
  ["POST", "/api/v1/admin/setup", "admin-entry"],
  ["POST", "/api/v1/admin/session", "admin-entry"],
  ["GET", "/api/v1/admin/session", "admin"],
  ["DELETE", "/api/v1/admin/session", "admin"],
  ["POST", "/api/v1/admin/session/password", "admin"],
  ["POST", "/api/v1/admin/session/mfa", "admin"],
  ["POST", "/api/v1/admin/password-reset", "admin-entry"],
  ["POST", "/api/v1/admin/password-reset/complete", "admin-entry"],
  ["GET", "/api/v1/admin/account/two-factor", "admin"],
  ["POST", "/api/v1/admin/account/two-factor/setup", "admin"],
  ["POST", "/api/v1/admin/account/two-factor/enable", "admin"],
  ["POST", "/api/v1/admin/account/two-factor/recovery-codes", "admin"],
  ["POST", "/api/v1/admin/account/two-factor/disable", "admin"],
  ["GET", "/api/v1/admin/enquiries", "admin"],
  ["GET", "/api/v1/admin/enquiries/{id}", "admin"],
  ["PATCH", "/api/v1/admin/enquiries/{id}", "admin"],
  ["GET", "/api/v1/admin/users", "admin"],
  ["POST", "/api/v1/admin/users", "admin"],
  ["PATCH", "/api/v1/admin/users/{id}", "admin"],
  ["POST", "/api/v1/admin/users/{id}/two-factor/reset", "admin"],
  ["GET", "/api/v1/admin/audit", "admin"],
  ["GET", "/api/v1/admin/catalog/{type}", "admin"],
  ["POST", "/api/v1/admin/catalog/{type}", "admin"],
  ["GET", "/api/v1/admin/catalog/{type}/{id}", "admin"],
  ["POST", "/api/v1/admin/catalog/{type}/{id}/draft", "admin"],
  ["DELETE", "/api/v1/admin/catalog/{type}/{id}/draft", "admin"],
  ["POST", "/api/v1/admin/catalog/{type}/{id}/publish", "admin"],
  ["POST", "/api/v1/admin/catalog/{type}/{id}/visibility", "admin"],
  ["POST", "/api/v1/admin/catalog/{type}/{id}/revisions/{revision}/restore", "admin"],
  ["GET", "/api/v1/admin/media", "admin"],
  ["POST", "/api/v1/admin/media", "admin"],
  ["PATCH", "/api/v1/admin/media/{id}", "admin"],
  ["DELETE", "/api/v1/admin/media/{id}", "admin"],
  ["GET", "/api/v1/admin/analytics", "admin"],
  ["GET", "/api/v1/admin/pages", "admin"],
  ["GET", "/api/v1/admin/pages/{page}", "admin"],
  ["POST", "/api/v1/admin/pages/{page}/draft", "admin"],
  ["DELETE", "/api/v1/admin/pages/{page}/draft", "admin"],
  ["POST", "/api/v1/admin/pages/{page}/publish", "admin"],
  ["POST", "/api/v1/admin/pages/{page}/revisions/{revision}/restore", "admin"],
  ["GET", "/api/v1/admin/applications", "admin"],
  ["GET", "/api/v1/admin/applications/{id}", "admin"],
  ["GET", "/api/v1/admin/applications/{id}/cv", "admin"],
  ["PATCH", "/api/v1/admin/applications/{id}", "admin"],
  ["POST", "/api/v1/admin/applications/{id}/scores", "admin"],
  ["POST", "/api/v1/admin/applications/{id}/notes", "admin"],
  ["POST", "/api/v1/admin/applications/{id}/emails", "admin"],
  ["DELETE", "/api/v1/admin/applications/{id}", "admin"],
  ["GET", "/api/v1/admin/career-roles", "admin"],
  ["POST", "/api/v1/admin/career-roles", "admin"],
  ["GET", "/api/v1/admin/career-roles/{id}", "admin"],
  ["PATCH", "/api/v1/admin/career-roles/{id}", "admin"],
  ["POST", "/api/v1/admin/career-roles/{id}/state", "admin"],
];

test("endpoint RTM has unique method/route entries", () => {
  const keys = routes.map((r) => r[0] + " " + r[1]);
  assert.equal(new Set(keys).size, keys.length);
});

test("public release routes are represented", () => {
  for (const route of ["/api/v1/health", "/api/v1/readiness", "/api/v1/content", "/api/v1/navigation", "/api/v1/products", "/api/v1/services", "/api/v1/careers"]) {
    assert.ok(routes.some((r) => r[1] === route && r[2] === "public"), route);
  }
});

test("route inventory matches backend/config/routes.php exactly", () => {
  const inventory = [...read("backend", "config", "routes.php").matchAll(/\['([A-Z]+)','([^']+)','([^']+)'/g)].map((m) => m.slice(1, 4).join(" "));
  assert.deepEqual(inventory.sort(), routes.map((r) => r.join(" ")).sort());
});

test("every admin route requires the staff guard, except the sign-in and password-reset entry points", () => {
  for (const [method, route, auth] of routes.filter((r) => r[1].startsWith("/api/v1/admin"))) {
    const entry = (route === "/api/v1/admin/setup") || (method === "POST" && route === "/api/v1/admin/session") || route.startsWith("/api/v1/admin/password-reset");
    assert.equal(auth, entry ? "admin-entry" : "admin", `${method} ${route}`);
  }
});

test("every admin controller action goes through AdminGuard", () => {
  const dir = path.join(root, "backend", "src", "Http", "Controllers", "Admin");
  const files = fs.readdirSync(dir).filter((f) => f.endsWith(".php"));
  assert.ok(files.length >= 4, "admin controllers present");
  for (const file of files) {
    const source = fs.readFileSync(path.join(dir, file), "utf8");
    const actions = source.split(/\n    public function /).slice(1).filter((body) => !body.startsWith("__construct"));
    for (const body of actions) {
      const name = body.slice(0, body.indexOf("("));
      // setupStatus only reports whether first-time setup is open (no data, no change).
      if (name === "setupStatus") continue;
      assert.match(body, /\$this->guard->(require|requireTrustedOrigin)\(/, `${file}::${name} must call AdminGuard`);
    }
  }
});

test("media and CV uploads read large bodies on their upload routes only (D-012, D-019)", () => {
  const factory = read("backend", "src", "Http", "RequestFactory.php");
  assert.match(factory, /UPLOAD_PATH = '\/api\/v1\/admin\/media'/);
  assert.match(factory, /CV_UPLOAD_PATH = '\/api\/v1\/careers\/cv'/);
  assert.match(read("backend", "public", "index.php"), /readBody\(RequestFactory::bodyLimit\(\$_SERVER\)\)/);
});

test("the RTM documents every route", () => {
  const rtm = read("docs", "ENDPOINT-RTM-V1.md");
  for (const [, route] of routes) assert.ok(rtm.includes(route), route + " missing from docs/ENDPOINT-RTM-V1.md");
});

test("the website and the API use the same page text fields (D-015)", () => {
  assert.equal(read("frontend", "lib", "page-copy.json"), read("backend", "config", "page-copy.json"));
});
