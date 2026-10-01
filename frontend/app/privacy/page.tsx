import type { Metadata } from "next";
import Link from "next/link";

export const metadata: Metadata = {
  title: "Privacy",
  description: "How Paxofi Technologies collects and uses information on this website, including contact enquiries.",
  alternates: { canonical: "/privacy" },
};

export default function Privacy() {
  return (
    <section className="section">
      <div className="container prose">
        <span className="eyebrow">PRIVACY</span>
        <h1>Privacy at Paxofi.</h1>
        <p className="hero-copy">
          We collect only the information needed to operate our services, respond to enquiries and improve our
          products. Version 1 uses privacy-minimal first-party analytics and avoids third-party tracking as an
          architectural dependency.
        </p>

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

        <h2>Questions and requests</h2>
        <p>
          To ask about, correct or delete information you have sent us, email{" "}
          <a href="mailto:hello@paxofi.com">hello@paxofi.com</a>.
        </p>
      </div>
    </section>
  );
}
