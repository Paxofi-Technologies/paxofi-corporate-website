"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { AdminApiError, PageTextSummary, formatDateTime } from "@/lib/admin-api";

/** The website's pages whose wording can be edited (D-015). */
export default function PageTextList() {
  const { request } = useAdmin();
  const [rows, setRows] = useState<PageTextSummary[] | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    let cancelled = false;
    request<PageTextSummary[]>("/pages")
      .then((result) => !cancelled && setRows(result.data))
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "Pages could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [request]);

  if (error) return <FormStatus state="error" message={error} />;
  if (rows === null) return <p role="status">Loading…</p>;

  return (
    <div className="admin-table-wrap">
      <table className="admin-table">
        <caption className="visually-hidden">Pages with editable text</caption>
        <thead>
          <tr>
            <th scope="col">Page</th>
            <th scope="col">Address</th>
            <th scope="col">Text on the website</th>
            <th scope="col">Draft</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.page}>
              <td>
                <Link href={`/admin/content/pages/${row.page}`} className="admin-strong-link">{row.label}</Link>
              </td>
              <td>{row.path}</td>
              <td>{row.published_at ? `Edited, published ${formatDateTime(row.published_at)}` : "Original wording"}</td>
              <td>{row.has_draft ? <span className="status-pill" data-status="in_progress">Unpublished changes</span> : "—"}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
