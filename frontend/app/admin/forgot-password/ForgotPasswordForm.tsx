"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { AdminFrame } from "@/components/admin/AdminFrame";
import { useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus, formValues } from "@/components/admin/AdminForm";
import { AdminApiError } from "@/lib/admin-api";

/** Asks for a password reset link by email (D-016). The answer never reveals whether the address has an account. */
export default function ForgotPasswordForm() {
  const { request } = useAdmin();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [fieldError, setFieldError] = useState("");
  const [sent, setSent] = useState<{ available: boolean } | null>(null);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError("");
    setFieldError("");
    try {
      const result = await request<{ requested: boolean; available: boolean }>("/password-reset", { method: "POST", body: formValues(event.currentTarget) });
      setSent({ available: result.data.available });
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFieldError(e.fields.email ?? "");
        setError(e.fields.email ? "" : e.message);
      } else {
        setError("That did not work. Please try again.");
      }
    } finally {
      setBusy(false);
    }
  }

  if (sent) {
    return (
      <AdminFrame title="Check your email" intro="">
        <FormStatus
          state="success"
          message={
            sent.available
              ? "If that address belongs to a staff account, a link to choose a new password is on its way. It works once, for 30 minutes."
              : "Password reset by email is not switched on yet. Ask an administrator to set a temporary password for you."
          }
        />
        <p className="form-note admin-help">
          Nothing after 10 minutes? Check your spam folder, or ask an administrator. <Link href="/admin/login">Back to sign in</Link>
        </p>
      </AdminFrame>
    );
  }

  return (
    <AdminFrame title="Forgot your password?" intro="Enter the email address you sign in with. We will email you a link to choose a new password.">
      <form className="contact-form" method="post" onSubmit={submit}>
        <Field name="email" label="Email" type="email" autoComplete="username" required maxLength={255} autoFocus error={fieldError} />
        <FormStatus state="error" message={error} />
        <button className="button button--primary" type="submit" disabled={busy}>
          {busy ? "Sending…" : "Email me a link"}
        </button>
      </form>
      <p className="form-note admin-help">
        <Link href="/admin/login">Back to sign in</Link>
      </p>
    </AdminFrame>
  );
}
