import type { Metadata } from "next";
import { BadgeCheck, ShieldAlert } from "lucide-react";
import { RECRUITMENT_EMAIL } from "@/lib/careers";
import { checkCertificate, formatCertificateDate } from "@/lib/certificates";
import { resolveApiBase } from "@/lib/contact";

// Each visit asks the API, so a revoked certificate stops showing as valid at once.
export const dynamic = "force-dynamic";

// A code is private to whoever holds it: the page is not listed or indexed.
export const metadata: Metadata = {
  title: "Check a certificate",
  description: "Check that a Paxofi Innovation Fellowship participation certificate is genuine, using the code printed on it.",
  alternates: { canonical: "/verify" },
  robots: { index: false, follow: false },
};

/** careers.paxofi.com/verify (D-029): a plain form that works without JavaScript. */
export default async function VerifyCertificate({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const raw = (await searchParams).code;
  const code = (Array.isArray(raw) ? raw[0] : raw)?.slice(0, 60).trim() ?? "";
  const result = code === "" ? null : await checkCertificate(resolveApiBase(process.env), code);
  return (
    <>
      <section className="page-hero">
        <div className="container page-hero-inner">
          <span className="eyebrow">Certificates</span>
          <h1>Check a certificate.</h1>
          <p className="lead">
            Enter the code printed on a Paxofi Innovation Fellowship participation certificate to confirm that it is genuine.
          </p>
        </div>
      </section>
      <section className="section">
        <div className="container prose">
          <form method="get" action="/verify" className="contact-form" role="search" aria-label="Check a certificate">
            <label>
              Certificate code
              <input name="code" type="text" autoComplete="off" autoCapitalize="characters" spellCheck={false} maxLength={60} placeholder="PIF-XXXX-XXXX-XXXX" defaultValue={code} required aria-describedby="code-hint" />
            </label>
            <p id="code-hint" className="form-note">The code starts with PIF and has twelve letters and numbers. Spaces and dashes do not matter.</p>
            <button className="button button--primary" type="submit">Check certificate</button>
          </form>

          <div aria-live="polite">
            {result?.kind === "valid" && (
              <div className="notice" role="status">
                <h2><BadgeCheck size={22} aria-hidden="true" /> This certificate is genuine.</h2>
                <dl className="admin-dl">
                  <dt>Awarded to</dt><dd>{result.certificate.holderName}</dd>
                  <dt>Position</dt><dd>{result.certificate.roleTitle} (PIF {result.certificate.engagementLabel})</dd>
                  {result.certificate.cohort && (<><dt>Cohort</dt><dd>{result.certificate.cohort}</dd></>)}
                  {result.certificate.startedOn && (<><dt>Started</dt><dd>{formatCertificateDate(result.certificate.startedOn)}</dd></>)}
                  <dt>Completed</dt><dd>{formatCertificateDate(result.certificate.completedOn)}</dd>
                  <dt>Issued</dt><dd>{formatCertificateDate(result.certificate.issuedOn)}</dd>
                  <dt>Code</dt><dd>{result.certificate.code}</dd>
                </dl>
                <p>{result.certificate.message}</p>
              </div>
            )}
            {result?.kind === "revoked" && (
              <div className="notice" role="status">
                <h2><ShieldAlert size={22} aria-hidden="true" /> This certificate is no longer valid.</h2>
                <p>{result.message || "It was withdrawn by Paxofi Technologies."} Questions? Email <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>.</p>
              </div>
            )}
            {result?.kind === "not_found" && (
              <p className="form-status" data-state="error" role="alert">
                We could not find a certificate with that code. Check the code printed on the certificate and try again, or email <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>.
              </p>
            )}
            {result?.kind === "error" && (
              <p className="form-status" data-state="error" role="alert">
                We could not check the certificate just now. Please try again in a minute, or email <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a>.
              </p>
            )}
          </div>

          <p className="admin-help">
            A certificate records taking part in the Paxofi Innovation Fellowship, an unpaid learning and development programme. It is not a record of employment.
            This page shows only what is printed on the certificate. Please do not share a code that is not yours.
          </p>
        </div>
      </section>
    </>
  );
}
