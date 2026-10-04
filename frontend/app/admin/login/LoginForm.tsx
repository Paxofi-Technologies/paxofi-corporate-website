"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";
import { AdminFrame } from "@/components/admin/AdminFrame";
import { useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus, formValues } from "@/components/admin/AdminForm";
import { AdminApiError, StaffUser } from "@/lib/admin-api";

type SignInResult = StaffUser & { mfa_required?: boolean };

/** Sign in: email and password, then the authenticator code when two-factor is on (D-010). */
export default function LoginForm({ notice, next, codeStep = false }: { notice: string; next: string; codeStep?: boolean }) {
  const { request, setUser } = useAdmin();
  const router = useRouter();
  const [step, setStep] = useState<"password" | "code">(codeStep ? "code" : "password");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [codeError, setCodeError] = useState("");
  const [info, setInfo] = useState(notice);
  const [setupAvailable, setSetupAvailable] = useState(false);

  useEffect(() => {
    request<{ available: boolean }>("/setup")
      .then((result) => setSetupAvailable(result.data.available))
      .catch(() => setSetupAvailable(false));
  }, [request]);

  async function submitPassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError("");
    try {
      const result = await request<SignInResult>("/session", { method: "POST", body: formValues(event.currentTarget) });
      if (result.data.mfa_required) {
        setInfo("");
        setStep("code");
        setBusy(false);
        return;
      }
      setUser(result.data);
      router.replace(next);
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "Sign-in failed. Please try again.");
      setBusy(false);
    }
  }

  async function submitCode(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError("");
    setCodeError("");
    try {
      const result = await request<SignInResult>("/session/mfa", { method: "POST", body: formValues(event.currentTarget) });
      setUser(result.data);
      router.replace(next);
    } catch (e) {
      if (e instanceof AdminApiError && e.status === 401) {
        // The 5 minutes for the code ran out, or the session ended.
        setStep("password");
        setInfo("That took too long. Please sign in again.");
      } else if (e instanceof AdminApiError) {
        setCodeError(e.fields.code ?? "");
        setError(e.fields.code ? "" : e.message);
        if (e.status === 429) setStep("password");
      } else {
        setError("Sign-in failed. Please try again.");
      }
      setBusy(false);
    }
  }

  async function startAgain() {
    await request("/session", { method: "DELETE" }).catch(() => undefined);
    setError("");
    setCodeError("");
    setStep("password");
  }

  if (step === "code") {
    return (
      <AdminFrame title="Enter your code" intro="Open your authenticator app and enter the 6-digit code for Paxofi.">
        <FormStatus state="info" message={info} />
        <form className="contact-form" method="post" onSubmit={submitCode}>
          <Field
            name="code"
            label="Authenticator code"
            autoComplete="one-time-code"
            required
            maxLength={20}
            autoFocus
            hint="Lost your phone? Enter one of your recovery codes instead."
            error={codeError}
          />
          <FormStatus state="error" message={error} />
          <button className="button button--primary" type="submit" disabled={busy}>
            {busy ? "Checking…" : "Verify and sign in"}
          </button>
        </form>
        <p className="form-note admin-help">
          <button type="button" className="admin-link-button" onClick={startAgain}>Sign in as someone else</button>
        </p>
      </AdminFrame>
    );
  }

  return (
    <AdminFrame title="Sign in" intro="For Paxofi staff. Visitors can reach us through the contact page.">
      <FormStatus state="info" message={info} />
      <form className="contact-form" method="post" onSubmit={submitPassword}>
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
        <Link href="/admin/forgot-password">Forgot your password?</Link>
        {setupAvailable && (
          <>
            {" "}First time here? <Link href="/admin/setup">Set up the first administrator</Link>.
          </>
        )}
      </p>
    </AdminFrame>
  );
}
