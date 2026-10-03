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
        intro="We collect only the information needed to answer your enquiries and keep this website secure. This website does not use analytics, advertising or tracking cookies."
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
            enquiry. We do not sell it or use it for advertising.
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
            This website sets no cookies for visitors and uses no analytics, advertising or third-party tracking. Fonts
            and images are served from our own servers. Paxofi staff who sign in to the staff area receive one strictly
            necessary session cookie, which ends when they sign out or after 8 hours.
          </p>

          <h2>Job applications</h2>
          <p>
            This website does not collect job applications. Recruitment is handled on{" "}
            <a href="https://career.paxofi.com" rel="noopener">career.paxofi.com</a>, which has its own privacy terms.
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
