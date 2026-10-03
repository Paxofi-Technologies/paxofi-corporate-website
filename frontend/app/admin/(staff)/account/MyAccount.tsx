"use client";

import { FormEvent, useState } from "react";
import { useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus, PASSWORD_HINT, formValues } from "@/components/admin/AdminForm";
import { AdminApiError, formatDateTime } from "@/lib/admin-api";

export default function MyAccount() {
  const { user, request } = useAdmin();
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [saved, setSaved] = useState("");
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const values = formValues(form);
    setFields({});
    setError("");
    setSaved("");
    if (values.new_password !== values.confirm_password) {
      setFields({ confirm_password: "The new passwords do not match." });
      setError("Please correct the highlighted fields.");
      return;
    }
    setBusy(true);
    try {
      await request("/session/password", {
        method: "POST",
        body: { current_password: values.current_password, new_password: values.new_password },
      });
      form.reset();
      setSaved("Your password has been changed. Any other signed-in browsers have been signed out.");
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(e.message);
      } else {
        setError("Your password could not be changed.");
      }
    } finally {
      setBusy(false);
    }
  }

  if (!user) return null;

  return (
    <>
      <h1 className="admin-title">My account</h1>
      <div className="admin-detail">
        <section className="form-card" aria-labelledby="profile-heading">
          <h2 id="profile-heading">Profile</h2>
          <dl className="admin-dl">
            <dt>Name</dt><dd>{user.display_name}</dd>
            <dt>Email</dt><dd>{user.email}</dd>
            <dt>Role</dt><dd>{user.role_label ?? "No role"}</dd>
            <dt>Last sign-in</dt><dd>{formatDateTime(user.last_login_at)}</dd>
          </dl>
          <p className="form-note">To change your name, email or role, ask an administrator.</p>
        </section>
        <section className="form-card" aria-labelledby="password-heading">
          <h2 id="password-heading">Change password</h2>
          <form className="contact-form" onSubmit={submit}>
            <Field name="current_password" label="Current password" type="password" autoComplete="current-password" required error={fields.current_password} />
            <Field
              name="new_password"
              label="New password"
              type="password"
              autoComplete="new-password"
              required
              minLength={12}
              maxLength={256}
              hint={PASSWORD_HINT}
              error={fields.new_password}
            />
            <Field name="confirm_password" label="Confirm new password" type="password" autoComplete="new-password" required error={fields.confirm_password} />
            <FormStatus state="success" message={saved} />
            <FormStatus state="error" message={error} />
            <button className="button button--primary" type="submit" disabled={busy}>{busy ? "Saving…" : "Change password"}</button>
          </form>
        </section>
      </div>
    </>
  );
}
