"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FieldShell, FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, type MailAttempt, type MailFieldKey, type MailStatus, formatDateTime } from "@/lib/admin-api";

const SOURCE: Record<string, string> = { staff: "saved here", server: "from backend/.env", none: "not set" };

/** Email (D-031): SMTP settings saved here or in .env, the outbox, a test email and "send queued emails now". */
export default function EmailSettings() {
  const { user, request } = useAdmin();
  const [status, setStatus] = useState<MailStatus | null>(null);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState("");
  const [result, setResult] = useState<{ ok: boolean; text: string } | null>(null);
  const [formKey, setFormKey] = useState(0);

  const load = useCallback(() => {
    request<MailStatus>("/mail")
      .then((r) => setStatus(r.data))
      .catch((e) => setError(e instanceof AdminApiError ? e.message : "The email settings could not be loaded."));
  }, [request]);

  useEffect(() => {
    if (can(user, "users.manage")) load();
  }, [user, load]);

  if (!can(user, "users.manage")) return <NoAccess title="Email" />;
  if (status === null) return error ? <FormStatus state="error" message={error} /> : <p role="status">Loading…</p>;

  const field = (key: MailFieldKey) => status.fields[key];
  const hint = (key: MailFieldKey) => {
    const f = field(key);
    return f.source === "staff" ? "Saved here. Empty it and save to use the backend/.env setting again." : `Now ${SOURCE[f.source]}${f.source === "server" && f.value ? ` (${f.value})` : ""}.`;
  };

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const body: Record<string, unknown> = {};
    for (const key of ["transport", "host", "port", "encryption", "username", "from_address", "from_name"]) body[key] = String(data.get(key) ?? "");
    const password = String(data.get("password") ?? "");
    if (password !== "") body.password = password;
    if (data.get("clear_password") === "on") body.clear_password = true;
    setBusy("save");
    setError("");
    setSaved("");
    setFields({});
    try {
      const r = await request<MailStatus>("/mail", { method: "PATCH", body });
      setStatus(r.data);
      setFormKey((k) => k + 1);
      setSaved("Saved. The new settings are used straight away, by the website and the 5-minute email job.");
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "The settings could not be saved.");
      if (e instanceof AdminApiError) setFields(e.fields);
    } finally {
      setBusy("");
    }
  }

  async function run(kind: "test" | "send-queued") {
    setBusy(kind);
    setResult(null);
    try {
      const r = await request<MailAttempt>(`/mail/${kind}`, { method: "POST" });
      setStatus(r.data.status);
      const a = r.data;
      setResult(
        a.ok
          ? { ok: true, text: kind === "test" ? `Sent to ${a.to}. Check that inbox (and spam) in a minute.` : `Sent ${a.sent ?? 0}, failed ${a.failed ?? 0}${(a.failed ?? 0) > 0 ? ". The reason is shown under Queue." : "."}` }
          : { ok: false, text: `Not sent: ${a.error}` },
      );
    } catch (e) {
      setResult({ ok: false, text: e instanceof AdminApiError ? e.message : "The request failed." });
    } finally {
      setBusy("");
    }
  }

  const q = status.queue;
  return (
    <>
      <h1 className="admin-title">Email</h1>
      <p className="admin-intro">
        The website sends acknowledgements, alerts and password links through your mailbox. Enter the mailbox&apos;s SMTP details here, or keep them in backend/.env. A value saved here wins; an empty field uses the .env setting.
      </p>

      <section className="form-card" aria-labelledby="mail-state">
        <h2 id="mail-state">Status</h2>
        <p role="status">
          <span className="status-pill" data-status={status.configured ? "active" : "failure"}>{status.configured ? "Sending is on" : "Sending is off"}</span>
          {status.problem && <> {status.problem}</>}
        </p>
        <p>
          <button type="button" className="button button--primary" onClick={() => run("test")} disabled={busy !== ""}>{busy === "test" ? "Sending…" : "Send me a test email"}</button>{" "}
          <button type="button" className="button button--outline" onClick={() => run("send-queued")} disabled={busy !== ""}>{busy === "send-queued" ? "Sending…" : "Send queued emails now"}</button>
        </p>
        {result && <FormStatus state={result.ok ? "success" : "error"} message={result.text} />}
        <h3>Queue</h3>
        <dl className="admin-dl">
          <dt>Waiting to be sent</dt><dd>{q.pending}{q.oldest_pending_at ? ` (oldest from ${formatDateTime(q.oldest_pending_at)})` : ""}</dd>
          <dt>Given up</dt><dd>{q.failed}</dd>
          <dt>Sent in the last 24 hours</dt><dd>{q.sent_last_day}</dd>
          <dt>Last sent</dt><dd>{q.last_sent_at ? formatDateTime(q.last_sent_at) : "—"}</dd>
          <dt>Latest error</dt><dd>{q.last_error ? <code className="admin-mono">{q.last_error}</code> : "None"}</dd>
        </dl>
        <p className="admin-help">Emails are sent straight after each form, and again every 5 minutes by the cPanel cron job (RB-17). If emails wait here for more than a few minutes, use the buttons above: they show the real error.</p>
      </section>

      <section className="form-card" aria-labelledby="mail-settings">
        <h2 id="mail-settings">SMTP settings</h2>
        <p className="admin-help">In cPanel → Email Accounts → Connect Devices you find the outgoing server and its SSL port for your mailbox (usually mail.paxofi.com, port 465, SSL).</p>
        <form key={formKey} className="contact-form" onSubmit={save}>
          <FieldShell label="How to send" hint={hint("transport")} error={fields.transport}>
            {(props) => (
              <select name="transport" defaultValue={field("transport").source === "staff" ? (field("transport").value ?? "") : ""} {...props}>
                <option value="">Use the .env setting</option>
                <option value="smtp">SMTP (your mailbox, recommended)</option>
                <option value="mail">The server&apos;s mail function</option>
              </select>
            )}
          </FieldShell>
          {(["host", "port", "username", "from_address", "from_name"] as const).map((key) => (
            <FieldShell key={key} label={{ host: "Outgoing mail server (host)", port: "Port", username: "Username (usually the full email address)", from_address: "Send from (email address)", from_name: "Sender name" }[key]} hint={hint(key)} error={fields[key]}>
              {(props) => <input name={key} type={key === "port" ? "number" : key === "from_address" ? "email" : "text"} autoComplete="off" defaultValue={field(key).source === "staff" ? (field(key).value ?? "") : ""} placeholder={field(key).source === "server" ? (field(key).value ?? "") : ""} {...props} />}
            </FieldShell>
          ))}
          <FieldShell label="Encryption" hint={hint("encryption")} error={fields.encryption}>
            {(props) => (
              <select name="encryption" defaultValue={field("encryption").source === "staff" ? (field("encryption").value ?? "") : ""} {...props}>
                <option value="">Use the .env setting (SSL if none)</option>
                <option value="ssl">SSL (port 465)</option>
                <option value="tls">TLS / STARTTLS (port 587)</option>
                <option value="none">None (not recommended)</option>
              </select>
            )}
          </FieldShell>
          <FieldShell
            label="Mailbox password"
            hint={`${field("password").set ? `A password is set (${SOURCE[field("password").source]}). Leave empty to keep it.` : "No password is set."} It is stored encrypted and never shown again.${status.can_store_password ? "" : " Saving it here needs MFA_ENCRYPTION_KEY in backend/.env."}`}
            error={fields.password}
          >
            {(props) => <input name="password" type="password" autoComplete="new-password" {...props} />}
          </FieldShell>
          {field("password").source === "staff" && (
            <label className="admin-inline-label"><input type="checkbox" name="clear_password" /> Remove the saved password (use the .env one)</label>
          )}
          <FormStatus state="error" message={error} />
          <FormStatus state="success" message={saved} />
          <button className="button button--primary" type="submit" disabled={busy !== ""}>{busy === "save" ? "Saving…" : "Save settings"}</button>
        </form>
      </section>
    </>
  );
}
