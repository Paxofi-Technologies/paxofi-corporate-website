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
  ["POST", "/api/v1/forms/{form_key}/submit", "public+rate-limit"],
  ["GET", "/api/v1/admin/setup", "admin-entry"],
  ["POST", "/api/v1/admin/setup", "admin-entry"],
  ["POST", "/api/v1/admin/session", "admin-entry"],
  ["GET", "/api/v1/admin/session", "admin"],
  ["DELETE", "/api/v1/admin/session", "admin"],
  ["POST", "/api/v1/admin/session/password", "admin"],
  ["POST", "/api/v1/admin/session/mfa", "admin"],
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

test("every admin route requires the staff guard, except the sign-in entry points", () => {
  for (const [method, route, auth] of routes.filter((r) => r[1].startsWith("/api/v1/admin"))) {
    const entry = (route === "/api/v1/admin/setup") || (method === "POST" && route === "/api/v1/admin/session");
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

test("admin areas not built yet (CMS, media) are not exposed", () => {
  const sources = read("backend", "config", "routes.php") + read("backend", "src", "Bootstrap", "ApiApplication.php");
  for (const route of ["/api/v1/admin/content", "/api/v1/admin/media"]) assert.ok(!sources.includes(route), route);
});

test("the RTM documents every route", () => {
  const rtm = read("docs", "ENDPOINT-RTM-V1.md");
  for (const [, route] of routes) assert.ok(rtm.includes(route), route + " missing from docs/ENDPOINT-RTM-V1.md");
});
