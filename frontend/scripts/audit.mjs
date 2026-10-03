// Dependency audit gate (CI "Frontend validation").
// 1. Runtime dependencies must have no advisory of moderate severity or above.
// 2. Development dependencies may only carry advisories listed, with a reason
//    and an unexpired date, in audit-exceptions.json.
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";

const SEVERE = new Set(["moderate", "high", "critical"]);
const today = new Date().toISOString().slice(0, 10);

function audit(args) {
  try {
    return JSON.parse(execFileSync("npm", ["audit", "--json", ...args], { encoding: "utf8", maxBuffer: 64 * 1024 * 1024 }));
  } catch (error) {
    // npm audit exits non-zero when it finds advisories; the JSON is still on stdout.
    if (error.stdout) return JSON.parse(error.stdout);
    throw error;
  }
}

/** Advisories (GHSA id → {package, severity}) of moderate severity or above. */
function advisories(report) {
  const found = new Map();
  for (const vulnerability of Object.values(report.vulnerabilities ?? {})) {
    for (const via of vulnerability.via) {
      if (typeof via === "object" && SEVERE.has(via.severity)) {
        const id = via.url?.split("/").pop() ?? `${via.name}:${via.title}`;
        found.set(id, { package: via.name, severity: via.severity, title: via.title });
      }
    }
  }
  return found;
}

const exceptions = JSON.parse(readFileSync(new URL("../audit-exceptions.json", import.meta.url), "utf8")).exceptions;
const accepted = new Map(exceptions.map((e) => [e.advisory, e]));
const problems = [];

const runtime = advisories(audit(["--omit=dev"]));
for (const [id, a] of runtime) problems.push(`${id} (${a.severity}) in runtime dependency ${a.package}: ${a.title} — runtime advisories cannot be excepted`);

const all = advisories(audit([]));
for (const [id, a] of all) {
  if (runtime.has(id)) continue;
  const exception = accepted.get(id);
  if (!exception) problems.push(`${id} (${a.severity}) in ${a.package}: ${a.title}`);
  else if (exception.expires < today) problems.push(`${id}: exception expired on ${exception.expires}; review it (RUNBOOKS RB-9)`);
  else console.log(`accepted until ${exception.expires}: ${id} (${a.severity}) in development dependency ${a.package}`);
}
for (const e of exceptions) {
  if (!all.has(e.advisory)) console.log(`note: ${e.advisory} no longer reported; remove it from audit-exceptions.json`);
}

if (problems.length > 0) {
  console.error("Dependency audit failed:\n- " + problems.join("\n- "));
  process.exit(1);
}
console.log(`Dependency audit passed: runtime clean, ${all.size} accepted development-only advisor${all.size === 1 ? "y" : "ies"}.`);
