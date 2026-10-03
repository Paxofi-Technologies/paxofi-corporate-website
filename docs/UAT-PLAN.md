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
| U7 | Form mistakes | Contact → enter an email without "@" → Send | The browser or the form points to the email field and asks for a valid address; nothing is sent | | | |
| U8 | Too many enquiries | On the desktop, keep sending enquiries (at most 6) | Within 6 sends, a message asks you to wait a few minutes (the limit is 5 per 10 minutes per email or network, so the U5 enquiries count) | | – | – |
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

## Results — release 20261002-7a821c7 (2 Oct 2026)

Tester: Samuel Kehinde Adeniji (owner and founder), on desktop, iPhone and Android. U1–U4, U7, U9, U10 and U12 passed on all devices; content and privacy/terms wording approved. **U5 failed (DEF-001)**, and U6 and U8 were blocked by it. **U11 failed (DEF-002)**: a link shared on LinkedIn showed only the title and domain, with no image.

**DEF-001 (blocker):** the site was opened over `http://` ("Not secure"). The API accepts enquiries only from `https://corporate.paxofi.com` (CORS), so the browser blocked the request. This is hosting configuration: a valid AutoSSL certificate plus **Force HTTPS Redirect** (guide Step 0, RB-4/RB-6). Retest U5, U6 and U8 over HTTPS to complete sign-off.

**DEF-002 (major):** the site had no `og:image`. Fixed with a 1200×630 branded share image on every page and the large Twitter/X card. After deploying, refresh LinkedIn's cache at https://www.linkedin.com/post-inspector/ and retest U11.

## Retest and sign-off — release 20261002-747d5a8 (2 Oct 2026)

| Item | Result |
|---|---|
| DEF-001 HTTPS | Resolved: AutoSSL certificate issued; padlock on `https://corporate.paxofi.com` |
| U5 Send an enquiry | Pass (over HTTPS) |
| U6 Enquiry arrives | Pass: the test enquiries were in `enquiries` with name, email, company, IP, user-agent and request reference; the owner deleted them afterwards |
| U8 Rate limit | Pass: "You've sent several enquiries in a short time…" |
| U11 Share preview | LinkedIn: pass (branded image). Facebook: **known issue KI-001**, accepted by the owner |

**KI-001 (accepted known issue):** Facebook's crawler gets HTTP 403 from the shared host's bot protection (Imunify360/WebShield) before the request reaches the website. ModSecurity was ruled out and the error log is empty. The website itself is correct: LinkedIn previews work. The owner deferred this to the planned move to a VPS, where the firewall is under Paxofi's control; retest with the Facebook Sharing Debugger after the move.

**Sign-off:** UAT passed for release `20261002-747d5a8`, signed off by Samuel Kehinde Adeniji (owner and founder), 2 Oct 2026. All 12 scenarios pass on desktop, iPhone and Android, with KI-001 accepted.

## Phase 2.1 — staff area (CW-UAT, D-009)

Run after deploying the first release that includes `/admin` (from `20261003-4a341c6`) and completing guide Step 7. About 20 minutes on a desktop browser; S7 also on a phone.

| # | Scenario | Steps | Expected | Result |
|---|---|---|---|---|
| S1 | First administrator | Guide Step 7: set `ADMIN_SETUP_TOKEN`, open `/admin/setup`, create your account, remove the token | You land in **Enquiries**; afterwards `/admin/setup` says setup is not available | |
| S2 | Wrong password | Sign out, sign in with a wrong password | "The email or password is incorrect."; nothing else is revealed | |
| S3 | Inbox | Send a "UAT test" enquiry from `/contact`, then open **Enquiries** | It appears under **New** with name, email and the start of the message | |
| S4 | Work an enquiry | Open it → **Reply by email** opens your mail app addressed to the sender → set status **Replied** | Status saved; the enquiry moves from **New** to **Replied** | |
| S5 | Team member | **Users** → **Add a user** with role *Business Development* and a temporary password; sign in as them in a private window | They see **Enquiries** and **My account** only, not **Users** or **Audit log** | |
| S6 | Leaver | As administrator, set that user to **Disabled** | Their private window is sent back to sign in on the next click; they cannot sign in again | |
| S7 | Phone | Sign in on your phone | Pages fit the screen; the inbox scrolls sideways only inside the table | |
| S8 | Audit | **Audit log** | Shows your sign-ins, the status change and the user changes, with names and times | |
| S9 | Password | **My account** → change your password; sign in again with it | Works; the old password no longer does | |

Afterwards mark the "UAT test" enquiry **Spam** or **Closed** (or delete it in phpMyAdmin) and disable or keep the test user. Record sign-off ("Phase 2.1 UAT passed for release X") in Asana.

### Results — release 20261003-4a341c6 (3 Oct 2026)

**Phase 2.1 UAT passed**, signed off by Samuel Kehinde Adeniji (owner and founder). S1–S9 passed on the live site. Notes:

- S1: `/admin/setup` first showed "Setup is not available". The page shows the same message when it cannot reach the API, which was the case during deployment. Once the API was in place, setup worked, and `GET /api/v1/admin/setup` now reports `"available":false`, as intended. Follow-up for the next release: show a distinct "could not reach the service" message on that page.
- S5/S6: Business Development user (Temitope Koleosho) sees only **Enquiries** and **My account**. Once disabled, the account gets "The email or password is incorrect.", the same message as a wrong password, by design (the sign-in page does not reveal which accounts exist or are disabled). The attempt is recorded in the Audit log.

