import type { Metadata } from "next";
import Link from "next/link";
import { RECRUITMENT_EMAIL } from "@/lib/careers";
import { siteUrl } from "@/lib/site";

export const metadata: Metadata = {
  title: "Applicant privacy notice",
  description: "How Paxofi Technologies uses, protects and deletes the information you send when you apply on careers.paxofi.com.",
  alternates: { canonical: "/privacy" },
};

/** Applicant privacy notice (D-019). Version 2026-10-05: keep in step with ApplicationInput::PRIVACY_VERSION. */
export default function ApplicantPrivacy() {
  return (
    <>
      <section className="page-hero">
        <div className="container page-hero-inner">
          <span className="eyebrow">Applicant privacy notice</span>
          <h1>How we handle your application.</h1>
          <p className="lead">
            We ask only for what we need to assess you fairly, we let only the people running recruitment see it, and
            we delete it automatically after 12 months.
          </p>
        </div>
      </section>
      <section className="section">
        <div className="container prose">
          <p>
            This notice covers applications made on careers.paxofi.com to {"Paxofi Technologies LTD"} (“Paxofi”, “we”),
            including the Paxofi Innovation Fellowship. Version of 5 October 2026.
          </p>

          <h2>What we collect</h2>
          <ul>
            <li>your name, email address, optional phone number, and country and city;</li>
            <li>the role you apply for and the hours a week you can commit;</li>
            <li>your CV (if you upload one), portfolio and LinkedIn links, and what you tell us about your motivation and experience;</li>
            <li>your confirmation that you are 18 or older and that you have read this notice;</li>
            <li>if you tell us, how you heard about the role, and the campaign name in the link you followed (for example a LinkedIn post), so we know which channels reach good candidates;</li>
            <li>your IP address and browser user-agent, to protect the form against spam and abuse.</li>
          </ul>
          <p>
            We do not ask for your date of birth, gender, religion, ethnicity, health, financial details or any other
            sensitive information. Please leave such details out of your CV.
          </p>

          <h2>How we use it</h2>
          <p>
            Only to assess your application for the role you chose, to contact you about it and, if you are selected, to
            prepare your fellowship agreement and onboarding. Every applicant for a role is reviewed against the same
            published criteria and scorecard. We do not use automated decision-making, we do not sell your information,
            and we never use it for marketing.
          </p>

          <h2>Who can see it</h2>
          <p>
            Only Paxofi staff responsible for recruitment (human resources staff and administrators) and the people who
            interview you. They use a signed-in staff area protected by two-factor sign-in; viewing your CV is recorded.
            Emails about your application are sent from our own mail system. Your CV is stored on our own server, outside
            the public website, and is never published.
          </p>

          <h2>How long we keep it</h2>
          <ul>
            <li>Your application, CV, notes and scores are deleted automatically 12 months after the application closes (offer declined, not selected or withdrawn), or 12 months after its last update if it stays open.</li>
            <li>Your IP address and browser user-agent are removed after 90 days.</li>
            <li>A CV uploaded without completing the application is deleted after one day.</li>
            <li>Server backups are replaced within 14 days, so deleted information leaves them by then.</li>
          </ul>
          <p>If you join Paxofi as a Fellow, the information needed for your fellowship moves to your fellowship record, which has its own notice.</p>

          <h2>Your choices</h2>
          <p>
            You can ask to see, correct or delete your application, or withdraw it, at any time by emailing{" "}
            <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a> with your application reference. Withdrawing
            does not affect any future application.
          </p>

          <h2>More information</h2>
          <p>
            How the rest of our website handles information is described in the{" "}
            <a href={`${siteUrl()}/privacy`} rel="noopener">Paxofi privacy notice</a>. Back to the{" "}
            <Link href="/#roles">open roles</Link>.
          </p>
        </div>
      </section>
    </>
  );
}
