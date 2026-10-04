"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, Fragment, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FieldShell, FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, type ApplicationDetail, downloadCv, formatBytes, formatDateTime } from "@/lib/admin-api";

type Gate = "evidence" | "interview";
const ATTACHMENT_MAX_BYTES = 5 * 1024 * 1024;
const ATTACHMENT_ACCEPT = ".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document";

/** A file's bytes as base64, for the JSON request. */
async function toBase64(file: Blob): Promise<string> {
  const bytes = new Uint8Array(await file.arrayBuffer());
  let binary = "";
  for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
  return btoa(binary);
}

const NOTE_LABELS: Record<string, string> = { note: "Note", stage: "Stage", score: "Score", email: "Email sent" };

/** One application: details, CV, stage, scorecards, candidate emails and notes (D-019). */
export default function ApplicationView({ id }: { id: string }) {
  const { apiBase, user, request } = useAdmin();
  const router = useRouter();
  const [app, setApp] = useState<ApplicationDetail | null>(null);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState("");
  // Field errors from the last failed action, and which action it was.
  const [fields, setFields] = useState<Record<string, string>>({});
  const [failed, setFailed] = useState("");
  const [busy, setBusy] = useState("");
  const [template, setTemplate] = useState("");
  const [subject, setSubject] = useState("");
  const [body, setBody] = useState("");
  const [attachment, setAttachment] = useState<File | null>(null);
  const [fileKey, setFileKey] = useState(0);

  useEffect(() => {
    if (!can(user, "recruitment.read")) return;
    let cancelled = false;
    request<ApplicationDetail>(`/applications/${encodeURIComponent(id)}`)
      .then((result) => !cancelled && setApp(result.data))
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "The application could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, id]);

  if (!can(user, "recruitment.read")) return <NoAccess title="Application" />;
  const manage = can(user, "recruitment.manage");
  const path = `/applications/${encodeURIComponent(id)}`;

  async function run(label: string, action: () => Promise<ApplicationDetail | null>, success: string, form?: HTMLFormElement) {
    setBusy(label);
    setError("");
    setSaved("");
    setFields({});
    setFailed("");
    try {
      const result = await action();
      if (result) setApp(result);
      setSaved(success);
      form?.reset();
      return true;
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "That could not be saved.");
      if (e instanceof AdminApiError) setFields(e.fields);
      setFailed(label);
      return false;
    } finally {
      setBusy("");
    }
  }

  const back = <p><Link href="/admin/recruitment">← All applications</Link></p>;
  if (!app) {
    return (
      <>
        {back}
        <h1 className="admin-title">Application</h1>
        {error ? <FormStatus state="error" message={error} /> : <p role="status">Loading application…</p>}
      </>
    );
  }

  function changeStage(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const stage = String(new FormData(event.currentTarget).get("stage") ?? "");
    const label = app?.stages.find((s) => s.value === stage)?.label ?? stage;
    run("stage", async () => (await request<ApplicationDetail>(path, { method: "PATCH", body: { stage } })).data, `Moved to “${label}”.`);
  }

  function score(gate: Gate, event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const scores: Record<string, number> = {};
    for (const key of Object.keys(app?.scorecards[gate].criteria ?? {})) scores[key] = Number(data.get(key) ?? 0);
    run(gate, async () => (await request<ApplicationDetail>(`${path}/scores`, { method: "POST", body: { gate, scores } })).data, `${app?.scorecards[gate].label} saved.`);
  }

  function addNote(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const note = String(new FormData(form).get("body") ?? "");
    run("note", async () => (await request<ApplicationDetail>(`${path}/notes`, { method: "POST", body: { body: note } })).data, "Note added.", form);
  }

  function chooseTemplate(key: string) {
    setTemplate(key);
    const chosen = app?.email_templates.find((t) => t.key === key);
    setSubject(chosen?.subject ?? "");
    setBody(chosen?.body ?? "");
  }

  async function sendEmail(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (attachment && attachment.size > ATTACHMENT_MAX_BYTES) {
      setFailed("email");
      setFields({ attachment: "Attachments can be up to 5 MB." });
      setError("Please correct the highlighted fields.");
      return;
    }
    const file = attachment ? { filename: attachment.name, content_base64: await toBase64(attachment) } : undefined;
    const sent = await run("email", async () => (await request<ApplicationDetail>(`${path}/emails`, { method: "POST", body: { template: template || "custom", subject, body, attachment: file } })).data, `Email sent to ${app?.email}${attachment ? ` with ${attachment.name}` : ""}.`);
    if (sent) {
      chooseTemplate("");
      setAttachment(null);
      setFileKey((k) => k + 1);
    }
  }

  async function erase() {
    if (!window.confirm(`Erase ${app?.full_name}'s application, notes and CV permanently? This cannot be undone.`)) return;
    const done = await run("erase", async () => {
      await request(path, { method: "DELETE" });
      return null;
    }, "Application erased.");
    if (done) router.replace("/admin/recruitment");
  }

  async function cv() {
    setError("");
    try {
      await downloadCv(apiBase, id);
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "The CV could not be downloaded.");
    }
  }

  const errorFor = (action: string, key: string) => (failed === action ? fields[key] : undefined);
  const suggested = app.email_templates.find((t) => t.key === template)?.stage;

  return (
    <>
      {back}
      <h1 className="admin-title">{app.full_name}</h1>
      <p className="admin-intro">
        {app.reference} · {app.role.title} · <span className="status-pill" data-status={app.stage}>{app.stage_label}</span>
      </p>
      <FormStatus state="success" message={saved} />
      <FormStatus state="error" message={error} />

      <div className="admin-detail">
        <div className="admin-stack">
          <section className="form-card" aria-labelledby="application-heading">
            <h2 id="application-heading">Application</h2>
            <dl className="admin-dl">
              <dt>Email</dt><dd><a href={`mailto:${app.email}`}>{app.email}</a></dd>
              <dt>Phone</dt><dd>{app.phone || "—"}</dd>
              <dt>Location</dt><dd>{app.location}</dd>
              <dt>Hours a week</dt><dd>{app.hours_per_week}</dd>
              <dt>Portfolio</dt><dd>{app.portfolio_url ? <a href={app.portfolio_url} rel="noopener noreferrer" target="_blank">{app.portfolio_url}</a> : "—"}</dd>
              <dt>LinkedIn</dt><dd>{app.linkedin_url ? <a href={app.linkedin_url} rel="noopener noreferrer" target="_blank">{app.linkedin_url}</a> : "—"}</dd>
              <dt>CV</dt>
              <dd>
                {app.cv ? (
                  <button type="button" className="admin-link-button" onClick={cv}>Download {app.cv.filename} ({formatBytes(app.cv.size_bytes)})</button>
                ) : "No CV (links only)"}
              </dd>
            </dl>
            <h3>Why this role</h3>
            <p className="admin-message">{app.motivation}</p>
            <h3>Experience and evidence</h3>
            <p className="admin-message">{app.experience}</p>
          </section>

          {(["evidence", "interview"] as const).map((gate) => {
            const card = app.scorecards[gate];
            const result = app[gate];
            return (
              <section key={gate} className="form-card" aria-labelledby={`${gate}-heading`}>
                <h2 id={`${gate}-heading`}>{card.label}</h2>
                <p className="admin-help">
                  Score each criterion from 1 (weak) to 5 (excellent). Progression mark: {card.pass} of 35.
                  {result && <> Current: <strong>{result.total}/35</strong>, {result.passed ? "meets the mark" : "below the mark"}.</>}
                </p>
                {manage ? (
                  <form className="contact-form scorecard" onSubmit={(e) => score(gate, e)} key={JSON.stringify(result?.scores ?? {})}>
                    {Object.entries(card.criteria).map(([key, label]) => (
                      <FieldShell key={key} label={label} error={errorFor(gate, key)}>
                        {(props) => (
                          <select name={key} defaultValue={result?.scores[key] ? String(result.scores[key]) : ""} required {...props}>
                            <option value="" disabled>Score</option>
                            {[1, 2, 3, 4, 5].map((n) => <option key={n} value={n}>{n}</option>)}
                          </select>
                        )}
                      </FieldShell>
                    ))}
                    <button className="button button--primary" type="submit" disabled={busy !== ""}>{busy === gate ? "Saving…" : "Save scorecard"}</button>
                  </form>
                ) : result ? (
                  <dl className="admin-dl">
                    {Object.entries(card.criteria).map(([key, label]) => (
                      <Fragment key={key}><dt>{label}</dt><dd>{result.scores[key]}</dd></Fragment>
                    ))}
                  </dl>
                ) : <p>Not scored yet.</p>}
              </section>
            );
          })}

          {manage && (
            <section className="form-card" aria-labelledby="email-heading">
              <h2 id="email-heading">Email the candidate</h2>
              {app.email_available ? (
                <form className="contact-form" onSubmit={sendEmail}>
                  <FieldShell label="Template" hint="Choose a template, then edit it. Replace every [placeholder] before sending. Replies go to hr@paxofi.com.">
                    {(props) => (
                      <select value={template} onChange={(e) => chooseTemplate(e.target.value)} {...props}>
                        <option value="">Write my own</option>
                        {app.email_templates.map((t) => <option key={t.key} value={t.key}>{t.label}</option>)}
                      </select>
                    )}
                  </FieldShell>
                  <FieldShell label="Subject" error={errorFor("email", "subject")}>
                    {(props) => <input name="subject" maxLength={200} required value={subject} onChange={(e) => setSubject(e.target.value)} {...props} />}
                  </FieldShell>
                  <FieldShell label="Message" error={errorFor("email", "body")}>
                    {(props) => <textarea name="body" rows={12} maxLength={8000} required value={body} onChange={(e) => setBody(e.target.value)} {...props} />}
                  </FieldShell>
                  <FieldShell
                    label={template === "selection" ? "Attachment: the PIF Participant Agreement (required)" : "Attachment (optional)"}
                    hint="PDF or Word (.docx), up to 5 MB. It is sent with the email and kept with it for 30 days."
                    error={errorFor("email", "attachment")}
                  >
                    {(props) => (
                      <input key={fileKey} name="attachment" type="file" accept={ATTACHMENT_ACCEPT} onChange={(e) => setAttachment(e.target.files?.[0] ?? null)} {...props} />
                    )}
                  </FieldShell>
                  {suggested && suggested !== app.stage && (
                    <p className="admin-help">This email usually goes with the stage “{app.stages.find((s) => s.value === suggested)?.label}”. Change the stage separately if it applies.</p>
                  )}
                  <button className="button button--primary" type="submit" disabled={busy !== ""}>{busy === "email" ? "Sending…" : "Send email"}</button>
                </form>
              ) : (
                <p>Email sending is not set up yet (deployment guide Step 10c). Email the candidate from the hr@paxofi.com mailbox and add a note here.</p>
              )}
            </section>
          )}

          <section className="form-card" aria-labelledby="notes-heading">
            <h2 id="notes-heading">Notes and history</h2>
            {manage && (
              <form className="contact-form" onSubmit={addNote}>
                <FieldShell label="Add a note" hint="Facts and evidence only; candidates can ask to see their records." error={errorFor("note", "body")}>
                  {(props) => <textarea name="body" rows={3} maxLength={4000} required {...props} />}
                </FieldShell>
                <button className="button button--outline" type="submit" disabled={busy !== ""}>{busy === "note" ? "Adding…" : "Add note"}</button>
              </form>
            )}
            {app.notes.length === 0 ? <p>No notes yet.</p> : (
              <ol className="admin-history admin-history--notes">
                {app.notes.map((note) => (
                  <li key={note.id}>
                    <span className="admin-sub">{NOTE_LABELS[note.kind] ?? note.kind} · {note.author_name ?? "Former staff"} · {formatDateTime(note.created_at)}</span>
                    <p className="admin-message">{note.body}</p>
                  </li>
                ))}
              </ol>
            )}
          </section>
        </div>

        <aside className="form-card" aria-label="Stage and record">
          <h2>Stage</h2>
          <dl className="admin-dl">
            <dt>Applied</dt><dd>{formatDateTime(app.created_at)}</dd>
            <dt>Stage since</dt><dd>{formatDateTime(app.stage_changed_at)}</dd>
            {app.closed_at && (<><dt>Closed</dt><dd>{formatDateTime(app.closed_at)}</dd></>)}
            <dt>Role</dt><dd>{app.role.title}</dd>
          </dl>
          {manage && (
            <form className="contact-form" onSubmit={changeStage}>
              <FieldShell label="Move to stage" error={errorFor("stage", "stage")}>
                {(props) => (
                  <select name="stage" defaultValue={app.stage} key={app.stage} {...props}>
                    {app.stages.map((s) => <option key={s.value} value={s.value}>{s.label}{s.closed ? " (closes the application)" : ""}</option>)}
                  </select>
                )}
              </FieldShell>
              <button className="button button--primary" type="submit" disabled={busy !== ""}>{busy === "stage" ? "Saving…" : "Save stage"}</button>
            </form>
          )}
          <p className="admin-help">Deleted automatically 12 months after it closes (or after 12 months without a stage change).</p>
          {manage && (
            <p><button type="button" className="button button--outline admin-danger" onClick={erase} disabled={busy !== ""}>Erase application</button></p>
          )}
        </aside>
      </div>
    </>
  );
}
