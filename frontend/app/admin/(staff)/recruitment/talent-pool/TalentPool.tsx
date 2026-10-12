"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, type TalentPoolEntry } from "@/lib/admin-api";

type Meta = { page?: number; total_pages?: number; total?: number };

/** People who agreed to be contacted about future programmes (D-029). */
export default function TalentPool() {
  const { user, request } = useAdmin();
  const [query, setQuery] = useState("");
  const [engagement, setEngagement] = useState("");
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<TalentPoolEntry[] | null>(null);
  const [meta, setMeta] = useState<Meta>({});
  const [error, setError] = useState("");
  const [saved, setSaved] = useState("");
  const [reload, setReload] = useState(0);

  useEffect(() => {
    if (!can(user, "recruitment.read")) return;
    let cancelled = false;
    const params = new URLSearchParams({ page: String(page) });
    if (query) params.set("q", query);
    if (engagement) params.set("engagement", engagement);
    request<TalentPoolEntry[]>(`/talent-pool?${params}`)
      .then((result) => {
        if (cancelled) return;
        setRows(result.data);
        setMeta(result.meta as Meta);
        setError("");
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "The talent pool could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, page, query, engagement, reload]);

  if (!can(user, "recruitment.read")) return <NoAccess title="Talent pool" />;
  const manage = can(user, "recruitment.manage");
  const totalPages = meta.total_pages ?? 1;

  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setQuery(String(new FormData(event.currentTarget).get("q") ?? "").trim());
    setPage(1);
  }

  async function withdraw(entry: TalentPoolEntry) {
    if (!window.confirm(`Remove ${entry.full_name} from the talent pool? Their consent is withdrawn and nothing is kept.`)) return;
    setError("");
    setSaved("");
    try {
      await request(`/talent-pool/${encodeURIComponent(entry.id)}`, { method: "DELETE" });
      setSaved(`${entry.full_name} was removed from the talent pool.`);
      setReload((n) => n + 1);
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "That could not be saved.");
    }
  }

  return (
    <>
      <p><Link href="/admin/recruitment">← All applications</Link></p>
      <h1 className="admin-title">Talent pool</h1>
      <p className="admin-intro">
        People who were not selected, declined or withdrew, and agreed to be contacted about future programmes. Consent lasts 24 months; each entry is then deleted automatically. To add someone, open their application. Contact them by email from hr@paxofi.com, and never share this list.
      </p>
      <div className="admin-toolbar">
        <div className="admin-search">
          <label>
            <span className="visually-hidden">Engagement type</span>
            <select value={engagement} onChange={(e) => { setEngagement(e.target.value); setPage(1); }}>
              <option value="">Fellows, Interns and Volunteers</option>
              <option value="fellow">Fellows</option>
              <option value="intern">Interns</option>
              <option value="volunteer">Volunteers</option>
            </select>
          </label>
        </div>
        <form role="search" className="admin-search" onSubmit={search}>
          <label>
            <span className="visually-hidden">Search name, email, role or reference</span>
            <input name="q" type="search" maxLength={100} placeholder="Search name, email or role" defaultValue={query} />
          </label>
          <button className="button button--outline" type="submit">Search</button>
        </form>
      </div>
      <FormStatus state="success" message={saved} />
      <FormStatus state="error" message={error} />
      {rows === null ? (
        <p role="status">Loading the talent pool…</p>
      ) : rows.length === 0 ? (
        <p className="admin-empty">{query || engagement ? "No one matches that search." : "No one is in the talent pool yet."}</p>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <caption className="visually-hidden">Talent pool, newest first</caption>
            <thead>
              <tr>
                <th scope="col">Candidate</th>
                <th scope="col">Applied for</th>
                <th scope="col">Consent</th>
                {manage && <th scope="col"><span className="visually-hidden">Actions</span></th>}
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    {row.application_id ? <Link href={`/admin/recruitment/${row.application_id}`} className="admin-strong-link">{row.full_name}</Link> : <strong>{row.full_name}</strong>}
                    <span className="admin-sub"><a href={`mailto:${row.email}`}>{row.email}</a>{row.location ? ` · ${row.location}` : ""}</span>
                    {row.note && <span className="admin-sub">{row.note}</span>}
                  </td>
                  <td>
                    {row.role_title}
                    <span className="admin-sub">PIF {row.engagement_label}{row.reference ? ` · ${row.reference}` : ""}</span>
                  </td>
                  <td>
                    {row.consent_given_on}
                    <span className="admin-sub">{row.consent_method_label}</span>
                    <span className="admin-sub">Lapses {row.consent_expires_on}{row.expiring_soon ? " (soon)" : ""}</span>
                  </td>
                  {manage && <td><button type="button" className="admin-link-button" onClick={() => withdraw(row)}>Consent withdrawn: remove</button></td>}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {totalPages > 1 && (
        <nav className="admin-pager" aria-label="Pages">
          <button type="button" className="button button--outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</button>
          <span>Page {page} of {totalPages}</span>
          <button type="button" className="button button--outline" disabled={page >= totalPages} onClick={() => setPage(page + 1)}>Next</button>
        </nav>
      )}
    </>
  );
}
