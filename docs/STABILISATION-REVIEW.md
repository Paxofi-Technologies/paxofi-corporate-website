# Post-launch stabilisation review — CW-OPS2-003

**Meeting:** Friday 16 Oct 2026, 45 minutes. **Attendees:** owner (chair), Operations (operator on duty), Engineering (CTO), Business Development, Human Resources.
**Period reviewed:** go-live on 2 Oct 2026 to 15 Oct 2026.
**Acceptance (Asana CW-OPS2-003):** initial production evidence reviewed and stabilisation actions tracked.

The review decides one thing: is the website stable enough to leave the launch period and run as normal operations (weekly checks, RB-1 to RB-24, the 30-day review on 1 Nov)? Or does it need a stabilisation action list first?

## 1. Before the meeting

| When | Who | What | Where the result goes |
|---|---|---|---|
| By 15 Oct | Operations | Run `bash ops/stabilisation-check.sh` from any computer with bash, curl and openssl. Paste the output. | §3.1 |
| By 15 Oct | Operations | phpMyAdmin → `paxoalhu_corporate` → **SQL** → paste `ops/stabilisation-evidence.sql` → **Go**. Copy each result table. The queries only read data and show counts and timings, no personal data. | §3.2–§3.6 |
| By 15 Oct | Operations | UptimeRobot → each monitor → uptime since 2 Oct, and the list of incidents. | §3.1 |
| By 15 Oct | Operations | cPanel → File Manager → `logs/`: last 14 lines of `purge-retention.log`, last lines of `backup.log` and `send-mail.log`; `stderr.log` in both Node app folders; `error_log` in `paxofi-api-runtime/backend/public`. | §3.4, §3.5 |
| By 15 Oct | Operations | Run PageSpeed Insights (mobile) on `/`, `/products` and `https://careers.paxofi.com/`. | §3.1 |
| By 15 Oct | Engineering | Fill in §2 again on the day: release, CI, Dependabot, open tasks. | §2 |

Paste results into the PKDMS copy of this page. Do not paste names, email addresses, messages or CVs.

## 2. Engineering evidence (prepared 10 Oct 2026; refresh on 15 Oct)

| Item | State on 10 Oct | Check on 15 Oct |
|---|---|---|
| Release live | Release 25 (6 Oct 2026, P3.6 reviews and redirects). UAT CR1–CR7 passed. `/release.txt` shows the version. | Release 26 deployed? (see below) |
| Release 26 (prepared 10 Oct) | No database change and no new features. It brings: a minor update of the icon library (`lucide-react` 1.50 → 1.52), the review tools in this document, and `HANDOVER.md` updates. It may be deployed before or after the review. | — |
| Planned scope | All planned work is built and live: v1, Phase 2 (P2.1–P2.8), careers site (C1–C3), Phase 3 (P3.1–P3.6), Cloudflare (D-024). Decisions D-001 to D-025. | — |
| CI on `main` | Green on every merge since go-live (unit, MariaDB integration, component, E2E in three browsers with WCAG 2.1 AA, OWASP ZAP baseline, dependency audits). | Latest `main` run green |
| Dependabot | One update PR (frontend minor/patch) merged on 10 Oct; none open. | Open PRs: ___ |
| Known issues | KI-001 Facebook link previews (blocked by the host until the VPS move). D-004 interim logo. | Changes: ___ |
| Open Asana items | Careers site launch and campaign go-ahead (due 19 Oct). Plan the first Insights articles (owner). KI-001 retest after the VPS move. CW-OPS2-004 30-day review (1 Nov). | — |
| Repository secrets | `PCF_COMPOSER_AUTH` / `PCF_GITHUB_TOKEN` expiry date: ___ (rotate before it expires) | — |

## 3. Production evidence (Operations fills in)

Each line has a pass condition. Mark it **Pass**, **Watch** (acceptable, keep an eye on it) or **Action** (open an Asana task, §5).

### 3.1 Availability and speed (D-007)

