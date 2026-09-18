"use client";

import { FormEvent, useState } from "react";

type Props = { apiUrl: string };

export default function ContactForm({ apiUrl }: Props) {
  const [status, setStatus] = useState<"idle" | "sending" | "success" | "error">("idle");
  const [message, setMessage] = useState("");

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setStatus("sending");
    setMessage("");

    const form = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form).entries());

    try {
      const response = await fetch(apiUrl, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const body = (await response.json().catch(() => null)) as
        | { data?: { accepted?: boolean }; error?: { message?: string } }
        | null;

      if (!response.ok) {
        throw new Error(body?.error?.message || "We could not send your enquiry. Please try again.");
      }

      form.reset();
      setStatus("success");
      setMessage("Thanks — your enquiry has been received. We&apos;ll get back to you.");
    } catch (error) {
      setStatus("error");
      setMessage(error instanceof Error ? error.message : "We could not send your enquiry. Please try again.");
    }
  }

  return (
    <form className="contact-form" onSubmit={submit} noValidate={false}>
      <label>
        Name<input name="name" autoComplete="name" required maxLength={160} />
      </label>
      <label>
        Email<input name="email" type="email" autoComplete="email" required maxLength={255} />
      </label>
      <label>
        Company<input name="company" autoComplete="organization" maxLength={255} />
      </label>
      <label>
        How can we help?<textarea name="message" rows={6} required maxLength={10000} />
      </label>
      <button className="button primary" type="submit" disabled={status === "sending"}>
        {status === "sending" ? "Sending…" : "Send enquiry"}
      </button>
      <p className="form-status" role="status" aria-live="polite" data-state={status}>
        {message}
      </p>
    </form>
  );
}
