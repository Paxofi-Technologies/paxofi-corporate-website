"use client";

import Link from "next/link";
import { FormEvent, useRef, useState } from "react";
import { CV_ACCEPT, HOURS_OPTIONS, RECRUITMENT_EMAIL, applyUrl, checkCvFile, cvUploadUrl, describeApplicationFailure } from "@/lib/careers";

type Props = { apiBase?: string; slug: string; roleTitle: string; evidenceHint: string };
type Status = "idle" | "uploading" | "sending" | "error";

const ORDER = ["full_name", "email", "phone", "location", "hours_per_week", "cv", "portfolio_url", "linkedin_url", "motivation", "experience", "age_confirmed", "privacy_consent"];

/** The application form: the CV (optional) goes first, then the details claim it (D-019). */
export default function ApplicationForm({ apiBase, slug, roleTitle, evidenceHint }: Props) {
  const [status, setStatus] = useState<Status>("idle");
  const [message, setMessage] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [receipt, setReceipt] = useState<{ reference: string; email: string } | null>(null);
  const statusRef = useRef<HTMLParagraphElement>(null);
  const doneRef = useRef<HTMLHeadingElement>(null);

  function fail(form: HTMLFormElement, text: string, fields: Record<string, string>) {
    setErrors(fields);
    setStatus("error");
    setMessage(text);
    const first = ORDER.find((key) => fields[key]);
    const element = first ? (form.elements.namedItem(first) as HTMLElement | null) : null;
    (element ?? statusRef.current)?.focus();
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    setErrors({});
    setMessage("");

    let cvToken: string | undefined;
    const file = data.get("cv");
    if (file instanceof File && file.size > 0) {
      const problem = checkCvFile(file.name, file.size);
      if (problem) return fail(form, problem, { cv: problem });
      setStatus("uploading");
      try {
        const response = await fetch(cvUploadUrl(apiBase, file.name), {
          method: "POST",
          headers: { Accept: "application/json", "Content-Type": "application/octet-stream" },
          body: file,
        });
        const payload: unknown = await response.json().catch(() => null);
        if (!response.ok) {
          const failure = describeApplicationFailure(response.status, payload);
          return fail(form, failure.message, Object.keys(failure.fields).length ? failure.fields : { cv: failure.message });
        }
        cvToken = (payload as { data?: { token?: string } } | null)?.data?.token;
      } catch {
        return fail(form, `Your CV could not be uploaded. Check your connection and try again, or add a portfolio or LinkedIn link instead.`, {});
      }
    }

    setStatus("sending");
    const text = (key: string) => String(data.get(key) ?? "").trim();
    const body = {
      full_name: text("full_name"),
      email: text("email"),
      phone: text("phone"),
      location: text("location"),
      hours_per_week: Number(text("hours_per_week")) || 0,
      portfolio_url: text("portfolio_url"),
      linkedin_url: text("linkedin_url"),
      motivation: text("motivation"),
      experience: text("experience"),
      cv_token: cvToken ?? "",
      age_confirmed: data.get("age_confirmed") === "yes",
      privacy_consent: data.get("privacy_consent") === "yes",
      website: text("website"),
    };
    try {
      const response = await fetch(applyUrl(apiBase, slug), {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const payload: unknown = await response.json().catch(() => null);
      if (!response.ok) {
        const failure = describeApplicationFailure(response.status, payload);
        return fail(form, failure.message, failure.fields);
      }
      const reference = (payload as { data?: { reference?: string } } | null)?.data?.reference ?? "";
      setReceipt({ reference, email: body.email });
      setStatus("idle");
      requestAnimationFrame(() => doneRef.current?.focus());
    } catch {
      fail(form, describeApplicationFailure(0, null).message, {});
    }
  }

  if (receipt) {
    return (
      <div className="application-done" role="status">
        <h3 ref={doneRef} tabIndex={-1}>Application received</h3>
        <p>Thank you for applying for <strong>{roleTitle}</strong>. Your reference is <strong className="application-reference">{receipt.reference}</strong>.</p>
        <p>We have emailed a confirmation to {receipt.email}. Every applicant hears back by email; screening usually takes about two weeks. Please also check your spam folder.</p>
        <p>Questions? Email <a href={`mailto:${RECRUITMENT_EMAIL}`}>{RECRUITMENT_EMAIL}</a> with your reference.</p>
        <p><Link href="/#roles">See the other open roles</Link></p>
      </div>
    );
  }

  const busy = status === "uploading" || status === "sending";
  const error = (key: string) => errors[key];
  const describedBy = (key: string, hint?: boolean) => [hint ? `${key}-hint` : "", error(key) ? `${key}-error` : ""].filter(Boolean).join(" ") || undefined;
  const invalid = (key: string) => (error(key) ? true : undefined);
  const errorText = (key: string) => error(key) && <span id={`${key}-error`} className="field-error">{error(key)}</span>;

  return (
    <form className="contact-form application-form" method="post" onSubmit={submit} noValidate>
      <noscript>
        <p className="form-status" data-state="error">Applying needs JavaScript. You can also email your CV to {RECRUITMENT_EMAIL}, naming the role.</p>
      </noscript>

      <fieldset>
        <legend>About you</legend>
        <label>Full name<input name="full_name" autoComplete="name" maxLength={160} required aria-invalid={invalid("full_name")} aria-describedby={describedBy("full_name")} />{errorText("full_name")}</label>
        <label>Email<input name="email" type="email" autoComplete="email" maxLength={255} required aria-invalid={invalid("email")} aria-describedby={describedBy("email")} />{errorText("email")}</label>
        <label>Phone (optional)<input name="phone" type="tel" autoComplete="tel" maxLength={20} aria-invalid={invalid("phone")} aria-describedby={describedBy("phone")} />{errorText("phone")}</label>
        <label>Country and city<input name="location" autoComplete="address-level1" maxLength={120} required placeholder="Lagos, Nigeria" aria-invalid={invalid("location")} aria-describedby={describedBy("location")} />{errorText("location")}</label>
        <label>
          Hours a week you can commit
          <select name="hours_per_week" required defaultValue="20" aria-invalid={invalid("hours_per_week")} aria-describedby={describedBy("hours_per_week", true)}>
            {HOURS_OPTIONS.map((hours) => <option key={hours} value={hours}>{hours === 40 ? "40 or more" : hours} hours</option>)}
          </select>
          <span id="hours_per_week-hint" className="form-note">The minimum is 15 hours a week; 20 is recommended.</span>
          {errorText("hours_per_week")}
        </label>
      </fieldset>

      <fieldset>
        <legend>Your experience</legend>
        <p className="form-note">Add your CV, a portfolio or your LinkedIn profile: at least one, ideally all three. {evidenceHint}</p>
        <label>
          CV (optional, PDF or Word, up to 5 MB)
          <input name="cv" type="file" accept={CV_ACCEPT} aria-invalid={invalid("cv")} aria-describedby={describedBy("cv")} />
          {errorText("cv")}
        </label>
        <label>Portfolio, GitHub or work samples (optional)<input name="portfolio_url" type="url" inputMode="url" maxLength={500} placeholder="https://" aria-invalid={invalid("portfolio_url")} aria-describedby={describedBy("portfolio_url")} />{errorText("portfolio_url")}</label>
        <label>LinkedIn profile (optional)<input name="linkedin_url" type="url" inputMode="url" maxLength={500} placeholder="https://www.linkedin.com/in/" aria-invalid={invalid("linkedin_url")} aria-describedby={describedBy("linkedin_url")} />{errorText("linkedin_url")}</label>
        <label>
          Why do you want this role?
          <textarea name="motivation" rows={5} minLength={50} maxLength={3000} required aria-invalid={invalid("motivation")} aria-describedby={describedBy("motivation", true)} />
          <span id="motivation-hint" className="form-note">50 to 3,000 characters.</span>
          {errorText("motivation")}
        </label>
        <label>
          Your relevant experience or evidence
          <textarea name="experience" rows={5} minLength={50} maxLength={3000} required aria-invalid={invalid("experience")} aria-describedby={describedBy("experience", true)} />
          <span id="experience-hint" className="form-note">Projects, coursework, volunteering or jobs that show you can do this role. 50 to 3,000 characters.</span>
          {errorText("experience")}
        </label>
      </fieldset>

      <fieldset>
        <legend>Confirm</legend>
        <label className="check-field">
          <input name="age_confirmed" type="checkbox" value="yes" required aria-invalid={invalid("age_confirmed")} aria-describedby={describedBy("age_confirmed")} />
          <span>I am 18 or older.</span>
        </label>
        {errorText("age_confirmed")}
        <label className="check-field">
          <input name="privacy_consent" type="checkbox" value="yes" required aria-invalid={invalid("privacy_consent")} aria-describedby={describedBy("privacy_consent")} />
          <span>I have read the <Link href="/privacy">applicant privacy notice</Link> and agree that Paxofi uses my application to assess me for this role. It is kept for up to 12 months.</span>
        </label>
        {errorText("privacy_consent")}
      </fieldset>

      {/* Honeypot: hidden from people and assistive tech; bots that fill it are discarded server-side. */}
      <div className="hp-field" aria-hidden="true">
        <label>Website<input name="website" tabIndex={-1} autoComplete="off" defaultValue="" /></label>
      </div>

      <button className="button button--primary" type="submit" disabled={busy}>
        {status === "uploading" ? "Uploading your CV…" : status === "sending" ? "Sending…" : "Submit application"}
      </button>
      <p ref={statusRef} tabIndex={-1} className="form-status" role="status" aria-live="polite" data-state={status}>{message}</p>
    </form>
  );
}
