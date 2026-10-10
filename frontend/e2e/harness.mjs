// Shared set-up for the end-to-end suites: starts `next start` on a production
// build and launches the browser chosen by E2E_BROWSER (chromium, firefox or
// webkit). Set PLAYWRIGHT_CHROMIUM_PATH to use a preinstalled Chromium.
import { after, before } from "node:test";
import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { setTimeout as sleep } from "node:timers/promises";
import { chromium, firefox, webkit } from "playwright";
import AxeBuilder from "@axe-core/playwright";

export const API_BASE = "https://api.e2e.test/api/v1";

const ENGINES = { chromium, firefox, webkit };
export const BROWSER = process.env.E2E_BROWSER || "chromium";
assert.ok(ENGINES[BROWSER], `unknown E2E_BROWSER ${BROWSER}`);

/**
 * Registers before/after hooks for one suite file; returns its base URL and a page factory.
 * apiBase: the API the server itself reads (catalogue pages); defaults to an unreachable host.
 * env: extra settings for the website server (e.g. staging mode, D-013).
 * host: the address the server listens on. "0.0.0.0" is what app.js uses on
 * cPanel; it is needed where proxy.ts rewrites paths (Next.js names 127.0.0.1
 * "localhost" in rewrites and would otherwise proxy them back to itself).
 */
export function startHarness(port, { apiBase = API_BASE, env = {}, host = "127.0.0.1" } = {}) {
  const base = `http://127.0.0.1:${port}`;
  let server;
  let browser;

  before(async () => {
    const root = new URL("..", import.meta.url).pathname;
    // Own process group, so the whole server tree can be stopped afterwards.
    server = spawn(`${root}node_modules/.bin/next`, ["start", "-p", String(port), "-H", host], {
      cwd: root,
      detached: true,
      env: { ...process.env, API_BASE_URL: apiBase, NODE_ENV: "production", ...env },
      stdio: ["ignore", "ignore", "inherit"],
    });
    for (let attempt = 0; attempt < 60; attempt++) {
      try {
        // Any HTTP answer means the server is up (staging answers 401 without a password).
        if ((await fetch(base)).status < 500) break;
      } catch {
        // server not listening yet
      }
      await sleep(500);
    }
    const executablePath = BROWSER === "chromium" ? process.env.PLAYWRIGHT_CHROMIUM_PATH || undefined : undefined;
    browser = await ENGINES[BROWSER].launch({ executablePath });
  });

  after(async () => {
    await browser?.close();
    if (server?.pid) process.kill(-server.pid, "SIGTERM");
  });

  return {
    base,
    /** A page in its own browser context (axe-core requires one per page). */
    async newPage(options = {}) {
      const context = await browser.newContext(options);
      return context.newPage();
    },
  };
}

/** Fails with a readable list of WCAG 2.1 AA violations on the current page. */
export async function assertAccessible(page, label = "") {
  // After a client-side redirect Next.js sets <title> a moment later; give it
  // up to 5 s. A page that never gets a title still fails below.
  await page.waitForFunction(() => document.title.trim() !== "", null, { timeout: 5000 }).catch(() => {});
  const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"]).analyze();
  const summary = results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`);
  assert.deepEqual(summary, [], `WCAG 2.1 AA violations ${label}`);
}
