"use client";

import { useEffect, useMemo, useState } from "react";
import { useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { AdminApiError, REVIEW_STATUSES, type ReviewItem, type ReviewStatus, formatDateTime, formatDay } from "@/lib/admin-api";

type Filter = "all" | "attention" | "none" | "ok";
const FILTERS: { value: Filter; label: string }[] = [
  { value: "all", label: "Everything" },
  { value: "attention", label: "Overdue or due soon" },
  { value: "none", label: "No review date" },
  { value: "ok", label: "Up to date" },
];

function matches(filter: Filter, status: ReviewStatus): boolean {
  if (filter === "all") return true;
  if (filter === "attention") return status === "overdue" || status === "due";
  return status === filter;
}

/**
 * Review dates for everything on the website (D-025): check an item is still
 * correct, then mark it reviewed so the next date moves on, or set a date.
 */
export default function ReviewList() {
  const { request } = useAdmin();
  const [rows, setRows] = useState<ReviewItem[] | null>(null);
  const [today, setToday] = useState("");
  const [filter, setFilter] = useState<Filter>("all");
  const [error, setError] = useState("");
  const [status, setStatus] = useState("");

  useEffect(() => {
    let cancelled = false;
    request<ReviewItem[]>("/reviews")
      .then((result) => {
        if (cancelled) return;
        setRows(result.data);
        setToday(typeof result.meta.today === "string" ? result.meta.today : "");
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "Review dates could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [request]);

  const counts = useMemo(() => {
    const result: Record<ReviewStatus, number> = { overdue: 0, due: 0, none: 0, ok: 0 };
    for (const row of rows ?? []) result[row.status]++;
    return result;
  }, [rows]);

  if (error) return <FormStatus state="error" message={error} />;
  if (rows === null) return <p role="status">Loading…</p>;

  const replace = (item: ReviewItem, message: string) => {
    setRows((current) => (current ?? []).map((row) => (row.type === item.type && row.key === item.key ? item : row)));
    setStatus(message);
  };
  const shown = rows.filter((row) => matches(filter, row.status));

  return (
    <>
      <p className="admin-intro">
        Everything shown on the website, with the date it should next be checked. Open an item, check it is still correct, then mark it reviewed. Administrators get
        an email once a week while anything is overdue or due within two weeks.
      </p>
      <ul className="review-summary" aria-label="Review dates">
        {(Object.keys(REVIEW_STATUSES) as ReviewStatus[]).map((key) => (
          <li key={key}>
            <span className="status-pill" data-status={REVIEW_STATUSES[key].pill}>{REVIEW_STATUSES[key].label}</span> <strong>{counts[key]}</strong>
          </li>
        ))}
      </ul>
      <div className="admin-toolbar">
        <label className="review-filter">
          Show{" "}
          <select value={filter} onChange={(event) => setFilter(event.target.value as Filter)}>
            {FILTERS.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
        </label>
      </div>
      <FormStatus state="success" message={status} />
      {shown.length === 0 ? (
        <p className="admin-empty">Nothing to show here.</p>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table review-table">
            <caption className="visually-hidden">Content and its review dates, overdue first</caption>
            <thead>
              <tr>
                <th scope="col">Item</th>
                <th scope="col">Review by</th>
                <th scope="col">Last reviewed</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody>
              {shown.map((row) => (
                <ReviewRow key={`${row.type}:${row.key}`} row={row} today={today} onSaved={replace} />
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}

function ReviewRow({ row, today, onSaved }: { row: ReviewItem; today: string; onSaved: (item: ReviewItem, message: string) => void }) {
  const { request } = useAdmin();
  const [months, setMonths] = useState("6");
  const [date, setDate] = useState(row.review_by ?? "");
  const [note, setNote] = useState(row.note ?? "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const name = `${row.type_label}: ${row.title}`;

  const save = async (body: Record<string, unknown>, message: string) => {
    setBusy(true);
    setError("");
    try {
      const result = await request<ReviewItem>(`/reviews/${row.type}/${encodeURIComponent(row.key)}`, { method: "POST", body });
      setDate(result.data.review_by ?? "");
      onSaved(result.data, message);
    } catch (e) {
      setError(e instanceof AdminApiError ? Object.values(e.fields)[0] ?? e.message : "The review could not be saved.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <tr>
      <td>
        <span className="admin-strong">{row.title}</span>
        <span className="admin-sub">
          {row.type_label}
          {row.path && (
            <>
              {" · "}
              <a href={row.path} target="_blank" rel="noopener">View<span className="visually-hidden"> {row.title} on the website (opens in a new tab)</span></a>
            </>
          )}
        </span>
        {row.note && <span className="admin-sub">Note: {row.note}</span>}
      </td>
      <td>
        <span className="status-pill" data-status={REVIEW_STATUSES[row.status].pill}>{REVIEW_STATUSES[row.status].label}</span>
        <span className="admin-sub">{formatDay(row.review_by)}</span>
      </td>
      <td>
        {row.last_reviewed_at ? formatDateTime(row.last_reviewed_at) : "Never"}
        {row.last_reviewed_by && <span className="admin-sub">by {row.last_reviewed_by}</span>}
      </td>
      <td className="review-actions">
        <div className="review-mark">
          <label>
            <span className="visually-hidden">Next review of {name}</span>
            <select value={months} onChange={(event) => setMonths(event.target.value)} disabled={busy}>
              <option value="3">Next in 3 months</option>
              <option value="6">Next in 6 months</option>
              <option value="12">Next in 12 months</option>
            </select>
          </label>
          <button
            type="button"
            className="button button--primary button--small"
            disabled={busy}
            onClick={() => save({ action: "reviewed", months: Number(months), note: note.trim() === "" ? null : note }, `${row.title} marked reviewed.`)}
          >
            Mark reviewed<span className="visually-hidden">: {name}</span>
          </button>
        </div>
        <details>
          <summary>Set a date or note<span className="visually-hidden"> for {name}</span></summary>
          <form
            className="review-date"
            onSubmit={(event) => {
              event.preventDefault();
              save({ review_by: date || null, note: note.trim() === "" ? null : note }, date ? `Review date for ${row.title} saved.` : `Review date for ${row.title} cleared.`);
            }}
          >
            <label>
              Review by
              <input type="date" value={date} min={today || undefined} onChange={(event) => setDate(event.target.value)} disabled={busy} />
            </label>
            <label>
              Note (optional)
              <input type="text" value={note} onChange={(event) => setNote(event.target.value)} disabled={busy} />
            </label>
            <button type="submit" className="button button--outline button--small" disabled={busy}>Save<span className="visually-hidden"> review date for {name}</span></button>
          </form>
        </details>
        {error && <p className="field-error" role="alert">{error}</p>}
      </td>
    </tr>
  );
}
