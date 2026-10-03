"use client";

import Link from "next/link";
import qrcode from "qrcode-generator";
import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import { useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus, formValues } from "@/components/admin/AdminForm";
import { AdminApiError, TwoFactorStatus } from "@/lib/admin-api";

type Setup = { secret: string; otpauth_uri: string };

/** Set up, manage or turn off two-factor sign-in with an authenticator app (D-010). */
export default function TwoFactorSettings() {
  const { user, setUser, request } = useAdmin();
  const [status, setStatus] = useState<TwoFactorStatus | null>(null);
  const [setup, setSetup] = useState<Setup | null>(null);
  const [codes, setCodes] = useState<string[] | null>(null);
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(
    () =>
      request<TwoFactorStatus>("/account/two-factor")
        .then((result) => setStatus(result.data))
        .catch((e) => setError(e instanceof AdminApiError ? e.message : "Two-factor settings could not be loaded.")),
    [request],
  );

  useEffect(() => {
    load();
  }, [load]);

  async function run<T>(action: () => Promise<T>): Promise<T | undefined> {
    setBusy(true);
    setError("");
    setNotice("");
    setFields({});
    try {
      return await action();
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(e.message);
      } else {
        setError("Something went wrong. Please try again.");
      }
      return undefined;
    } finally {
      setBusy(false);
    }
  }

  async function begin() {
    const result = await run(() => request<Setup>("/account/two-factor/setup", { method: "POST" }));
    if (result) setSetup(result.data);
  }

  async function enable(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const body = formValues(event.currentTarget);
    const result = await run(() => request<{ recovery_codes: string[] }>("/account/two-factor/enable", { method: "POST", body }));
    if (result) {
      setSetup(null);
      setCodes(result.data.recovery_codes);
      if (user) setUser({ ...user, two_factor_enabled: true, two_factor_enrollment_required: false });
      load();
    }
  }

  async function replaceCodes(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const result = await run(() => request<{ recovery_codes: string[] }>("/account/two-factor/recovery-codes", { method: "POST", body: formValues(form) }));
    if (result) {
      form.reset();
      setCodes(result.data.recovery_codes);
      load();
    }
  }

  async function disable(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const result = await run(() => request("/account/two-factor/disable", { method: "POST", body: formValues(form) }));
    if (result) {
      form.reset();
      if (user) setUser({ ...user, two_factor_enabled: false });
      setNotice("Two-factor sign-in is off. You will sign in with your password only.");
      load();
    }
  }

  if (!status) {
    return (
      <>
        <h1 className="admin-title">Two-factor sign-in</h1>
        {error ? <FormStatus state="error" message={error} /> : <p role="status">Loading…</p>}
      </>
    );
  }

  return (
    <>
      <p><Link href="/admin/account">← My account</Link></p>
      <h1 className="admin-title">Two-factor sign-in</h1>
      <p className="admin-intro">
        After your password, Paxofi asks for a 6-digit code from an authenticator app on your phone, so a stolen password alone
        is not enough to get in.
      </p>
      {status.required && !status.enabled && (
        <p className="admin-callout" role="status">Administrators must use two-factor sign-in. Set it up to continue to the staff area.</p>
      )}
      <FormStatus state="success" message={notice} />

      {codes ? (
        <RecoveryCodesPanel codes={codes} onDone={() => setCodes(null)} />
      ) : !status.configured && !status.enabled ? (
        <section className="form-card">
          <h2>Not available yet</h2>
          <p>Two-factor sign-in has not been switched on for this server. Ask whoever runs the server to add <code>MFA_ENCRYPTION_KEY</code> (deployment guide).</p>
        </section>
      ) : status.enabled ? (
        <div className="admin-detail">
          <section className="form-card" aria-labelledby="tf-on">
            <h2 id="tf-on">On <span className="status-pill" data-status="active">Protected</span></h2>
            <p>Recovery codes left: <strong>{status.recovery_codes_left}</strong> of 10.</p>
            <h3>New recovery codes</h3>
            <p className="form-note">Replaces all your recovery codes. Do this if you have used most of them or think someone has seen them.</p>
            <form className="contact-form" onSubmit={replaceCodes}>
              <Field name="code" label="Current authenticator code" autoComplete="one-time-code" inputMode="numeric" required maxLength={6} error={fields.code} />
              <button className="button button--outline" type="submit" disabled={busy}>Get new recovery codes</button>
            </form>
          </section>
          <section className="form-card" aria-labelledby="tf-off">
            <h2 id="tf-off">New phone?</h2>
            {status.required ? (
              <p>Administrators cannot turn two-factor off. If you lose your phone, sign in with a recovery code and ask another administrator to reset your two-factor, then set it up again.</p>
            ) : (
              <>
                <p>Turn two-factor off, then set it up again on the new phone.</p>
                <form className="contact-form" onSubmit={disable}>
                  <Field name="password" label="Your password" type="password" autoComplete="current-password" required error={fields.password} />
                  <button className="button button--outline" type="submit" disabled={busy}>Turn off two-factor sign-in</button>
                </form>
              </>
            )}
          </section>
        </div>
      ) : setup ? (
        <SetupSteps setup={setup} busy={busy} codeError={fields.code} onSubmit={enable} />
      ) : (
        <section className="form-card">
          <h2>Set up</h2>
          <p>You need an authenticator app on your phone, such as Google Authenticator, Microsoft Authenticator or 1Password. It takes about two minutes.</p>
          <button className="button button--primary" type="button" onClick={begin} disabled={busy}>
            {busy ? "Starting…" : "Set up two-factor sign-in"}
          </button>
        </section>
      )}
      <FormStatus state="error" message={error} />
    </>
  );
}

