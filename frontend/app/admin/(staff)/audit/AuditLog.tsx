"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, formatDateTime } from "@/lib/admin-api";

type Event = {
  id: string;
  action: string;
  outcome: string;
  target_type: string | null;
  target_id: string | null;
  request_id: string | null;
  created_at: string;
  actor_name: string | null;
  actor_email: string | null;
};

const FILTERS = [
  { value: "", label: "All activity" },
  { value: "staff.sign_in", label: "Sign-ins" },
  { value: "staff.", label: "Staff accounts" },
  { value: "enquiry.", label: "Enquiries" },
  { value: "data_retention.", label: "Data retention" },
];

export default function AuditLog() {
  const { user, request } = useAdmin();
  const [filter, setFilter] = useState("");
  const [page, setPage] = useState(1);
  const [events, setEvents] = useState<Event[] | null>(null);
  const [totalPages, setTotalPages] = useState(1);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!can(user, "audit.read")) return;
    let cancelled = false;
    const params = new URLSearchParams({ page: String(page), per_page: "50" });
    if (filter) params.set("action", filter);
    request<Event[]>(`/audit?${params}`)
      .then((result) => {
        if (cancelled) return;
        setEvents(result.data);
        setTotalPages(Number(result.meta.total_pages) || 1);
        setError("");
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "The audit log could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, filter, page]);

  if (!can(user, "audit.read")) return <NoAccess title="Audit log" />;

  return (
    <>
      <h1 className="admin-title">Audit log</h1>
      <p className="admin-intro">Who did what, newest first. Entries are kept for 24 months (D-008).</p>
      <div className="admin-toolbar">
        <label className="admin-inline-label">
          Show
          <select
            value={filter}
            onChange={(event) => {
              setFilter(event.target.value);
              setPage(1);
            }}
          >
            {FILTERS.map((f) => (
              <option key={f.value} value={f.value}>{f.label}</option>
            ))}
          </select>
        </label>
      </div>
      <FormStatus state="error" message={error} />
      {events === null ? (
        <p role="status">Loading the audit log…</p>
      ) : events.length === 0 ? (
        <p className="admin-empty">No activity recorded yet.</p>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <caption className="visually-hidden">Audit events, newest first</caption>
            <thead>
              <tr>
                <th scope="col">When</th>
                <th scope="col">Who</th>
                <th scope="col">Action</th>
                <th scope="col">Outcome</th>
                <th scope="col">Target</th>
              </tr>
            </thead>
            <tbody>
              {events.map((event) => (
                <tr key={event.id}>
                  <td>{formatDateTime(event.created_at)}</td>
                  <td>{event.actor_name ?? (event.actor_email || "Website visitor or system")}</td>
                  <td className="admin-mono">{event.action}</td>
                  <td><span className="status-pill" data-status={event.outcome}>{event.outcome}</span></td>
                  <td>
                    {event.target_type === "enquiry" && event.target_id ? (
                      <Link href={`/admin/enquiries/${event.target_id}`}>Enquiry</Link>
                    ) : event.target_type ? (
                      <span>{event.target_type === "user" ? "User" : event.target_type}</span>
                    ) : (
                      "—"
                    )}
                  </td>
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
