# PIF 2026 recruitment campaign — setup plan

**Prepared:** 12 Oct 2026 (Engineering). **Asana:** [LAUNCH] Careers site launch and recruitment campaign go-ahead (due Fri 30 Oct).
**Sources:** D-020 (campaign tracking), D-027 (engagement types and dates), D-028 and D-030 (opening and closing in the staff area), D-029 (reviewers, talent pool, certificates), RB-19, PIF Readiness baseline v2.1 and the Launch Decision Pack (PKDMS).

Everything the campaign needs is built and live, and the owner tested the full flow end to end on 12 Oct. What remains is setting the dates, preparing posts with tracked links, and agreeing who reviews.

## 1. Key dates (Lagos time)

| Date | What | Who |
|---|---|---|
| Fri 16 Oct | Stabilisation review: campaign readiness is agenda item 3 | Owner |
| By Fri 23 Oct | Dates set in the staff area (§2), reviewers named (§5), posts drafted (§4) | HR, Business Development |
| **Fri 30 Oct** | **Gate 1:** go or wait, using the checklist in §3. A test application on the day is part of it | Owner, HR, Engineering |
| **Mon 2 Nov** | **Applications open** (fallback Mon 9 Nov if Gate 1 says wait). Campaign posts go out | Business Development |
| Sun 15 Nov | Last day for Cohort 1. Later applications are labelled *Cohort 2* (talent pool, with consent) | — |
| Mon 7 Dec | Cohort 1 starts | — |

Review targets during the campaign: every new application moves out of *Applied* within 2 working days; the evidence review within 2 weeks (RB-19).

## 2. Staff-area settings — do them in this order

Today (12 Oct) the live settings are: opening day **Mon 12 Oct**, closed by **Close applications now** (last day Sun 11 Oct), no last day for Cohort 1. Applications are closed only because of *Close now*. **Do not click Reopen first:** with the opening day in the past, the form would open immediately, three weeks early.

Staff area → Recruitment → *Set when applications open* (administrators):

1. **Opening day:** Mon 2 Nov 2026. Save.
2. **Last day for Cohort 1:** Sun 15 Nov 2026. Save.
3. **Reopen applications.** This clears the "last day to apply". The careers site now says applications open on Monday 2 November.
4. Check https://careers.paxofi.com: every role says it opens on 2 November and the form is not shown.
5. **Staff test code:** create a new one for Gate 1 if the old one was shared widely. It is shown once; give it only to the people testing.
6. Leave **Last day to apply** empty unless you decide on a final close date (decision D1 in §6). If Gate 1 says wait, change the opening day to Mon 9 Nov; nothing else changes.

Evidence query 13 in `ops/stabilisation-evidence.sql` shows the saved values.

## 3. Gate 1 checklist (Fri 30 Oct)

| # | Check | How | Done |
|---|---|---|---|
| 1 | Both apps on the same, latest release | `bash ops/stabilisation-check.sh`: no "careers release differs" | |
| 2 | Email sends | Staff area → **Email**: "Sending is on", nothing waiting, no latest error; **Send me a test email** arrives | |
| 3 | Dates right | §2 step 4; query 13 | |
| 4 | Test application | Recruitment → *Test application* → choose a Fellow, an Intern and a Volunteer role; apply with your own address and a small PDF CV. The acknowledgement names the right engagement type; `RECRUITMENT_ALERT_TO` gets the alert | |
| 5 | Erase the tests | Each test application → **Erase application**; query 12 shows 0 | |
| 6 | Roles | 15 positions published and their text checked (hours, term, unpaid, 18+); no seat numbers or mentor names | |
| 7 | Corporate Careers page | Content → Page text → Careers: no override that contradicts D-027 | |
| 8 | Privacy notice | careers.paxofi.com privacy notice is version 2026-10-12 (DP-025 v1.2) | |
| 9 | Reviewers ready | §5: reviewers have Human Resources accounts with two-factor on; each knows the recusal step | |
| 10 | Campaign links | Each post has its own link from §4; open three of them and check the role page loads | |
| 11 | Monitoring | UptimeRobot has a careers.paxofi.com monitor; the report (Recruitment → report) opens | |
| 12 | Templates | Agreement and offer letter PDFs for Fellow, Intern and Volunteer ready to attach to *Selected* emails | |

Go if 1–10 pass. 11 and 12 can be finished in the first week.

## 4. Campaign links

Every post gets its own link, so the recruitment report shows which channels bring applicants (D-020). The careers site keeps the tags for the browser tab, so an applicant who looks around first is still counted.

- **Ready-made links:** `ops/pif-2026-campaign-links.csv` has 96 links: the careers home page and each of the 15 roles, for LinkedIn, X, Instagram, Facebook, WhatsApp and email. Open it in Excel or Google Sheets.
- **Format:** `https://careers.paxofi.com/roles/<role>?utm_source=<where>&utm_medium=<kind>&utm_campaign=pif-2026`
- **A new place** (a partner school, a community, a newsletter): change `utm_source` to a short lower-case name and `utm_medium` to `partner`, `community` or `newsletter`, for example `?utm_source=unilag&utm_medium=partner&utm_campaign=pif-2026`. Only lower-case letters, digits, `.`, `_` and `-` are kept.
- **Keep a list** of which link went where. Links can't be told apart in the report if two posts share one.

**What every post must say (D-027 public messaging rules):**

- The engagement type, by name: Fellow, Intern or Volunteer.
- Unpaid learning programme; not employment; no promise of pay or a job.
- Remote; 18 or older; the hours a week (Fellows and Interns at least 15, Volunteers at least 10).
- Applications open Mon 2 Nov; Cohort 1 for applications by Sun 15 Nov.
- Formal decisions come only by email from hr@paxofi.com.

## 5. Reviewing during the campaign

- **Reviewers:** administrators and Human Resources staff. Name them before Gate 1; each needs an account with the Human Resources role and two-factor on (RB-11).
- **Assign** up to 5 reviewers per application; once assigned, only they score. Anyone who knows a candidate uses **I have a conflict of interest** (D-029).
- **Daily** (10 minutes): Recruitment → filter *Applied* → move each to *Screening*. Staff area → **Email**: nothing waiting.
- **Weekly:** Recruitment → report: overdue applications first; which channels and links work. Share the counts (no names) with the owner.
- **Scorecards:** Fellows progress at 21/35 and are selected at 24/35; Interns 18/35 and 21/35; Volunteers have a short conversation, selected at 15/25 (RB-19).
- **After 15 Nov:** candidates not selected may be offered the talent pool, only with their consent (RB-19).

## 6. Decisions needed from the owner

| # | Decision | Suggested |
|---|---|---|
| D1 | Do applications close completely at some point, or stay open after 15 Nov for Cohort 2? | Stay open to Sun 29 Nov, labelled Cohort 2, then **Close applications now** |
| D2 | Review deadlines between 15 Nov and the 7 Dec start | Evidence review by Fri 20 Nov; interviews 23–27 Nov; decisions sent by Tue 1 Dec; agreements signed by Fri 4 Dec |
| D3 | Reviewers (names) and the number per application | Two per application for Fellows and Interns, one for Volunteers |
| D4 | Channels and partner schools | LinkedIn, X, Instagram, WhatsApp groups; partner schools listed in PKDMS with their own `utm_source` |
| D5 | Fallback trigger for Mon 9 Nov | Any Gate 1 check 1–10 failing on 30 Oct |
