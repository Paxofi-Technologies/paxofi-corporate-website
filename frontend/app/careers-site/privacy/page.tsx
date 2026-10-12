import type { Metadata } from "next";
import Link from "next/link";
import { RECRUITMENT_EMAIL } from "@/lib/careers";
import { siteUrl } from "@/lib/site";

export const metadata: Metadata = {
  title: "Applicant privacy notice",
  description: "How Paxofi Technologies uses, protects and deletes the information you send when you apply on careers.paxofi.com.",
  alternates: { canonical: "/privacy" },
};

/** Applicant privacy notice (D-019, D-027), aligned with the Paxofi Data Privacy Notice PTMS-APPB-DP-025 v1.1. Version 2026-10-12: keep in step with ApplicationInput::PRIVACY_VERSION. */
export default function ApplicantPrivacy() {
  return (
    <>
      <section className="page-hero">
        <div className="container page-hero-inner">
          <span className="eyebrow">Applicant privacy notice</span>
          <h1>How we handle your application.</h1>
          <p className="lead">
            We ask only for what we need to assess you fairly, we let only the people running recruitment see it, a
            person makes every decision, and we delete unsuccessful applications after 12 months.
          </p>
        </div>
      </section>
      <section className="section">
        <div className="container prose">
          <p>
            This notice covers applications made on careers.paxofi.com to {"Paxofi Technologies LTD"} (“Paxofi”, “we”),
            including the Paxofi Innovation Fellowship as a Fellow, Intern or Volunteer. It follows the Paxofi Data
            Privacy Notice and the Nigeria Data Protection Act 2023. Version of 12 October 2026.
          </p>

          <h2>What we collect</h2>
          <ul>
            <li>your name, email address, optional phone number, and country and city;</li>
            <li>the role and engagement type (Fellow, Intern or Volunteer) you apply for, and the hours a week you can commit;</li>
            <li>your CV (if you upload one), portfolio and LinkedIn links, and what you tell us about your motivation and experience;</li>
            <li>your confirmation that you are 18 or older and that you have read this notice;</li>
            <li>if you tell us, how you heard about the role, and the campaign name in the link you followed (for example a LinkedIn post), so we know which channels reach good candidates;</li>
            <li>your IP address and browser user-agent, to protect the form against spam and abuse;</li>
            <li>later, if you progress: screening scores, assessment work, interview notes and decisions.</li>
          </ul>
          <p>
            We do not ask for your date of birth, gender, religion, ethnicity, political views, health or financial
            details for selection. Please leave such details out of your CV. If you need an adjustment, for example for
            an assessment because of a disability, you may tell us at{" "}
            <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>; we use that only to make the adjustment.
          </p>

          <h2>How we use it, and why</h2>
          <ul>
            <li>To assess your application, contact you and, if you are selected, prepare your agreement and onboarding. This is taking steps at your request before an agreement, and our legitimate interest in selecting fairly.</li>
            <li>To protect the form and our systems from abuse. This is our legitimate interest.</li>
            <li>To keep your application in our talent pool for a later cohort. This is only with your consent, which we ask for separately, and you can withdraw it at any time.</li>
          </ul>
          <p>
            Every applicant for a role is reviewed against the same published criteria and scorecard. A person reviews
            every decision: we never select, reject or engage anyone by automated means alone. We do not sell your
            information, and we never use it for marketing.
          </p>

          <h2>Who can see it</h2>
          <p>
            Only Paxofi staff responsible for recruitment (human resources staff and administrators) and the people who
            interview you. They use a signed-in staff area protected by two-factor sign-in; viewing your CV is recorded.
            Emails about your application are sent from our own mail system. Your CV is stored on our own server, outside
            the public website, and is never published. Our server is reached through Cloudflare, a network provider
            that protects it against attacks and handles each request only to deliver it to us securely.
          </p>
          <p>
            We also use service providers who act on our instructions, for example for email, our record keeping
            (Notion, ClickUp, OrangeHRM), team collaboration after you join (Slack, GitHub, Figma) and our website host.
            Some of them store data outside Nigeria; where that happens we rely on the safeguards the Nigeria Data
            Protection Act allows, such as the provider&apos;s contractual and security commitments. We share
            information with professional advisers, auditors, regulators and authorities only where the law requires it.
          </p>

          <h2>How long we keep it</h2>
          <ul>
            <li>If you are not selected, withdraw or decline an offer: your application, CV, notes and scores are deleted automatically 12 months after the application closes, or 12 months after its last update if it stays open. We keep it longer only if you join the talent pool by consent.</li>
            <li>If you join as a Fellow, Intern or Volunteer: your record is kept as a programme record after your engagement ends and reviewed at least every 6 years. It is not deleted automatically, and you can still ask us to correct or delete it. Certificate verification details (your name, certificate number, programme and dates) are kept for as long as we offer certificate verification.</li>
            <li>Your IP address and browser user-agent are removed after 90 days.</li>
            <li>A CV uploaded without completing the application is deleted after one day.</li>
            <li>Server backups are replaced within 14 days, so deleted information leaves them by then.</li>
          </ul>

          <h2>Your rights</h2>
          <p>
            You can ask to see, correct or delete your information, restrict or object to how we use it, receive a
            portable copy, withdraw your application, and withdraw any consent you gave. Email{" "}
            <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a> with your application reference and the word
            “Privacy” in the subject. We reply within 30 days. Withdrawing does not affect any future application. You may
            also complain to the Nigeria Data Protection Commission.
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
