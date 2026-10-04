"use client";

import { useEffect, useRef, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, AnalyticsReport } from "@/lib/admin-api";

const RANGES = [7, 30, 90] as const;
const DEVICE_LABELS: Record<string, string> = { phone: "Phone", tablet: "Tablet", desktop: "Desktop", unknown: "Unknown" };
const number = new Intl.NumberFormat("en-GB");

/** Visitor analytics for staff (D-014): cookieless daily totals. */
export default function AnalyticsReportView() {
  const { user, request } = useAdmin();
  const [days, setDays] = useState<(typeof RANGES)[number]>(30);
  const [report, setReport] = useState<AnalyticsReport | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!can(user, "analytics.read")) return;
    let cancelled = false;
    request<AnalyticsReport>(`/analytics?days=${days}`)
      .then((result) => {
        if (cancelled) return;
        setReport(result.data);
        setError("");
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "Analytics could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, days]);

  if (!can(user, "analytics.read")) return <NoAccess title="Analytics" />;

  return (
    <>
      <h1 className="admin-title">Analytics</h1>
      <p className="admin-intro">
        Visits to the public website, counted without cookies or personal data. Visitors who ask not to be tracked, and known bots, are
        not counted, so the figures are a close estimate.
      </p>
      <div className="admin-toolbar">
        <div className="admin-tabs" role="group" aria-label="Period">
          {RANGES.map((range) => (
            <button key={range} type="button" className="admin-tab" aria-pressed={days === range} onClick={() => setDays(range)}>
              Last {range} days
            </button>
          ))}
        </div>
      </div>
      <FormStatus state="error" message={error} />
      {!report && !error && <p role="status">Loading…</p>}
      {report && (
        <>
          <dl className="stat-tiles">
            <div className="stat-tile">
              <dt>Page views</dt>
              <dd>{number.format(report.totals.views)}</dd>
            </div>
            <div className="stat-tile">
              <dt>Visitors</dt>
              <dd>{number.format(report.totals.visitors)}</dd>
              <dd className="admin-sub">Unique per day, added up</dd>
            </div>
            <div className="stat-tile">
              <dt>Enquiries</dt>
              <dd>{number.format(report.totals.enquiries)}</dd>
              <dd className="admin-sub">Spam not counted</dd>
            </div>
          </dl>

          <section className="form-card admin-panel-gap" aria-labelledby="daily-heading">
            <h2 id="daily-heading">Page views per day</h2>
            <DailyChart daily={report.daily} />
            <details className="chart-table">
              <summary>Show as a table</summary>
              <div className="admin-table-wrap">
                <table className="admin-table">
                  <thead>
                    <tr>
                      <th scope="col">Day</th>
                      <th scope="col">Page views</th>
                      <th scope="col">Visitors</th>
                      <th scope="col">Enquiries</th>
                    </tr>
                  </thead>
                  <tbody>
                    {[...report.daily].reverse().map((d) => (
                      <tr key={d.day}>
                        <td>{formatDay(d.day)}</td>
                        <td>{number.format(d.views)}</td>
                        <td>{number.format(d.visitors)}</td>
                        <td>{number.format(d.enquiries)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </details>
          </section>

          <div className="analytics-grid admin-panel-gap">
            <RankTable
              title="Pages"
              rows={report.pages.map((p) => ({ label: pageLabel(p.path), value: p.views, extra: p.visitors }))}
              valueLabel="Views"
              extraLabel="Visitors"
              empty="No visits in this period."
            />
            <RankTable
              title="Where visitors came from"
              rows={report.sources.map((s) => ({ label: s.source === "(direct)" ? "Direct or unknown" : s.source, value: s.views }))}
              valueLabel="Visits"
              empty="No visits in this period."
            />
            <RankTable
              title="Devices"
              rows={report.devices.map((d) => ({ label: DEVICE_LABELS[d.device] ?? d.device, value: d.views }))}
              valueLabel="Views"
              empty="No visits in this period."
            />
          </div>
        </>
      )}
    </>
  );
}

/**
 * Single-series column chart: thin columns, hairline grid, hover tooltip. The
 * picture is one image with a summary for screen readers; every value is in the
 * "Show as a table" view for keyboard and screen-reader users.
 */
function DailyChart({ daily }: { daily: AnalyticsReport["daily"] }) {
  const [active, setActive] = useState<number | null>(null);
  // Drawn at the container's real width, so text keeps its size on every screen.
  const box = useRef<HTMLDivElement>(null);
  const [width, setWidth] = useState(720);
  useEffect(() => {
    const element = box.current;
    if (!element) return;
    const observer = new ResizeObserver(([entry]) => setWidth(Math.max(280, Math.round(entry.contentRect.width))));
    observer.observe(element);
    return () => observer.disconnect();
  }, []);
  const height = width < 480 ? 180 : 220;
  const pad = { top: 12, right: 8, bottom: 28, left: 44 };
  const plotW = width - pad.left - pad.right;
  const plotH = height - pad.top - pad.bottom;
  const max = Math.max(1, ...daily.map((d) => d.views));
  const step = niceStep(max);
  const top = Math.ceil(max / step) * step;
  const ticks = Array.from({ length: Math.round(top / step) + 1 }, (_, i) => i * step);
  const slot = plotW / daily.length;
  const bar = Math.max(2, Math.min(24, slot - 2));
  // Date labels about 80px apart; the last day is always labelled, so a label too close before it is skipped.
  const labelEvery = Math.max(1, Math.ceil(80 / slot));
  const labelled = (i: number) => i === daily.length - 1 || (i % labelEvery === 0 && daily.length - 1 - i >= labelEvery * 0.75);
  const y = (v: number) => pad.top + plotH - (v / top) * plotH;
  const shown = active === null ? null : daily[active];

  return (
    <div className="chart" ref={box} onMouseLeave={() => setActive(null)}>
      <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label={`Page views per day, ${daily.length} days, highest ${number.format(max)}`}>
        {ticks.map((t) => (
          <g key={t}>
            <line x1={pad.left} x2={width - pad.right} y1={y(t)} y2={y(t)} className="chart-grid" />
            <text x={pad.left - 8} y={y(t)} dy="0.32em" textAnchor="end" className="chart-axis">
              {number.format(t)}
            </text>
          </g>
        ))}
        {daily.map((d, i) => {
          const x = pad.left + i * slot + (slot - bar) / 2;
          const h = Math.max(0, y(0) - y(d.views));
          const r = Math.min(4, h / 2, bar / 2);
          return (
            <g key={d.day}>
              {h > 0 && <path d={columnPath(x, y(0), bar, h, r)} className={active === i ? "chart-bar chart-bar--active" : "chart-bar"} />}
              <rect
                x={pad.left + i * slot}
                y={pad.top}
                width={slot}
                height={plotH}
                fill="transparent"
                onMouseEnter={() => setActive(i)}
              />
              {labelled(i) && (
                <text
                  x={i === daily.length - 1 ? width - pad.right : pad.left + i * slot + slot / 2}
                  y={height - 8}
                  textAnchor={i === daily.length - 1 ? "end" : "middle"}
                  className="chart-axis"
                >
                  {formatDay(d.day, true)}
                </text>
              )}
            </g>
          );
        })}
      </svg>
      {shown && active !== null && (
        <div className="chart-tooltip" style={{ left: `${((pad.left + active * slot + slot / 2) / width) * 100}%` }} aria-hidden="true">
          <strong>{formatDay(shown.day)}</strong>
          <span>{count(shown.views, "page view")}</span>
          <span>{count(shown.visitors, "visitor")}</span>
          <span>{count(shown.enquiries, "enquiry", "enquiries")}</span>
        </div>
      )}
    </div>
  );
}

function RankTable({
  title,
  rows,
  valueLabel,
  extraLabel,
  empty,
}: {
  title: string;
  rows: { label: string; value: number; extra?: number }[];
  valueLabel: string;
  extraLabel?: string;
  empty: string;
}) {
  const max = Math.max(1, ...rows.map((r) => r.value));
  return (
    <section className="form-card" aria-label={title}>
      <h2>{title}</h2>
      {rows.length === 0 ? (
        <p className="form-note">{empty}</p>
      ) : (
        <table className="rank-table">
          <thead>
            <tr>
              <th scope="col">
                <span className="visually-hidden">{title}</span>
              </th>
              <th scope="col">{valueLabel}</th>
              {extraLabel && <th scope="col">{extraLabel}</th>}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.label}>
                <th scope="row">
                  <span className="rank-bar" style={{ width: `${(row.value / max) * 100}%` }} aria-hidden="true" />
                  <span className="rank-label">{row.label}</span>
                </th>
                <td>{number.format(row.value)}</td>
                {extraLabel && <td>{number.format(row.extra ?? 0)}</td>}
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  );
}

/** Column with a 4px rounded top and a square base on the baseline. */
function columnPath(x: number, base: number, w: number, h: number, r: number): string {
  const top = base - h;
  return `M${x},${base}V${top + r}Q${x},${top} ${x + r},${top}H${x + w - r}Q${x + w},${top} ${x + w},${top + r}V${base}Z`;
}

function niceStep(max: number): number {
  const rough = max / 4;
  const power = 10 ** Math.floor(Math.log10(rough));
  const unit = rough / power;
  return Math.max(1, (unit <= 1 ? 1 : unit <= 2 ? 2 : unit <= 5 ? 5 : 10) * power);
}

function count(n: number, one: string, many = `${one}s`): string {
  return `${number.format(n)} ${n === 1 ? one : many}`;
}

const PAGE_NAMES: Record<string, string> = {
  "/": "Home",
  "/about": "About",
  "/services": "Services",
  "/products": "Products",
  "/careers": "Careers",
  "/contact": "Contact",
  "/privacy": "Privacy",
  "/terms": "Terms",
  "(other)": "Other pages",
};

function pageLabel(path: string): string {
  return PAGE_NAMES[path] ?? path;
}

function formatDay(day: string, short = false): string {
  const date = new Date(`${day}T00:00:00Z`);
  return new Intl.DateTimeFormat("en-GB", short ? { day: "numeric", month: "short", timeZone: "UTC" } : { weekday: "short", day: "numeric", month: "short", timeZone: "UTC" }).format(date);
}
