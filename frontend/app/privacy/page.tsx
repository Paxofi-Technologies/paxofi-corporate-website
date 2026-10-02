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
        intro="We collect only the information needed to operate our services, respond to enquiries and improve our products. Version 1 uses privacy-minimal first-party analytics and avoids third-party tracking as an architectural dependency."
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
            We use this information only to respond to your enquiry and to keep the service secure. We do not sell it
            or use it for advertising.
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
