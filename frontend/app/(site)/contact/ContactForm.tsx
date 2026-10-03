"use client";

import Link from "next/link";
import { FormEvent, useRef, useState } from "react";
import { ContactFieldErrors, describeFailure } from "@/lib/contact";

type Props = { endpoint: string };
type Status = "idle" | "sending" | "success" | "error";

const FIELDS = [
  { name: "name", label: "Name", autoComplete: "name", maxLength: 160, required: true },
  { name: "email", label: "Email", type: "email", autoComplete: "email", maxLength: 255, required: true },
  { name: "company", label: "Company (optional)", autoComplete: "organization", maxLength: 255, required: false },
] as const;

export default function ContactForm({ endpoint }: Props) {
  const [status, setStatus] = useState<Status>("idle");
  const [message, setMessage] = useState("");
  const [fieldErrors, setFieldErrors] = useState<ContactFieldErrors>({});
  const statusRef = useRef<HTMLParagraphElement>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setStatus("sending");
    setMessage("");
    setFieldErrors({});

    const form = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form).entries());

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const failure = describeFailure(response.status, await response.json().catch(() => null));
        setFieldErrors(failure.fields);
        setStatus("error");
        setMessage(failure.message);
        focusFirstInvalid(form, failure.fields);
        return;
      }

      form.reset();
      setStatus("success");
      setMessage("Thanks — your enquiry has been received. We'll get back to you.");
      statusRef.current?.focus();
    } catch {
      setStatus("error");
      setMessage(describeFailure(0, null).message);
    }
  }

  return (
    // method="post": if the form is submitted before the script loads, the
    // details go in the request body, never in the URL or server logs.
    <form className="contact-form" method="post" onSubmit={submit}>
      <noscript>
        <p className="form-status" data-state="error">
          Sending this form needs JavaScript. You can also email us at hello@paxofi.com.
        </p>
      </noscript>
      {FIELDS.map((field) => (
        <Field key={field.name} {...field} error={fieldErrors[field.name]} />
      ))}
      <label>
        How can we help?
        <textarea
          name="message"
          rows={6}
          required
          maxLength={10000}
          aria-invalid={fieldErrors.message ? true : undefined}
          aria-describedby={fieldErrors.message ? "message-error" : undefined}
        />
        {fieldErrors.message && <span id="message-error" className="field-error">{fieldErrors.message}</span>}
      </label>

      {/* Honeypot: hidden from people and assistive tech; bots that fill it are discarded server-side. */}
      <div className="hp-field" aria-hidden="true">
        <label>
          Website<input name="website" tabIndex={-1} autoComplete="off" defaultValue="" />
        </label>
      </div>

      <button className="button button--primary" type="submit" disabled={status === "sending"}>
        {status === "sending" ? "Sending…" : "Send enquiry"}
      </button>
      <p ref={statusRef} tabIndex={-1} className="form-status" role="status" aria-live="polite" data-state={status}>
        {message}
      </p>
      <p className="form-note">
        We use your details only to respond to your enquiry. See our <Link href="/privacy">privacy notice</Link>.
      </p>
    </form>
  );
}

type FieldProps = {
  name: "name" | "email" | "company";
  label: string;
  type?: string;
  autoComplete: string;
  maxLength: number;
  required: boolean;
  error?: string;
};

function Field({ name, label, type = "text", autoComplete, maxLength, required, error }: FieldProps) {
  const errorId = `${name}-error`;
  return (
    <label>
      {label}
      <input
        name={name}
        type={type}
        autoComplete={autoComplete}
        maxLength={maxLength}
        required={required}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
      />
      {error && <span id={errorId} className="field-error">{error}</span>}
    </label>
  );
}

function focusFirstInvalid(form: HTMLFormElement, fields: ContactFieldErrors) {
  const first = (["name", "email", "company", "message"] as const).find((key) => fields[key]);
  if (first) (form.elements.namedItem(first) as HTMLElement | null)?.focus();
}
