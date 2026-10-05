"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { AdminApiError, type ArticleAdminSummary, formatDateTime } from "@/lib/admin-api";

const STATE_LABELS = { draft: "Not published", published: "On the website", hidden: "Hidden" } as const;

/** News & Insights articles, newest change first (D-021). */
export default function ArticleList() {
  const { request } = useAdmin();
  const [rows, setRows] = useState<ArticleAdminSummary[] | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    let cancelled = false;
    request<ArticleAdminSummary[]>("/articles")
      .then((result) => !cancelled && setRows(result.data))
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "Articles could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [request]);

  if (error) return <FormStatus state="error" message={error} />;
  if (rows === null) return <p role="status">Loading…</p>;
  if (rows.length === 0) return <p className="admin-empty">No articles yet. Use <strong>Write an article</strong> to start one.</p>;
  return (
    <div className="admin-table-wrap">
      <table className="admin-table">
        <caption className="visually-hidden">Articles, most recently changed first</caption>
        <thead>
          <tr>
            <th scope="col">Title</th>
            <th scope="col">Status</th>
            <th scope="col">Draft</th>
            <th scope="col">Published</th>
            <th scope="col">Last changed</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id}>
              <td>
                <Link href={`/admin/content/articles/${row.id}`} className="admin-strong-link">{row.title}</Link>
                <span className="admin-sub">{row.path}</span>
              </td>
              <td><span className="status-pill" data-status={row.state === "published" ? "active" : "disabled"}>{STATE_LABELS[row.state]}</span></td>
              <td>{row.has_draft ? <span className="status-pill" data-status="in_progress">Unpublished changes</span> : "—"}</td>
              <td>{formatDateTime(row.published_at)}</td>
              <td>{formatDateTime(row.updated_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
