"use client";

import { useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus } from "@/components/admin/AdminForm";
import { AdminApiError, type RedirectItem, formatDateTime } from "@/lib/admin-api";

/**
 * Redirects for retired or renamed pages (D-025): a visitor who opens the old
 * address is sent on to the new one, and search engines update their links.
 * Administrators add and remove them; editors can see them.
 */
export default function RedirectList() {
  const { user, request } = useAdmin();
  const [rows, setRows] = useState<RedirectItem[] | null>(null);
  const [error, setError] = useState("");
  const [status, setStatus] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState("");
  const [busy, setBusy] = useState(false);
  const publisher = can(user, "content.publish");

  useEffect(() => {
    let cancelled = false;
    request<RedirectItem[]>("/redirects")
      .then((result) => !cancelled && setRows(result.data))
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "Redirects could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [request]);

  if (error) return <FormStatus state="error" message={error} />;
  if (rows === null) return <p role="status">Loading…</p>;

  const add = async (form: HTMLFormElement) => {
    const data = new FormData(form);
    const note = String(data.get("note") ?? "").trim();
    setBusy(true);
    setFields({});
    setFormError("");
    setStatus("");
    try {
      const result = await request<RedirectItem>("/redirects", { method: "POST", body: { from: data.get("from"), to: data.get("to"), note: note === "" ? null : note } });
      setRows((current) => [...(current ?? []), result.data].sort((a, b) => a.from.localeCompare(b.from)));
      form.reset();
      setStatus(`Visitors to ${result.data.from} now go to ${result.data.to}. The website picks this up within a minute.`);
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setFormError(e.message);
      } else {
        setFormError("The redirect could not be saved.");
      }
    } finally {
      setBusy(false);
    }
  };

  const remove = async (row: RedirectItem) => {
    if (!window.confirm(`Delete the redirect from ${row.from}? Visitors to that address will see "Page not found" again.`)) return;
    setStatus("");
    try {
      await request(`/redirects/${row.id}`, { method: "DELETE" });
      setRows((current) => (current ?? []).filter((r) => r.id !== row.id));
      setStatus(`Redirect from ${row.from} deleted.`);
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "The redirect could not be deleted.");
    }
  };

  return (
    <>
      <p className="admin-intro">
        When you retire or rename a page, add a redirect from its old address so links and search results still work. Use it for pages that no longer exist, such as a
        hidden article or industry, or an old address from a previous website.
      </p>
      {publisher ? (
        <form
          className="contact-form redirect-form"
          aria-labelledby="redirect-form-title"
          onSubmit={(event) => {
            event.preventDefault();
            add(event.currentTarget);
          }}
        >
          <h2 id="redirect-form-title" className="admin-card-title">Add a redirect</h2>
          <Field name="from" label="Old address" hint="On this website, starting with /, for example /insights/old-article." error={fields.from} required autoComplete="off" spellCheck={false} />
          <Field name="to" label="Send visitors to" hint="Another address on this website starting with /, or a full link starting with https://." error={fields.to} required autoComplete="off" spellCheck={false} />
          <Field name="note" label="Note (optional)" hint="Why it was added, for whoever looks at it later." error={fields.note} autoComplete="off" />
          <FormStatus state="error" message={formError} />
          <button type="submit" className="button button--primary" disabled={busy}>{busy ? "Saving…" : "Add redirect"}</button>
        </form>
      ) : (
        <p className="admin-sub">Only administrators can add or delete redirects.</p>
      )}
      <FormStatus state="success" message={status} />
      {rows.length === 0 ? (
        <p className="admin-empty">No redirects yet.</p>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <caption className="visually-hidden">Redirects by old address</caption>
            <thead>
              <tr>
                <th scope="col">Old address</th>
                <th scope="col">Goes to</th>
                <th scope="col">Added</th>
                {publisher && <th scope="col"><span className="visually-hidden">Actions</span></th>}
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    <code className="admin-mono">{row.from}</code>
                    {row.note && <span className="admin-sub">{row.note}</span>}
                  </td>
                  <td><code className="admin-mono">{row.to}</code></td>
                  <td>
                    {formatDateTime(row.created_at)}
                    {row.created_by && <span className="admin-sub">by {row.created_by}</span>}
                  </td>
                  {publisher && (
                    <td>
                      <button type="button" className="button button--outline button--small" onClick={() => remove(row)}>
                        Delete<span className="visually-hidden"> redirect from {row.from}</span>
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
