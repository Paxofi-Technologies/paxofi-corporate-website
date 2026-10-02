# User acceptance test plan — Version 1 (CW-UAT-001)

**Who tests:** the owner (or a Paxofi team member they name), on the live site after the release that includes this plan is deployed.
**Time:** about 45 minutes.
**Devices:** one desktop or laptop browser (Chrome, Edge or Firefox), one iPhone (Safari), one Android phone (Chrome).
**How to record:** for each scenario mark **Pass** or **Fail**. For a fail, note the page, the device, what you expected and what happened (a screenshot helps), and send it to engineering. Engineering logs it as an Asana defect under CW-UAT-004.

Automated tests already cover these flows in Chromium, Firefox and WebKit on every change. UAT confirms that real people on real devices see what they expect, and that the content is right.

## Entry criteria (CW-UAT-002)

- [ ] The release under test is deployed: `https://corporate.paxofi.com/release.txt` shows the version you were given.
- [ ] CI is green on `main` for that version (engineering confirms).
- [ ] The padlock shows on corporate.paxofi.com and api.paxofi.com (no "Not secure").
- [ ] UptimeRobot shows all monitors up.

## Scenarios

| # | Scenario | Steps | Expected | Desktop | iPhone | Android |
|---|---|---|---|---|---|---|
| U1 | First impression | Open https://corporate.paxofi.com | Home page loads within about 3 seconds, Paxofi branding (blue/navy), headline "Technology for a Brighter Tomorrow.", no errors, padlock shown | | | |
| U2 | Navigation | Use the menu to open About, Services, Products, Careers, then **Talk to us** | Each page opens; the current page is highlighted in the menu. On phones, the menu button opens and closes the menu | | | |
| U3 | Content accuracy | Read every page, including the footer | Company name, products, services, values, email address and wording are correct and approved; no spelling mistakes; no placeholder text | | | |
| U4 | Careers hand-off | Careers → **View opportunities** | career.paxofi.com opens. No application form on the corporate site | | | |
| U5 | Send an enquiry | Contact → fill in name, email, company, a message starting "UAT test" → **Send enquiry** | "Thanks — your enquiry has been received."; the form clears | | | |
| U6 | Enquiry arrives | phpMyAdmin → `paxoalhu_corporate` → `enquiries` → Browse (newest first) | The U5 enquiries are there with the right details | | – | – |
| U7 | Form mistakes | Contact → enter an email without "@" → Send | A message next to the email field asks for a valid address; nothing is sent | | | |
| U8 | Too many enquiries | Send 6 enquiries in a row from one device within 10 minutes | The 6th shows a message asking you to wait a few minutes | | – | – |
| U9 | Missing page | Open https://corporate.paxofi.com/xyz | A branded "Page not found." page with a way back home | | | |
| U10 | Privacy and terms | Footer → Privacy, Terms | The pages state the retention periods and that no cookies or tracking are used; you accept the wording | | – | – |
| U11 | Search/share preview | Paste https://corporate.paxofi.com into WhatsApp or LinkedIn (do not send) | A preview shows the Paxofi title and description | | | |
| U12 | Logo and icon | Look at the browser tab and the header/footer logo | The interim Paxofi "P" mark shows (D-004: replaced when the designer delivers) | | | |

After U5–U8: delete the "UAT test" enquiries in phpMyAdmin (tick → Delete), so they don't reach Business Development.

## Exit criteria (CW-UAT-003 → CW-UAT-006)

- All scenarios pass on all applicable devices, **or** every failure has an agreed fix or is accepted by the owner as a known issue.
- Defects are fixed and re-tested (CW-UAT-004). Severity: **blocker** (site or form unusable, wrong legal/company information) must be fixed before sign-off; **major** (wrong content, broken on one device) fixed before sign-off unless the owner accepts it; **minor** (cosmetic) can follow in the next release.
- The owner records sign-off ("UAT passed for release X") in the Asana task CW-UAT-003. That is the production readiness decision (CW-UAT-006/007) for the already-live site.

## After sign-off

CW-UAT-008 (production smoke test: guide Step 5), CW-UAT-009 (release closure), CW-OPS2-003 (stabilisation review after 1–2 weeks), CW-OPS2-004 (30-day review around 1 November 2026: uptime against D-007, enquiries received, incidents, dependency updates).