## Phase 2.2 — two-factor sign-in (D-010)

Run after deploying the release that includes two-factor (from P2.2) and completing guide **Step 8**. About 20 minutes; you need your phone with an authenticator app.

| # | Scenario | Steps | Expected | Result |
|---|---|---|---|---|
| T1 | Enforced for administrators | Sign in as an administrator | You go straight to **Two-factor sign-in** with "Administrators must use two-factor sign-in"; only **My account** is in the menu | |
| T2 | Set up | **Set up two-factor sign-in** → scan the QR with the app → enter the code | Recovery codes appear; **Done** works only after ticking "I have saved my recovery codes"; the full menu returns | |
| T3 | Sign in with a code | Sign out, sign in with your password | "Enter your code"; the current app code signs you in. A wrong code says it is not valid | |
| T4 | Recovery code | Sign out, sign in, enter one recovery code instead | Signed in; the same recovery code does not work a second time; *Two-factor sign-in* shows 9 codes left | |
| T5 | Optional for Business Development | Sign in as a Business Development user | They reach **Enquiries** without being forced; *My account* offers **Set up two-factor sign-in** | |
| T6 | Lost phone | As administrator: *Users* → **Edit** on a person with two-factor on → **Reset two-factor** | Confirmation shown; that person is signed out and signs in with their password only | |
| T7 | Audit | **Audit log** | Shows the set-up, the recovery code use and the reset, with names and times | |

### Results — release 20261003-f6c67fe (3 Oct 2026)

**Phase 2.2 UAT passed**, signed off by Samuel Kehinde Adeniji (owner and founder). T1–T7 passed on the live site after guide Step 8 (`MFA_ENCRYPTION_KEY` set; administrator authenticator set up and recovery codes saved).

## Phase 2.3 — editing products and services (D-011)

Run after deploying the release that includes **Content** in the staff area. About 15 minutes. Use a test wording you can recognise, and restore the original at the end.

| # | Scenario | Steps | Expected | Result |
|---|---|---|---|---|
| C1 | Nothing changed by the upgrade | Open /products and /services | Same products and services and wording as before the release (6 services) | |
| C2 | Draft stays private | **Content** → Paxofi Pay → change the summary → **Save draft**; open /products in a private window | The preview shows the new wording; the website still shows the old wording | |
| C3 | Publish | Back in the editor → **Publish**; reload /products | The new wording is on the website and on the home page | |
| C4 | Undo | **Earlier versions** → restore the version before your change → **Publish** | The original wording is back on the website | |
| C5 | Hide and show | **Hide from website** on a service; reload /services; then **Show on website** | It disappears, then comes back in the same place | |
| C6 | Business Development | Sign in as a Business Development user → **Content** → change a summary → **Save draft** | There is no **Publish** button; "An administrator will publish it" | |
| C7 | Audit | **Audit log** | Shows the draft, publish, restore and hide/show actions with names and times | |

### Results — release 20261003-ec0b070 (3 Oct 2026)

**Phase 2.3 UAT passed**, signed off by Samuel Kehinde Adeniji (owner and founder): "I have deployed and everything works perfectly fine". Guide Steps 1–5 applied (database upgrade with migration 009); no new server settings.

## Phase 2.4 — pictures and documents (D-012)

Run after deploying the release with **Media** and completing guide Step 10. About 20 minutes. Have a photo from a phone (JPEG), a PDF and a Word document ready.

| # | Scenario | Steps | Expected | Result |
|---|---|---|---|---|
| M1 | Uploads are set up | **Media** | No "not set up" or "server accepts files up to" warning | |
| M2 | Upload a picture | Choose the phone photo → enter a description → **Upload** | It appears upright in the list with its size; **Open** shows it | |
| M3 | Description is required | Choose a picture, leave the description empty → **Upload** | "Describe the picture in a few words." Nothing is uploaded | |
| M4 | Upload documents | Upload the PDF (title empty), then the Word file with a title | Both listed; the PDF's title is its file name; **Open** downloads the file | |
| M5 | Unsuitable files refused | Try an `.exe`, `.zip` or `.svg` file | A clear message saying which files are accepted | |
| M6 | Picture and brochure on the website | **Content** → Paxofi Pay → choose the picture and the PDF → **Publish**; open /products | The Paxofi Pay card shows the picture and a "Paxofi Pay brochure (PDF, … KB)" link that downloads the file. The home page keeps its compact cards with icons. | |
| M7 | Files in use are protected | **Media** → the picture | **Delete** is unavailable and says it is used by Paxofi Pay | |
| M8 | Business Development | Sign in as Business Development → **Media** | Can upload; no **Delete** button | |
| M9 | Clean up and audit | Remove the test picture and PDF from Paxofi Pay → **Publish** → **Delete** them in **Media**; **Audit log** | They are gone from the website; the audit log shows `media.uploaded`, `media.deleted` with names and times | |

Record sign-off ("Phase 2.4 UAT passed for release X") in Asana.

### Results — release 20261003-a95b522 (3 Oct 2026)

**Phase 2.4 UAT passed**, signed off by Samuel Kehinde Adeniji (owner and founder): "deployed and everything runs fine". Guide Steps 1–5 and Step 10 (media folder and `MEDIA_STORAGE_PATH`) applied.
