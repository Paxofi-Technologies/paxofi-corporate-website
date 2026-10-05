"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { CatalogForm, CatalogFormValues, toFormValues, toPayload, useMediaList } from "@/components/admin/CatalogForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, CATALOG_KINDS, CatalogDetail, formatDateTime } from "@/lib/admin-api";

/** Edit a product, service or industry: drafts, publishing, show/hide and earlier versions (D-011, D-022). */
export default function CatalogItemEditor({ type, id }: { type: string; id: string }) {
  const { user, request, apiBase } = useAdmin();
  const media = useMediaList();
  const kind = CATALOG_KINDS.find((k) => k.value === type);
  const [detail, setDetail] = useState<CatalogDetail | null>(null);
  const [values, setValues] = useState<CatalogFormValues | null>(null);
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const publisher = can(user, "content.publish");
  const base = kind ? `/catalog/${kind.value}/${encodeURIComponent(id)}` : "";

  const show = useCallback((data: CatalogDetail) => {
    setDetail(data);
    setValues(toFormValues(data.draft?.content ?? data.item.content));
  }, []);

  useEffect(() => {
    if (!kind || !can(user, "content.edit")) return;
    let cancelled = false;
    request<CatalogDetail>(base)
      .then((result) => !cancelled && show(result.data))
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "This item could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, kind, base, show]);

  if (!can(user, "content.edit") || !kind) return <NoAccess title="Edit content" />;

  async function run(action: () => Promise<{ data: CatalogDetail }>, done: string) {
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

  const saveDraft = () => request<CatalogDetail>(`${base}/draft`, { method: "POST", body: toPayload(values!) });

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    run(saveDraft, publisher ? "Draft saved. It is not on the website until you publish it." : "Draft saved. An administrator will publish it.");
  }

  function publish() {
    run(async () => {
      await saveDraft();
      return request<CatalogDetail>(`${base}/publish`, { method: "POST" });
    }, "Published. The website shows the new version now.");
  }

  if (!detail || !values) {
    return (
      <>
        <p><Link href="/admin/content">← Content</Link></p>
        <h1 className="admin-title">Edit content</h1>
        {error ? <FormStatus state="error" message={error} /> : <p role="status">Loading…</p>}
      </>
    );
  }

  const { item, draft, revisions } = detail;

  return (
    <>
      <p><Link href="/admin/content">← Content</Link></p>
      <h1 className="admin-title">{item.name}</h1>
      <p className="admin-intro">
        <span className="status-pill" data-status={item.visible ? "active" : "disabled"}>{item.visible ? "Shown on the website" : "Hidden from the website"}</span>{" "}
        {draft ? (
          <span>
            Unpublished changes saved {formatDateTime(draft.saved_at)}
            {draft.author_name ? ` by ${draft.author_name}` : ""}.
          </span>
        ) : (
          <span>No unpublished changes.</span>
        )}
      </p>
      <FormStatus state="success" message={notice} />

      <CatalogForm kind={kind.value} values={values} onChange={setValues} fields={fields} onSubmit={submit} media={media} apiBase={apiBase}>
        <FormStatus state="error" message={error} />
        <div className="actions admin-actions">
          <button className="button button--outline" type="submit" disabled={busy}>Save draft</button>
          {publisher && (
            <button className="button button--primary" type="button" onClick={publish} disabled={busy}>Publish</button>
          )}
          {draft && (
            <button className="button button--outline" type="button" disabled={busy} onClick={() => run(() => request<CatalogDetail>(`${base}/draft`, { method: "DELETE" }), "Draft discarded.")}>
              Discard draft
            </button>
          )}
        </div>
        {!publisher && <p className="form-note">An administrator publishes changes and decides what is shown on the website.</p>}
      </CatalogForm>

      {publisher && (
        <section className="form-card admin-panel-gap" aria-labelledby="visibility-heading">
          <h2 id="visibility-heading">On the website</h2>
          <p>{item.visible ? "Visitors can see this item." : "Visitors cannot see this item."}</p>
          <button
            className="button button--outline"
            type="button"
            disabled={busy}
            onClick={() =>
              run(
                () => request<CatalogDetail>(`${base}/visibility`, { method: "POST", body: { visible: !item.visible } }),
                item.visible ? "Hidden from the website." : "Shown on the website.",
              )
            }
          >
            {item.visible ? "Hide from website" : "Show on website"}
          </button>
        </section>
      )}

      <section className="form-card admin-panel-gap" aria-labelledby="history-heading">
        <h2 id="history-heading">Earlier versions</h2>
        {revisions.length === 0 ? (
          <p className="form-note">No earlier versions yet.</p>
        ) : (
          <ul className="admin-history">
            {revisions.map((revision) => (
              <li key={revision.id}>
                <div>
                  <strong>{revision.state === "created" ? "Created" : "Published"}</strong> {formatDateTime(revision.created_at)}
                  {revision.author_name ? ` by ${revision.author_name}` : ""}
                  <span className="admin-sub">{revision.content.name}: {revision.content.summary}</span>
                </div>
                <button
                  className="button button--outline"
                  type="button"
                  disabled={busy}
                  onClick={() => run(() => request<CatalogDetail>(`${base}/revisions/${revision.id}/restore`, { method: "POST" }), "Earlier version copied into the draft. Review it, then save or publish.")}
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
