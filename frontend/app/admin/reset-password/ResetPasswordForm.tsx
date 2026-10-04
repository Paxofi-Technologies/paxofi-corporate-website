"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { AdminFrame } from "@/components/admin/AdminFrame";
import { useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus, PASSWORD_HINT, formValues } from "@/components/admin/AdminForm";
import { AdminApiError } from "@/lib/admin-api";

/**
 * Sets a new password with the emailed link (D-016). The token is in the
 * address after "#", so it never reaches server logs; it is read here and
 * removed from the address bar straight away.
 */
export default function ResetPasswordForm() {
  const { request } = useAdmin();
  const [token, setToken] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});
  const [done, setDone] = useState(false);

  useEffect(() => {
    const value = window.location.hash.slice(1);
    // Read once from the address bar, then hide it there (the effect runs only on mount).
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setToken(/^[A-Za-z0-9_-]{20,100}$/.test(value) ? value : "");
    if (value) window.history.replaceState(null, "", window.location.pathname);
  }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = formValues(event.currentTarget);
    setFields({});
    setError("");
    if (values.password !== values.confirm_password) {
      setFields({ confirm_password: "The two passwords do not match." });
      return;
    }
    setBusy(true);
    try {
      await request("/password-reset/complete", { method: "POST", body: { token, password: values.password } });
      setDone(true);
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(e.fields.token ?? (e.fields.password ? "" : e.message));
      } else {
        setError("That did not work. Please try again.");
      }
    } finally {
      setBusy(false);
    }
  }

  if (done) {
    return (
      <AdminFrame title="Password changed" intro="">
        <FormStatus state="success" message="Your new password is set and every other session was signed out. Sign in with it now." />
        <p><Link className="button button--primary" href="/admin/login">Sign in</Link></p>
      </AdminFrame>
    );
  }

  if (token === "") {
    return (
      <AdminFrame title="This link does not work" intro="">
        <FormStatus state="error" message="Open the link from the email exactly as it was sent, or ask for a new one." />
        <p><Link href="/admin/forgot-password">Ask for a new link</Link></p>
      </AdminFrame>
    );
  }

  return (
    <AdminFrame title="Choose a new password" intro="The link works once, within 30 minutes of asking for it.">
      <form className="contact-form" method="post" onSubmit={submit}>
        <Field name="password" label="New password" type="password" autoComplete="new-password" required maxLength={256} hint={PASSWORD_HINT} error={fields.password} />
        <Field name="confirm_password" label="Confirm new password" type="password" autoComplete="new-password" required maxLength={256} error={fields.confirm_password} />
        <FormStatus state="error" message={error} />
        <button className="button button--primary" type="submit" disabled={busy || token === null}>
          {busy ? "Saving…" : "Set new password"}
        </button>
      </form>
      {error && fields.token && (
        <p className="form-note admin-help"><Link href="/admin/forgot-password">Ask for a new link</Link></p>
      )}
    </AdminFrame>
  );
}
