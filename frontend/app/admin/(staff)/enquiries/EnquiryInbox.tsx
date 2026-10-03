"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, ENQUIRY_STATUSES, formatDateTime, statusLabel } from "@/lib/admin-api";

type Row = { id: string; name: string; email: string; company: string | null; excerpt: string; status: string; created_at: string };
type Meta = { page?: number; total?: number; total_pages?: number; counts?: Record<string, number> };

export default function EnquiryInbox() {
  const { user, request } = useAdmin();
  const [status, setStatus] = useState("new");
  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<Row[] | null>(null);
  const [meta, setMeta] = useState<Meta>({});
  const [error, setError] = useState("");

  useEffect(() => {
    if (!can(user, "enquiries.read")) return;
    let cancelled = false;
    const params = new URLSearchParams({ page: String(page), per_page: "25" });
    if (status) params.set("status", status);
    if (query) params.set("q", query);
    request<Row[]>(`/enquiries?${params}`)
      .then((result) => {
        if (cancelled) return;
        setRows(result.data);
        setMeta(result.meta as Meta);
        setError("");
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "Enquiries could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, status, query, page]);

  if (!can(user, "enquiries.read")) return <NoAccess title="Enquiries" />;

  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setQuery(String(new FormData(event.currentTarget).get("q") ?? "").trim());
    setPage(1);
  }

  const counts = meta.counts ?? {};
  const totalPages = meta.total_pages ?? 1;

  return (
    <>
      <h1 className="admin-title">Enquiries</h1>
      <p className="admin-intro">Messages sent through the contact form. Reply within 2 business days (D-007).</p>

      <div className="admin-toolbar">
        <div className="admin-tabs" role="group" aria-label="Filter by status">
          {[{ value: "", label: "All" }, ...ENQUIRY_STATUSES].map((option) => (
            <button
              key={option.value || "all"}
              type="button"
              className="admin-tab"
              aria-pressed={status === option.value}
              onClick={() => {
                setStatus(option.value);
                setPage(1);
              }}
            >
              {option.label}
              {option.value && <span className="admin-count">{counts[option.value] ?? 0}</span>}
            </button>
          ))}
        </div>
        <form role="search" className="admin-search" onSubmit={search}>
          <label>
            <span className="visually-hidden">Search name, email or company</span>
            <input name="q" type="search" maxLength={100} placeholder="Search name, email or company" defaultValue={query} />
          </label>
          <button className="button button--outline" type="submit">Search</button>
        </form>
      </div>

      <FormStatus state="error" message={error} />

      {rows === null ? (
        <p role="status">Loading enquiries…</p>
      ) : rows.length === 0 ? (
        <p className="admin-empty">No enquiries{status ? ` marked “${statusLabel(status)}”` : ""}{query ? ` matching “${query}”` : ""}.</p>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <caption className="visually-hidden">Enquiries, newest first</caption>
            <thead>
              <tr>
                <th scope="col">From</th>
                <th scope="col">Message</th>
                <th scope="col">Status</th>
                <th scope="col">Received</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    <Link href={`/admin/enquiries/${row.id}`} className="admin-strong-link">{row.name}</Link>
                    <span className="admin-sub">{row.email}{row.company ? ` · ${row.company}` : ""}</span>
                  </td>
                  <td className="admin-excerpt">{row.excerpt}</td>
                  <td><span className="status-pill" data-status={row.status}>{statusLabel(row.status)}</span></td>
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
