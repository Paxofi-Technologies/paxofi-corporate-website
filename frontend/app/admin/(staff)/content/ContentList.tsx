"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import ArticleList from "./ArticleList";
import PageTextList from "./PageTextList";
import RedirectList from "./RedirectList";
import ReviewList from "./ReviewList";
import { AdminApiError, CATALOG_KINDS, CatalogKind, CatalogSummary, formatDateTime } from "@/lib/admin-api";
import { PRODUCT_STATUSES, isProductStatus } from "@/lib/catalog";

type Tab = CatalogKind | "pages" | "articles" | "reviews" | "redirects";
const OTHER_TABS = ["pages", "articles", "reviews", "redirects"] as const;
const isOther = (tab: Tab): tab is (typeof OTHER_TABS)[number] => (OTHER_TABS as readonly string[]).includes(tab);

/** Products and services on the website (D-011) and the wording of each page (D-015). */
export default function ContentList() {
  const { user, request } = useAdmin();
  const initialTab = useSearchParams().get("tab");
  const [tab, setTab] = useState<Tab>(
    initialTab === "services" || initialTab === "industries" || (OTHER_TABS as readonly (string | null)[]).includes(initialTab) ? (initialTab as Tab) : "products",
  );
  const kind: CatalogKind = isOther(tab) ? "products" : tab;
  const [rows, setRows] = useState<CatalogSummary[] | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!can(user, "content.edit") || isOther(tab)) return;
    let cancelled = false;
    request<CatalogSummary[]>(`/catalog/${kind}`)
      .then((result) => {
        if (cancelled) return;
        setRows(result.data);
        setError("");
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "Content could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, kind, tab]);

  if (!can(user, "content.edit")) return <NoAccess title="Content" />;
  const current = CATALOG_KINDS.find((k) => k.value === kind)!;

  return (
    <>
      <h1 className="admin-title">Content</h1>
      <p className="admin-intro">
        The products, services and industries shown on the website, News &amp; Insights articles, and the wording of each page. Changes are saved as drafts and appear on the
        site only when an administrator publishes them. <strong>Reviews</strong> shows when each item is next due a check; <strong>Redirects</strong> keeps old addresses working.
      </p>
      <div className="admin-toolbar">
        <div className="admin-tabs" role="group" aria-label="Collection">
          {[
            ...CATALOG_KINDS,
            { value: "articles" as const, label: "Articles" },
            { value: "pages" as const, label: "Page text" },
            { value: "reviews" as const, label: "Reviews" },
            { value: "redirects" as const, label: "Redirects" },
          ].map((option) => (
            <button
              key={option.value}
              type="button"
              className="admin-tab"
              aria-pressed={tab === option.value}
              onClick={() => {
                setRows(null);
                setTab(option.value);
              }}
            >
              {option.label}
            </button>
          ))}
        </div>
        {tab === "articles" ? (
          <Link className="button button--primary" href="/admin/content/articles/new">Write an article</Link>
        ) : !isOther(tab) && (
          <Link className="button button--primary" href={`/admin/content/${kind}/new`}>
            Add {current.a}
          </Link>
        )}
      </div>
      <FormStatus state="error" message={error} />
      {tab === "pages" ? (
        <PageTextList />
      ) : tab === "reviews" ? (
        <ReviewList />
      ) : tab === "redirects" ? (
        <RedirectList />
      ) : tab === "articles" ? (
        <ArticleList />
      ) : rows === null ? (
        <p role="status">Loading…</p>
      ) : rows.length === 0 ? (
        <p className="admin-empty">No {current.label.toLowerCase()} yet.</p>
      ) : (
        <div className="admin-table-wrap">
          <table className="admin-table">
            <caption className="visually-hidden">{current.label} in display order</caption>
            <thead>
              <tr>
                <th scope="col">Name</th>
                <th scope="col">On the website</th>
                {kind === "products" && <th scope="col">Status</th>}
                <th scope="col">Draft</th>
                <th scope="col">Order</th>
                <th scope="col">Last changed</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>
                    <Link href={`/admin/content/${kind}/${row.id}`} className="admin-strong-link">{row.name}</Link>
                  </td>
                  <td><span className="status-pill" data-status={row.visible ? "active" : "disabled"}>{row.visible ? "Shown" : "Hidden"}</span></td>
                  {kind === "products" && <td>{isProductStatus(row.status) ? PRODUCT_STATUSES[row.status] : "—"}</td>}
                  <td>{row.has_draft ? <span className="status-pill" data-status="in_progress">Unpublished changes</span> : "—"}</td>
                  <td>{row.sort_order}</td>
                  <td>{formatDateTime(row.updated_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