function SetupSteps({ setup, busy, codeError, onSubmit }: { setup: Setup; busy: boolean; codeError?: string; onSubmit: (e: FormEvent<HTMLFormElement>) => void }) {
  const qr = useMemo(() => {
    const code = qrcode(0, "M");
    code.addData(setup.otpauth_uri);
    code.make();
    return "data:image/svg+xml;utf8," + encodeURIComponent(code.createSvgTag({ cellSize: 4, margin: 4, scalable: true }));
  }, [setup.otpauth_uri]);
  const groupedKey = setup.secret.match(/.{1,4}/g)?.join(" ") ?? setup.secret;

  return (
    <section className="form-card" aria-labelledby="tf-setup">
      <h2 id="tf-setup">Set up</h2>
      <ol className="admin-steps">
        <li>Open your authenticator app and add an account (often a <strong>+</strong> button).</li>
        <li>
          Scan this QR code:
          {/* eslint-disable-next-line @next/next/no-img-element -- generated data URL, nothing to optimise */}
          <img className="admin-qr" src={qr} alt="QR code to add Paxofi to your authenticator app" width={200} height={200} />
          Can’t scan? Choose “enter a set-up key” and type: <code className="admin-key">{groupedKey}</code>
        </li>
        <li>Enter the 6-digit code the app now shows for Paxofi.</li>
      </ol>
      <form className="contact-form" onSubmit={onSubmit}>
        <Field name="code" label="6-digit code" autoComplete="one-time-code" inputMode="numeric" required maxLength={6} error={codeError} />
        <button className="button button--primary" type="submit" disabled={busy}>{busy ? "Checking…" : "Turn on two-factor sign-in"}</button>
      </form>
    </section>
  );
}

function RecoveryCodesPanel({ codes, onDone }: { codes: string[]; onDone: () => void }) {
  const [saved, setSaved] = useState(false);
  const [copied, setCopied] = useState("");
  const text = `Paxofi staff area recovery codes (each works once)\n\n${codes.join("\n")}\n`;
  const download = useMemo(() => "data:text/plain;charset=utf-8," + encodeURIComponent(text), [text]);

  return (
    <section className="form-card" aria-labelledby="tf-codes">
      <h2 id="tf-codes">Save your recovery codes</h2>
      <p>
        If you lose your phone, each of these codes lets you sign in once instead of the 6-digit code. Keep them somewhere safe,
        such as a password manager. <strong>They will not be shown again.</strong>
      </p>
      <ul className="admin-codes">
        {codes.map((code) => (
          <li key={code}><code>{code}</code></li>
        ))}
      </ul>
      <div className="actions">
        <button
          type="button"
          className="button button--outline"
          onClick={() =>
            navigator.clipboard
              ?.writeText(text)
              .then(() => setCopied("Copied."))
              .catch(() => setCopied("Copy is not available; download the file instead."))
          }
        >
          Copy codes
        </button>
        <a className="button button--outline" href={download} download="paxofi-recovery-codes.txt">Download as a text file</a>
      </div>
      <FormStatus state="info" message={copied} />
      <label className="admin-check">
        <input type="checkbox" checked={saved} onChange={(e) => setSaved(e.target.checked)} /> I have saved my recovery codes
      </label>
      <button type="button" className="button button--primary" disabled={!saved} onClick={onDone}>Done</button>
    </section>
  );
}
