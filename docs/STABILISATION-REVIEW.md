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

## 2. Engineering evidence (refreshed 12 Oct 2026; check again on 15 Oct)

| Item | State on 12 Oct | Check on 15 Oct |
|---|---|---|
| Release live | Corporate: `20261012-b05f22e` (Email page, D-031). **Careers: `20261012-dd25c03`, one release behind** (found by `stabilisation-check.sh` on 12 Oct). | Both apps show the same `/release.txt` |
| Latest release built | `20261012-97674ca` (12 Oct): the Email page explains SSL connection failures. No database change. Deploy the API zip, and the website zip to **both** Node.js apps. | Deployed? |
| Releases since 10 Oct | 26 (icon library update, review tools); 27 (PIF engagement types, 15 Cohort 1 positions, D-027); D-028 (opening dates set in the staff area); 28 (reviewers with recusal, talent pool, certificates and careers.paxofi.com/verify, D-029); D-030 (close applications, emails never stall silently); D-031 (Email page and SMTP settings in the staff area, test-application button). | — |
| Planned scope | v1, Phase 2 (P2.1–P2.8), careers site (C1–C3), Phase 3 (P3.1–P3.6), Cloudflare (D-024) and the PIF recruitment features (D-027 to D-031) are built. Decisions D-001 to D-031. | — |
| CI on `main` | Green on every merge (unit, MariaDB integration, component, E2E in three browsers with WCAG 2.1 AA, OWASP ZAP baseline, dependency audits). Latest: promotion PR 112, 12 Oct. | Latest `main` run green |
| Dependabot | No open pull requests on 12 Oct. | Open PRs: ___ |
| Known issues | KI-001 Facebook link previews (blocked by the host until the VPS move). D-004 interim logo. KI-002 closed (D-028). | Changes: ___ |
| Incidents | Two since go-live, both recorded in §3.7. | New ones: ___ |
| Open Asana items | Careers launch and campaign go-ahead, **moved to Gate 1, Fri 30 Oct** (applications open Mon 2 Nov). Plan the first Insights articles (owner). KI-001 retest after the VPS move. CW-OPS2-004 30-day review (1 Nov). | — |
| Repository secrets | `PCF_COMPOSER_AUTH` / `PCF_GITHUB_TOKEN` expiry date: ___ (rotate before it expires) | — |

## 3. Production evidence (Operations fills in)

Each line has a pass condition. Mark it **Pass**, **Watch** (acceptable, keep an eye on it) or **Action** (open an Asana task, §5).

### 3.1 Availability and speed (D-007)

| Check | Pass when | Result |
|---|---|---|
| `stabilisation-check.sh` | "all checks passed" or warnings only | 12 Oct (Engineering): pages, API, headers and certificates pass; certificates valid to Jan 2027 or later; average 0.63 s. **Warning: careers app on an older release** (deploy the latest website zip to it). |
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
| Careers site ready for Gate 1 (Fri 30 Oct) | 15 roles published; owner tested the full flow end to end on 12 Oct (apply, acknowledgement, alert, reviewers, talent pool, certificate and verify); campaign links ready (`PIF-CAMPAIGN-PLAN.md`) | |
| Opening dates (query 13) | Opening day Mon 2 Nov, last day for Cohort 1 Sun 15 Nov; applications not open before Gate 1 | |
| Test applications (query 12) | Erased after testing: 0 left, or each one explained | |

### 3.4 Background jobs, email and backups

| Check | Pass when | Result |
|---|---|---|
| `purge-retention.log` | One line per day since it was set up; no "failed" | |
| Query 7: retention | Every column 0 | |
| Staff area → **Email** | "Sending is on"; nothing waiting for more than an hour; no latest error | |
| `send-mail.log` + queries 6 and 11 | Since the 12 Oct fix: no `failed` emails, nothing `pending` for more than an hour, emails sent every day there was activity. The 6–12 Oct gap and 2 given-up emails are incident 2 (§3.7) | |
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

### 3.7 Incidents since go-live

| # | When | What happened | Impact | Cause | Fix | Prevention |
|---|---|---|---|---|---|---|
| 1 | 12 Oct (found) | careers.paxofi.com/verify showed "Page not found"; applications failed with a generic error | Certificate checks and some applications failed until the careers app was updated | The release 25 website zip had not been deployed to the careers Node.js app | Deployed the same zip to `paxofi-careers-website` | Guide Step 4 item 6 and RB-19 say "both apps"; `stabilisation-check.sh` warns when the releases differ |
| 2 | 6–12 Oct | No emails sent: applicant acknowledgements, HR and enquiry alerts, password links | 22 emails delayed up to 6 days; 2 given up after 6 attempts | The site sent through `paxofi.com:465`. Since the Cloudflare change (D-024) that name points to Cloudflare's proxy, which carries web traffic only, so every connection timed out. `mail.paxofi.com` reaches the server but its certificate is for `paxofi.com` | 12 Oct: Email page (D-031) showed the cause; the owner switched the transport to the server's own mail function; 18 sent at once, the rest by the cron job | Email page shows queue and latest error; `stabilisation-check` and the weekly check look at it (RB-17); outbox errors are recorded (D-030) |

Decide for incident 2 whether the applicants and enquirers affected need a personal note (the 2 given-up emails).

## 4. Agenda (45 minutes)

1. Evidence walk-through, §2 and §3 (20 min). Go through the Watch and Action lines only.
2. Incidents and near misses since go-live (5 min).
3. PIF campaign: readiness for Gate 1 (Fri 30 Oct) and the opening on Mon 2 Nov, from `PIF-CAMPAIGN-PLAN.md` (5 min).
4. Decision: close the stabilisation period, or extend it with a dated action list (5 min).
5. Actions, owners and dates (5 min). Confirm the 30-day review on 1 Nov (CW-OPS2-004).
6. Anything for the change backlog (5 min). New work goes into Asana first (PRODUCT-BASELINE §13).

## 5. Outcome

**Decision:** ☐ Stabilisation closed — normal operations from 16 Oct  ☐ Extended to ___ with the actions below

| # | Action | Owner | Due | Asana |
|---|---|---|---|---|
| 1 | Restore test: restore last night's backup into a new empty database and check it (RB-18) | Operations | before 1 Nov | |
| 2 | Deploy `20261012-97674ca` to the API and both Node.js apps; `stabilisation-check.sh` shows the same release on both | Operations | 16 Oct | |
| 3 | | | | |

**Carry into the 30-day review (1 Nov):** a full month of UptimeRobot data against 99.5%; LCP; P1/P2 response times; enquiry reply times; the restore test.

Record the outcome in Asana (CW-OPS2-003 comment, then mark it complete) and in PKDMS.