| Check | Pass when | Result |
|---|---|---|
| `stabilisation-check.sh` | "all checks passed" or warnings only | |
| UptimeRobot uptime since 2 Oct, each monitor | ≥ 99.5% | |
| UptimeRobot incidents | Each P1/P2 has a PKDMS record with cause and fix | |
| UptimeRobot monitors | Website, release, API health, API readiness **and careers.paxofi.com** exist and alert the operations email (HANDOVER §1 asks for the careers monitor; RUNBOOKS lists four) | |
| PageSpeed Insights (mobile) | LCP ≤ 2.5 s, performance ≥ 90 | |
| Certificates | Valid more than 14 days on all three names (the script checks) | |

### 3.2 Enquiries (D-007: first reply within 2 business days)

| Check | Pass when | Result |
|---|---|---|
| Query 1: enquiries by status | Nothing left in `new` | |
| Query 2: hours to first action | No "CHECK" rows, or each one explained | |
| Query 3: spam traps and rate limits | Trap and rate-limit counts small next to real enquiries; if spam gets through, note how much | |
| Enquiry alert emails | Business Development confirms they arrive for each new enquiry | |

### 3.3 Recruitment (D-019)

| Check | Pass when | Result |
|---|---|---|
| Query 4: applications by stage | `waiting_over_4_days` is 0 | |
| Staff area → Recruitment → report | No *overdue* applications | |
| Candidate acknowledgement emails | Query 6: kind `candidate_acknowledgement` (and `application_alert` to HR) shows only `sent` | |
| Careers site ready for launch on 19 Oct | QA checklist done (Asana), roles published, campaign links ready (RB-19) | |

### 3.4 Background jobs, email and backups

| Check | Pass when | Result |
|---|---|---|
| `purge-retention.log` | One line per day since it was set up; no "failed" | |
| Query 7: retention | Every column 0 | |
| `send-mail.log` + query 6 | No `failed` emails; nothing `pending` for more than an hour | |
| `backup.log` + `paxofi-backups/` | A new database and media file every night; 14 days kept | |
| Off-server copy (RB-18) | At least one weekly copy downloaded and stored off the server | |
| Restore test | Not required now; planned for the 30-day review (§5) | |

### 3.5 Errors and security

| Check | Pass when | Result |
|---|---|---|
| `error_log` (API) and `stderr.log` (both Node apps) | No repeating errors; each distinct error explained or has a task | |
| Error alert emails received | Each one explained | |
| Query 5: staff sign-ins | No unexplained bursts of wrong passwords or blocks | |
| Query 10: other failures | Nothing unexplained | |
| Staff accounts | Every administrator has two-factor on (D-010 requires it; Human Resources and Business Development should too); nobody who has left still has an active account (RB-11) | |
| Staging | Asks for the password; not in search results (RB-14) | |
| Cloudflare | A test enquiry shows your own address, not a Cloudflare one (RB-23) | |

### 3.6 Content and visitors

| Check | Pass when | Result |
|---|---|---|
| Query 8: visitors per day | Numbers look plausible (no zero days, no sudden jumps without a reason) | |
| Query 9 + Content → Reviews | Review dates set at least for the home page, products and contact details (RB-24) | |
| Insights | First articles planned (open owner task) | |
| Disk usage (cPanel) | Below 70% of the quota; old `…-old-<version>` folders deleted | |

## 4. Agenda (45 minutes)

1. Evidence walk-through, §2 and §3 (20 min). Go through the Watch and Action lines only.
2. Incidents and near misses since go-live (5 min).
3. Careers launch on 19 Oct: go or wait (5 min).
4. Decision: close the stabilisation period, or extend it with a dated action list (5 min).
5. Actions, owners and dates (5 min). Confirm the 30-day review on 1 Nov (CW-OPS2-004).
6. Anything for the change backlog (5 min). New work goes into Asana first (PRODUCT-BASELINE §13).

## 5. Outcome

**Decision:** ☐ Stabilisation closed — normal operations from 16 Oct  ☐ Extended to ___ with the actions below

| # | Action | Owner | Due | Asana |
|---|---|---|---|---|
| 1 | Restore test: restore last night's backup into a new empty database and check it (RB-18) | Operations | before 1 Nov | |
| 2 | | | | |

**Carry into the 30-day review (1 Nov):** a full month of UptimeRobot data against 99.5%; LCP; P1/P2 response times; enquiry reply times; the restore test.

Record the outcome in Asana (CW-OPS2-003 comment, then mark it complete) and in PKDMS.
