"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, type ApplicationRow, type StageOption, formatDateTime } from "@/lib/admin-api";

type Meta = { page?: number; total_pages?: number; counts?: Record<string, number>; stages?: StageOption[]; roles?: { id: string; title: string }[] };

/** Applications from careers.paxofi.com by stage, role or search (D-019). */
export default function ApplicationList() {
  const { user, request } = useAdmin();
  const [stage, setStage] = useState("");
  const [role, setRole] = useState("");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<ApplicationRow[] | null>(null);
  const [meta, setMeta] = useState<Meta>({});
  const [error, setError] = useState("");

  useEffect(() => {
    if (!can(user, "recruitment.read")) return;
    let cancelled = false;
    const params = new URLSearchParams({ page: String(page) });
    if (stage) params.set("stage", stage);
    if (role) params.set("role", role);
    if (query) params.set("q", query);
    request<ApplicationRow[]>(`/applications?${params}`)
      .then((result) => {
        if (cancelled) return;
        setRows(result.data);
        setMeta(result.meta as Meta);
        setError("");
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "Applications could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, stage, role, query, page]);

  if (!can(user, "recruitment.read")) return <NoAccess title="Recruitment" />;

  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setQuery(String(new FormData(event.currentTarget).get("q") ?? "").trim());
    setPage(1);
  }

  const counts = meta.counts ?? {};
  const stages = meta.stages ?? [];
  const totalPages = meta.total_pages ?? 1;
  const all = Object.values(counts).reduce((sum, n) => sum + n, 0);

  return (
    <>
      <h1 className="admin-title">Recruitment</h1>
      <p className="admin-intro">
        Applications from careers.paxofi.com. Acknowledge within 2 working days and finish screening within 2 weeks. Applications and CVs are deleted automatically 12 months after they close.
        {can(user, "careers.edit") && <> <Link href="/admin/recruitment/roles">Edit the roles on careers.paxofi.com</Link>.</>}
      </p>

      <div className="admin-toolbar">
        <div className="admin-tabs" role="group" aria-label="Filter by stage">
          <button type="button" className="admin-tab" aria-pressed={stage === ""} onClick={() => { setStage(""); setPage(1); }}>
            All<span className="admin-count">{all}</span>
          </button>
          {stages.filter((s) => (counts[s.value] ?? 0) > 0 || s.value === "applied").map((s) => (
            <button key={s.value} type="button" className="admin-tab" aria-pressed={stage === s.value} onClick={() => { setStage(s.value); setPage(1); }}>
              {s.label}<span className="admin-count">{counts[s.value] ?? 0}</span>
            </button>
          ))}
        </div>
        <div className="admin-search">
          <label>
            <span className="visually-hidden">Role</span>
            <select value={role} onChange={(e) => { setRole(e.target.value); setPage(1); }}>
              <option value="">All roles</option>
              {(meta.roles ?? []).map((r) => <option key={r.id} value={r.id}>{r.title}</option>)}
            </select>
          </label>
        </div>
        <form role="search" className="admin-search" onSubmit={search}>
          <label>
            <span className="visually-hidden">Search name, email or reference</span>
            <input name="q" type="search" maxLength={100} placeholder="Search name, email or reference" defaultValue={query} />
          </label>
          <button className="button button--outline" type="submit">Search</button>
        </form>
      </div>

      <FormStatus state="error" message={error} />

      {rows === null ? (
        <p role="status">Loading applications…</p>
      ) : rows.length === 0 ? (
        <p className="admin-empty">No applications{query ? ` matching “${query}”` : ""} here yet.</p>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <caption className="visually-hidden">Applications, newest first</caption>
            <thead>
              <tr>
                <th scope="col">Candidate</th>
                <th scope="col">Role</th>
                <th scope="col">Stage</th>
                <th scope="col">Scores</th>
                <th scope="col">Received</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    <Link href={`/admin/recruitment/${row.id}`} className="admin-strong-link">{row.full_name}</Link>
                    <span className="admin-sub">{row.reference} · {row.email}{row.has_cv ? " · CV" : ""}</span>
                  </td>
                  <td>{row.role_title}</td>
                  <td><span className="status-pill" data-status={row.stage}>{row.stage_label}</span></td>
                  <td className="admin-small">
                    {row.evidence_total === null ? "—" : `Evidence ${row.evidence_total}/35`}
                    {row.interview_total !== null && <><br />Interview {row.interview_total}/35</>}
                  </td>
                  <td>{formatDateTime(row.created_at)}</td>
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
