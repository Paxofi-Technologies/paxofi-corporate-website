"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";
import { AdminFrame } from "@/components/admin/AdminFrame";
import { useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus, formValues } from "@/components/admin/AdminForm";
import { AdminApiError, StaffUser } from "@/lib/admin-api";

export default function LoginForm({ notice, next }: { notice: string; next: string }) {
  const { request, setUser } = useAdmin();
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [setupAvailable, setSetupAvailable] = useState(false);

  useEffect(() => {
    request<{ available: boolean }>("/setup")
      .then((result) => setSetupAvailable(result.data.available))
      .catch(() => setSetupAvailable(false));
  }, [request]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const result = await request<StaffUser>("/session", { method: "POST", body: formValues(event.currentTarget) });
      setUser(result.data);
      router.replace(next);
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "Sign-in failed. Please try again.");
      setBusy(false);
    }
  }

  return (
    <AdminFrame title="Sign in" intro="For Paxofi staff. Visitors can reach us through the contact page.">
      <FormStatus state="info" message={notice} />
      <form className="contact-form" method="post" onSubmit={submit}>
        <noscript>
          <p className="form-status" data-state="error">Signing in needs JavaScript.</p>
        </noscript>
        <Field name="email" label="Email" type="email" autoComplete="username" required maxLength={255} />
        <Field name="password" label="Password" type="password" autoComplete="current-password" required maxLength={256} />
        <FormStatus state="error" message={error} />
        <button className="button button--primary" type="submit" disabled={busy}>
          {busy ? "Signing in…" : "Sign in"}
        </button>
      </form>
      <p className="form-note admin-help">
        Forgotten your password? Ask an administrator to set a temporary one for you.
        {setupAvailable && (
          <>
            {" "}First time here? <Link href="/admin/setup">Set up the first administrator</Link>.
          </>
        )}
      </p>
    </AdminFrame>
  );
}
