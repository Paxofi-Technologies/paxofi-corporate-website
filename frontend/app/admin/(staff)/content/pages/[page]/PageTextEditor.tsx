"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FieldShell, FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, PageTextDetail, PageTextField, formatDateTime } from "@/lib/admin-api";

/** Edit one page's wording: drafts, publishing and earlier versions, as for products (D-011, D-015). */
export default function PageTextEditor({ page }: { page: string }) {
  const { user, request } = useAdmin();
  const [detail, setDetail] = useState<PageTextDetail | null>(null);
  const [values, setValues] = useState<Record<string, string>>({});
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const publisher = can(user, "content.publish");
  const base = `/pages/${encodeURIComponent(page)}`;

  const show = useCallback((data: PageTextDetail) => {
    setDetail(data);
    const current = data.draft?.fields ?? data.live?.fields ?? {};
    setValues(Object.fromEntries(data.fields.map((field) => [field.key, current[field.key] ?? field.default])));
  }, []);

  useEffect(() => {
    if (!can(user, "content.edit")) return;
    let cancelled = false;
    request<PageTextDetail>(base)
      .then((result) => !cancelled && show(result.data))
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "This page could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, base, show]);

  if (!can(user, "content.edit")) return <NoAccess title="Edit page text" />;

  async function run(action: () => Promise<{ data: PageTextDetail }>, done: string) {
    setBusy(true);
    setFields({});
    setError("");
    setNotice("");
    try {
      const result = await action();
      show(result.data);
      setNotice(done);
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(e.message);
      } else {
        setError("That did not work. Please try again.");
      }
    } finally {
      setBusy(false);
    }
  }

  const saveDraft = () => request<PageTextDetail>(`${base}/draft`, { method: "POST", body: { fields: values } });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    run(saveDraft, publisher ? "Draft saved. It is not on the website until you publish it." : "Draft saved. An administrator will publish it.");
  }

  function publish() {
    run(async () => {
      await saveDraft();
      return request<PageTextDetail>(`${base}/publish`, { method: "POST" });
    }, "Published. The website shows the new text within a minute.");
  }

  if (!detail) {
    return (
      <>
        <p><Link href="/admin/content?tab=pages">← Content</Link></p>
        <h1 className="admin-title">Edit page text</h1>
        {error ? <FormStatus state="error" message={error} /> : <p role="status">Loading…</p>}
      </>
    );
  }

  const groups = [...new Set(detail.fields.map((field) => field.group))];
  const { draft, live, revisions } = detail;

  return (
    <>
      <p><Link href="/admin/content?tab=pages">← Content</Link></p>
      <h1 className="admin-title">{detail.label} page text</h1>
      <p className="admin-intro">
        {live ? `Edited text published ${formatDateTime(live.published_at)}.` : "The website shows the original wording."}{" "}
        {draft ? `Unpublished changes saved ${formatDateTime(draft.saved_at)}${draft.author_name ? ` by ${draft.author_name}` : ""}.` : "No unpublished changes."}{" "}
        <a href={detail.path} target="_blank" rel="noopener">View the page</a>
      </p>
      <FormStatus state="success" message={notice} />

      <form className="contact-form form-card" onSubmit={submit} noValidate>
        {groups.map((group) => (
          <fieldset key={group} className="admin-fieldset">
            <legend>{group}</legend>
            {detail.fields
              .filter((field) => field.group === group)
              .map((field) => (
                <TextField
                  key={field.key}
                  field={field}
                  value={values[field.key] ?? ""}
                  error={fields[field.key]}
                  onChange={(value) => setValues((current) => ({ ...current, [field.key]: value }))}
                />
              ))}
          </fieldset>
        ))}
        <FormStatus state="error" message={error} />
        <div className="actions admin-actions">
          <button className="button button--outline" type="submit" disabled={busy}>Save draft</button>
          {publisher && <button className="button button--primary" type="button" onClick={publish} disabled={busy}>Publish</button>}
          {draft && (
            <button className="button button--outline" type="button" disabled={busy} onClick={() => run(() => request<PageTextDetail>(`${base}/draft`, { method: "DELETE" }), "Draft discarded.")}>
              Discard draft
            </button>
          )}
        </div>
        {!publisher && <p className="form-note">An administrator publishes changes.</p>}
      </form>

      <section className="form-card admin-panel-gap" aria-labelledby="history-heading">
        <h2 id="history-heading">Earlier versions</h2>
        {revisions.length === 0 ? (
          <p className="form-note">No published versions yet. The original wording is always available with “Use original wording”.</p>
        ) : (
          <ul className="admin-history">
            {revisions.map((revision) => (
              <li key={revision.id}>
                <div>
                  <strong>Published</strong> {formatDateTime(revision.created_at)}
                  {revision.author_name ? ` by ${revision.author_name}` : ""}
                </div>
                <button
                  className="button button--outline"
                  type="button"
                  disabled={busy}
                  onClick={() => run(() => request<PageTextDetail>(`${base}/revisions/${revision.id}/restore`, { method: "POST" }), "Earlier version copied into the draft. Review it, then save or publish.")}
                >
                  Restore as draft
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>
    </>
  );
}

function TextField({ field, value, error, onChange }: { field: PageTextField; value: string; error?: string; onChange: (value: string) => void }) {
  const over = value.length > field.max;
  const changed = value !== field.default;
  return (
    <FieldShell label={field.label} error={error ?? (over ? `Keep this to ${field.max} characters.` : undefined)} hint={`${value.length} of ${field.max} characters`}>
      {(props) => (
        <>
          {field.kind === "text" ? (
            <textarea {...props} name={field.key} rows={3} value={value} required onChange={(e) => onChange(e.target.value)} />
          ) : (
            <input {...props} name={field.key} type="text" value={value} required onChange={(e) => onChange(e.target.value)} />
          )}
          {changed && (
            <button type="button" className="admin-link-button" onClick={() => onChange(field.default)}>
              Use original wording
            </button>
          )}
        </>
      )}
    </FieldShell>
  );
}
