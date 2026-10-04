import Link from "next/link";
import { PageHero } from "@/components/Sections";
import { pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Privacy",
  "How Paxofi Technologies collects and uses information on this website, including contact enquiries.",
  "/privacy",
);

export default function Privacy() {
  return (
    <>
      <PageHero
        eyebrow="Privacy"
        title="Privacy at Paxofi."
        intro="We collect only the information needed to answer your enquiries, keep this website secure and count visits without identifying anyone. This website uses no cookies for visitors, no advertising and no third-party tracking."
      />
      <section className="section">
        <div className="container prose">
          <h2>When you contact us</h2>
          <p>When you send an enquiry through our <Link href="/contact">contact form</Link>, we record:</p>
          <ul>
            <li>the name, email address, company and message you provide;</li>
            <li>your IP address and browser user-agent, to protect the form against spam and abuse;</li>
            <li>a technical request reference, so we can trace and resolve problems with a submission.</li>
          </ul>
          <p>
            We use this information only to respond to your enquiry and to keep the service secure. Only Paxofi staff
            who handle enquiries can read it, through a signed-in staff area that records who viewed or updated each
            enquiry. A copy of your enquiry is also emailed to the Paxofi mailbox of the staff who answer it; those
            emails are kept in our own email system for 30 days. We do not sell it or use it for advertising.
          </p>

          <h2>How long we keep it</h2>
          <ul>
            <li>Enquiries are deleted 24 months after we receive them.</li>
            <li>The IP address and browser user-agent recorded with an enquiry are removed after 90 days.</li>
            <li>Security and audit records are deleted after 24 months.</li>
          </ul>
          <p>You can ask us to delete an enquiry sooner at any time.</p>

          <h2>Cookies and tracking</h2>
          <p>
            This website sets no cookies for visitors and uses no advertising or third-party tracking. Fonts and images
            are served from our own servers. Paxofi staff who sign in to the staff area receive one strictly necessary
            session cookie, which ends when they sign out or after 8 hours.
          </p>

          <h2>Counting visits</h2>
          <p>
            To understand which pages are useful, our own server counts page views. Nothing is stored on your device and
            no other company is involved. For each page view we use only:
          </p>
          <ul>
            <li>which page of this website you opened;</li>
            <li>the website you came from, on the first page of your visit (its domain only, for example google.com);</li>
            <li>whether your screen is a phone, tablet or desktop size.</li>
          </ul>
          <p>
            To count how many different people visit each day, your IP address and browser details are combined with a
            random code that changes every day and turned into a one-way code that cannot be reversed. That code is
            deleted at the end of the day, so we cannot recognise you on another day or link a visit to you. We keep
            only daily totals, for 25 months. If your browser sends a Do Not Track or Global Privacy Control signal,
            your visits are not counted at all.
          </p>

          <h2>Job applications</h2>
          <p>
            This website does not collect job applications. Recruitment is handled on{" "}
            <a href="https://careers.paxofi.com" rel="noopener">careers.paxofi.com</a>, which has its own privacy terms.
          </p>

          <h2>Questions and requests</h2>
          <p>
            To ask about, correct or delete information you have sent us, email{" "}
            <a href="mailto:hello@paxofi.com">hello@paxofi.com</a>.
          </p>
        </div>
      </section>
    </>
  );
}
