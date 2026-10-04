"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FieldShell, FormStatus, formValues } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, type CareerRoleAdmin, type CareerRoleState, formatDateTime } from "@/lib/admin-api";

const STATE_LABELS: Record<CareerRoleState, string> = { draft: "Draft (hidden)", published: "Open for applications", closed: "Closed (hidden)" };
const LISTS = [
  { key: "responsibilities", label: "What they will do", hint: "One per line, up to 12. At least one." },
  { key: "deliverables", label: "What they will produce", hint: "One per line, up to 10." },
  { key: "competencies", label: "Skills we look for", hint: "One per line, up to 14." },
  { key: "tools", label: "Tools they may use", hint: "One per line, up to 14. Leave empty if none." },
] as const;

/** The roles on careers.paxofi.com (D-018): drafts are hidden; open roles accept applications. */
export default function CareerRoles() {
  const { user, request } = useAdmin();
  const [roles, setRoles] = useState<CareerRoleAdmin[] | null>(null);
  const [editing, setEditing] = useState<CareerRoleAdmin | "new" | null>(null);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    return request<CareerRoleAdmin[]>("/career-roles")
      .then((result) => setRoles(result.data))
      .catch((e) => setError(e instanceof AdminApiError ? e.message : "The roles could not be loaded."));
  }, [request]);

  useEffect(() => {
    if (can(user, "careers.edit")) load();
  }, [user, load]);

  if (!can(user, "careers.edit")) return <NoAccess title="Careers roles" />;

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values: Record<string, unknown> = formValues(event.currentTarget);
    values.sort_order = Number(values.sort_order) || 100;
    setBusy(true);
    setError("");
    setSaved("");
    setFields({});
    try {
      if (editing === "new") {
        await request<CareerRoleAdmin>("/career-roles", { method: "POST", body: values });
        setSaved(`“${values.title}” was created as a draft. Open it for applications when it is ready.`);
      } else if (editing) {
        await request<CareerRoleAdmin>(`/career-roles/${editing.id}`, { method: "PATCH", body: values });
        setSaved(`“${values.title}” was saved.`);
      }
      setEditing(null);
      await load();
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "The role could not be saved.");
      if (e instanceof AdminApiError) setFields(e.fields);
    } finally {
      setBusy(false);
    }
  }

  async function setState(role: CareerRoleAdmin, state: CareerRoleState) {
    setBusy(true);
    setError("");
    setSaved("");
    try {
      await request(`/career-roles/${role.id}/state`, { method: "POST", body: { state } });
      setSaved(`“${role.title}” is now ${STATE_LABELS[state].toLowerCase()}.`);
      await load();
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "The role could not be changed.");
    } finally {
      setBusy(false);
    }
  }

  const current = editing === "new" ? null : editing;

  return (
    <>
      <p><Link href="/admin/recruitment">← Recruitment</Link></p>
      <h1 className="admin-title">Careers roles</h1>
      <p className="admin-intro">
        The roles shown on careers.paxofi.com. A new role starts as a hidden draft. Its web address is set from the first title and never changes, so shared links keep working. Closing a role hides it; its applications stay.
      </p>
      <FormStatus state="success" message={saved} />
      <FormStatus state="error" message={error} />

      {editing ? (
        <form className="contact-form form-card" onSubmit={save} key={current?.id ?? "new"}>
          <h2>{current ? `Edit ${current.title}` : "New role"}</h2>
          <FieldShell label="Title" error={fields.title}>{(p) => <input name="title" maxLength={80} required defaultValue={current?.title} {...p} />}</FieldShell>
          <div className="admin-row3">
            <FieldShell label="Code" hint="2 to 4 letters, for example SE." error={fields.code}>{(p) => <input name="code" maxLength={4} required defaultValue={current?.code} {...p} />}</FieldShell>
            <FieldShell label="Role family" error={fields.family}>{(p) => <input name="family" maxLength={80} required defaultValue={current?.family} {...p} />}</FieldShell>
            <FieldShell label="Display order" hint="Lower numbers come first." error={fields.sort_order}>{(p) => <input name="sort_order" type="number" min={0} max={9999} defaultValue={current?.sort_order ?? 100} {...p} />}</FieldShell>
          </div>
          <FieldShell label="Summary (on the roles list)" error={fields.summary}>{(p) => <textarea name="summary" rows={2} maxLength={300} required defaultValue={current?.summary} {...p} />}</FieldShell>
          <FieldShell label="Purpose (top of the role page)" error={fields.purpose}>{(p) => <textarea name="purpose" rows={3} maxLength={600} required defaultValue={current?.purpose} {...p} />}</FieldShell>
          {LISTS.map((list) => (
            <FieldShell key={list.key} label={list.label} hint={list.hint} error={fields[list.key]}>
              {(p) => <textarea name={list.key} rows={5} defaultValue={current?.[list.key].join("\n")} {...p} />}
            </FieldShell>
          ))}
          <FieldShell label="What to show us (evidence)" error={fields.evidence}>{(p) => <textarea name="evidence" rows={2} maxLength={400} defaultValue={current?.evidence} {...p} />}</FieldShell>
          <FieldShell label="Assessment" error={fields.assessment}>{(p) => <input name="assessment" maxLength={400} defaultValue={current?.assessment} {...p} />}</FieldShell>
          <FieldShell label="Interview focus" error={fields.interview}>{(p) => <input name="interview" maxLength={400} defaultValue={current?.interview} {...p} />}</FieldShell>
          <div className="admin-actions">
            <button className="button button--primary" type="submit" disabled={busy}>{busy ? "Saving…" : current ? "Save role" : "Create draft"}</button>
            <button className="button button--outline" type="button" onClick={() => setEditing(null)}>Cancel</button>
          </div>
        </form>
      ) : (
        <p><button type="button" className="button button--primary" onClick={() => { setEditing("new"); setSaved(""); }}>New role</button></p>
      )}

      {roles === null ? (
        <p role="status">Loading roles…</p>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <caption className="visually-hidden">Roles in display order</caption>
            <thead>
              <tr>
                <th scope="col">Role</th>
                <th scope="col">Status</th>
                <th scope="col">Updated</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody>
              {roles.map((role) => (
                <tr key={role.id}>
                  <td>
                    <strong>{role.title}</strong>
                    <span className="admin-sub">{role.code} · {role.family} · /roles/{role.slug}</span>
                  </td>
                  <td><span className="status-pill" data-status={role.state}>{STATE_LABELS[role.state]}</span></td>
                  <td>{formatDateTime(role.updated_at)}</td>
                  <td>
                    <div className="admin-actions">
                      <button type="button" className="admin-link-button" onClick={() => { setEditing(role); setSaved(""); }}>Edit</button>
                      {role.state !== "published" && <button type="button" className="admin-link-button" disabled={busy} onClick={() => setState(role, "published")}>Open for applications</button>}
                      {role.state === "published" && <button type="button" className="admin-link-button" disabled={busy} onClick={() => setState(role, "closed")}>Close</button>}
                      {role.state === "closed" && <button type="button" className="admin-link-button" disabled={busy} onClick={() => setState(role, "draft")}>Back to draft</button>}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
