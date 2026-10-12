"use client";

import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FieldShell, FormStatus } from "@/components/admin/AdminForm";
import { AdminApiError, CONSENT_METHODS, type ApplicationDetail, downloadCertificate, formatDateTime } from "@/lib/admin-api";

type PanelProps = { app: ApplicationDetail; path: string; onChange: (app: ApplicationDetail) => void };

/** Runs one action and reports its outcome; field errors are kept for the form that failed. */
function useAction() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});
  async function run(action: () => Promise<void>, success: string): Promise<boolean> {
    setBusy(true);
    setError("");
    setSaved("");
    setFields({});
    try {
      await action();
      setSaved(success);
      return true;
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "That could not be saved.");
      if (e instanceof AdminApiError) setFields(e.fields);
      return false;
    } finally {
      setBusy(false);
    }
  }
  return { busy, error, saved, fields, run };
}

/** Reviewers and recusal (D-029). */
export function ReviewerPanel({ app, path, onChange }: PanelProps) {
  const { user, request } = useAdmin();
  const router = useRouter();
  const manage = can(user, "recruitment.manage");
  const assign = useAction();
  const recuse = useAction();
  const [recusing, setRecusing] = useState(false);
  const taken = new Set(app.reviewers.map((r) => r.user_id));
  const candidates = app.reviewer_candidates.filter((c) => !taken.has(c.id));

  function add(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const userId = String(new FormData(event.currentTarget).get("user_id") ?? "");
    assign.run(async () => onChange((await request<ApplicationDetail>(`${path}/reviewers`, { method: "POST", body: { user_id: userId } })).data), "Reviewer assigned.");
  }

  function remove(userId: string, name: string) {
    assign.run(async () => onChange((await request<ApplicationDetail>(`${path}/reviewers/${encodeURIComponent(userId)}`, { method: "DELETE" })).data), `${name} removed as a reviewer.`);
  }

  async function stepAside(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const reason = String(new FormData(event.currentTarget).get("reason") ?? "");
    const done = await recuse.run(async () => {
      await request(`${path}/recusal`, { method: "POST", body: { reason } });
    }, "You have stepped aside.");
    if (done) router.replace("/admin/recruitment");
  }

  return (
    <section className="form-card" aria-labelledby="reviewers-heading">
      <h2 id="reviewers-heading">Reviewers</h2>
      <p className="admin-help">
        When reviewers are assigned, only they can score this application. Anyone with a conflict of interest, such as knowing the candidate personally, should step aside: the application is then hidden from them and any scorecard they saved is cleared.
      </p>
      <FormStatus state="success" message={assign.saved} />
      <FormStatus state="error" message={assign.error} />
      {app.reviewers.length === 0 ? <p>No reviewers assigned. Anyone who manages recruitment can score.</p> : (
        <ul className="admin-list">
          {app.reviewers.map((r) => (
            <li key={r.user_id}>
              <strong>{r.name}</strong> <span className="status-pill" data-status={r.status === "recused" ? "failure" : "active"}>{r.status === "recused" ? "Stepped aside" : "Assigned"}</span>
              <span className="admin-sub"> · {formatDateTime(r.created_at)}</span>
              {r.reason && <p className="admin-message">{r.reason}</p>}
              {manage && r.status === "assigned" && (
                <> <button type="button" className="admin-link-button" onClick={() => remove(r.user_id, r.name)} disabled={assign.busy}>Remove</button></>
              )}
            </li>
          ))}
        </ul>
      )}
      {manage && candidates.length > 0 && (
        <form className="contact-form" onSubmit={add}>
          <FieldShell label="Assign a reviewer" error={assign.fields.user_id}>
            {(props) => (
              <select name="user_id" defaultValue="" required {...props}>
                <option value="" disabled>Choose a person</option>
                {candidates.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            )}
          </FieldShell>
          <button className="button button--outline" type="submit" disabled={assign.busy}>{assign.busy ? "Saving…" : "Assign reviewer"}</button>
        </form>
      )}
      {!recusing ? (
        <p><button type="button" className="button button--outline" onClick={() => setRecusing(true)}>I have a conflict of interest</button></p>
      ) : (
        <form className="contact-form" onSubmit={stepAside}>
          <FormStatus state="error" message={recuse.error} />
          <FieldShell label="Why can you not review this application?" hint="Only people who manage recruitment will see this. You will not be able to open the application again." error={recuse.fields.reason}>
            {(props) => <textarea name="reason" rows={3} minLength={10} maxLength={500} required {...props} />}
          </FieldShell>
          <button className="button button--primary" type="submit" disabled={recuse.busy}>{recuse.busy ? "Saving…" : "Step aside from this application"}</button>
          <button type="button" className="button button--outline" onClick={() => setRecusing(false)}>Cancel</button>
        </form>
      )}
    </section>
  );
}

/** Adding a candidate who was not selected to the talent pool, with their consent (D-029). */
export function TalentPoolPanel({ app, path, onChange }: PanelProps) {
  const { user, request } = useAdmin();
  const manage = can(user, "recruitment.manage");
  const action = useAction();
  const entry = app.talent_pool;
  if (!entry && !app.talent_pool_eligible) return null;

  function add(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const body = { consent_given_on: String(data.get("consent_given_on") ?? ""), consent_method: String(data.get("consent_method") ?? ""), note: String(data.get("note") ?? "") };
    action.run(async () => onChange((await request<ApplicationDetail>(`${path}/talent-pool`, { method: "POST", body })).data), "Added to the talent pool.");
  }

  function withdraw() {
    if (!entry || !window.confirm(`Remove ${app.full_name} from the talent pool? Their consent is withdrawn and nothing is kept.`)) return;
    action.run(async () => {
      await request(`/talent-pool/${encodeURIComponent(entry.id)}`, { method: "DELETE" });
      onChange((await request<ApplicationDetail>(path)).data);
    }, "Removed from the talent pool.");
  }

  return (
    <section className="form-card" aria-labelledby="pool-heading">
      <h2 id="pool-heading">Talent pool</h2>
      <FormStatus state="success" message={action.saved} />
      <FormStatus state="error" message={action.error} />
      {entry ? (
        <>
          <p>In the talent pool. Consent given on {entry.consent_given_on}; it lapses on {entry.consent_expires_on}, and the entry is then deleted.</p>
          {manage && <p><button type="button" className="button button--outline admin-danger" onClick={withdraw} disabled={action.busy}>Consent withdrawn: remove</button></p>}
        </>
      ) : manage ? (
        <form className="contact-form" onSubmit={add}>
          <p className="admin-help">Only add someone who has agreed to be contacted about future programmes. Record when and how they agreed. Their name, email, links and the role they applied for are kept for 24 months, never their CV.</p>
          <FieldShell label="Day they agreed" hint="For example 2026-11-20." error={action.fields.consent_given_on}>
            {(props) => <input name="consent_given_on" type="date" required {...props} />}
          </FieldShell>
          <FieldShell label="How they agreed" error={action.fields.consent_method}>
            {(props) => (
              <select name="consent_method" defaultValue="" required {...props}>
                <option value="" disabled>Choose</option>
                {CONSENT_METHODS.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
              </select>
            )}
          </FieldShell>
          <FieldShell label="Note (optional)" hint="For example the kinds of role they would like." error={action.fields.note}>
            {(props) => <textarea name="note" rows={2} maxLength={500} {...props} />}
          </FieldShell>
          <button className="button button--primary" type="submit" disabled={action.busy}>{action.busy ? "Saving…" : "Add to the talent pool"}</button>
        </form>
      ) : <p>Not in the talent pool.</p>}
    </section>
  );
}

/** Participation certificates (D-029). */
export function CertificatePanel({ app, path, onChange }: PanelProps) {
  const { apiBase, user, request } = useAdmin();
  const manage = can(user, "recruitment.manage");
  const administrator = can(user, "certificates.manage");
  const action = useAction();
  const hasValid = app.certificates.some((c) => c.status === "valid");
  if (!app.certificates.length && !app.certificate_eligible) return null;

  function issue(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const body = { started_on: String(data.get("started_on") ?? ""), completed_on: String(data.get("completed_on") ?? "") };
    action.run(async () => onChange((await request<ApplicationDetail>(`${path}/certificates`, { method: "POST", body })).data), "Certificate issued.");
  }

  function download(id: string) {
    action.run(() => downloadCertificate(apiBase, id), "Certificate downloaded.");
  }

  function revoke(id: string, code: string) {
    const reason = window.prompt(`Why is certificate ${code} withdrawn? (10 to 500 characters; it is kept on the record but never shown publicly)`);
    if (reason === null) return;
    action.run(async () => {
      await request(`/certificates/${encodeURIComponent(id)}/revoke`, { method: "POST", body: { reason } });
      onChange((await request<ApplicationDetail>(path)).data);
    }, "Certificate revoked.");
  }

  function erase(id: string, code: string) {
    if (!window.confirm(`Delete certificate ${code} permanently? Its code will stop working. Use this for an erasure request; to withdraw a certificate, revoke it instead.`)) return;
    action.run(async () => {
      await request(`/certificates/${encodeURIComponent(id)}`, { method: "DELETE" });
      onChange((await request<ApplicationDetail>(path)).data);
    }, "Certificate deleted.");
  }

  return (
    <section className="form-card" aria-labelledby="certificate-heading">
      <h2 id="certificate-heading">Certificates</h2>
      <FormStatus state="success" message={action.saved} />
      <FormStatus state="error" message={action.error} />
      {app.certificates.length > 0 && (
        <ul className="admin-list">
          {app.certificates.map((c) => (
            <li key={c.id}>
              <strong>{c.code}</strong> <span className="status-pill" data-status={c.status === "valid" ? "active" : "failure"}>{c.status === "valid" ? "Valid" : "Revoked"}</span>
              <span className="admin-sub"> · completed {c.completed_on} · issued {formatDateTime(c.issued_at)}</span>
              {c.revoked_reason && <p className="admin-message">Revoked: {c.revoked_reason}</p>}
              <div>
                <button type="button" className="admin-link-button" onClick={() => download(c.id)} disabled={action.busy}>Download PDF</button>
                {" · "}<a href={c.verify_url} rel="noopener noreferrer" target="_blank">Public check</a>
                {administrator && c.status === "valid" && <> · <button type="button" className="admin-link-button" onClick={() => revoke(c.id, c.code)} disabled={action.busy}>Revoke</button></>}
                {administrator && <> · <button type="button" className="admin-link-button" onClick={() => erase(c.id, c.code)} disabled={action.busy}>Delete</button></>}
              </div>
            </li>
          ))}
        </ul>
      )}
      {manage && app.certificate_eligible && !hasValid && (
        <form className="contact-form" onSubmit={issue}>
          <p className="admin-help">The certificate shows the name, position, cohort and dates below. Anyone with its code can check it at careers.paxofi.com/verify. Check the spelling of the name first.</p>
          <FieldShell label="Start day (optional)" error={action.fields.started_on}>
            {(props) => <input name="started_on" type="date" {...props} />}
          </FieldShell>
          <FieldShell label="Day they finished" error={action.fields.completed_on}>
            {(props) => <input name="completed_on" type="date" required {...props} />}
          </FieldShell>
          <button className="button button--primary" type="submit" disabled={action.busy}>{action.busy ? "Issuing…" : "Issue certificate"}</button>
        </form>
      )}
      {!app.certificate_eligible && !hasValid && <p>Certificates are issued once the participant is Completed or Alumni.</p>}
    </section>
  );
}
