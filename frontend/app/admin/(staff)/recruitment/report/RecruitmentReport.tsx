"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { RankTable } from "../../analytics/AnalyticsReportView";
import { AdminApiError, type RecruitmentReportData } from "@/lib/admin-api";

const PERIODS = [
  { value: 7, label: "Last 7 days" },
  { value: 30, label: "Last 30 days" },
  { value: 90, label: "Last 90 days" },
  { value: 0, label: "All" },
];
const number = new Intl.NumberFormat("en-GB");

/** Which roles, channels and campaigns bring applicants, and how fast they are reviewed (P3.1). Counts only. */
export default function RecruitmentReport() {
  const { user, request } = useAdmin();
  const [days, setDays] = useState(30);
  const [report, setReport] = useState<RecruitmentReportData | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!can(user, "recruitment.read")) return;
    let cancelled = false;
    request<RecruitmentReportData>(`/applications/report?days=${days}`)
      .then((result) => {
        if (cancelled) return;
        setReport(result.data);
        setError("");
      })
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "The report could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, days]);

  if (!can(user, "recruitment.read")) return <NoAccess title="Recruitment report" />;

  const rows = (list: { label: string; count: number }[]) => list.map((row) => ({ label: row.label, value: row.count }));
  const review = report?.review;
  const onTimeShare = review && review.reviewed > 0 ? Math.round((review.on_time / review.reviewed) * 100) : null;

  return (
    <>
      <p><Link href="/admin/recruitment">← Recruitment</Link></p>
      <h1 className="admin-title">Recruitment report</h1>
      <p className="admin-intro">
        Applications received in the period, by role, stage, channel and campaign. Channels come from the optional
        &ldquo;How did you hear about this role?&rdquo; question; campaigns from tagged links such as
        <code> careers.paxofi.com/?utm_source=linkedin&amp;utm_campaign=pif-2026</code>.
      </p>
      <div className="admin-toolbar">
        <div className="admin-tabs" role="group" aria-label="Period">
          {PERIODS.map((period) => (
            <button key={period.value} type="button" className="admin-tab" aria-pressed={days === period.value} onClick={() => setDays(period.value)}>
              {period.label}
            </button>
          ))}
        </div>
      </div>
      <FormStatus state="error" message={error} />
      {!report && !error && <p role="status">Loading…</p>}
      {report && review && (
        <>
          <dl className="stat-tiles">
            <div className="stat-tile">
              <dt>Applications</dt>
              <dd>{number.format(report.total)}</dd>
            </div>
            <div className="stat-tile">
              <dt>Reviewed within {review.target_working_days} working days</dt>
              <dd>{onTimeShare === null ? "—" : `${onTimeShare}%`}</dd>
              <dd className="admin-sub">{review.on_time} of {review.reviewed} reviewed{review.median_hours === null ? "" : ` · median ${review.median_hours} h`}</dd>
            </div>
            <div className="stat-tile">
              <dt>Waiting for review</dt>
              <dd>{number.format(review.waiting)}</dd>
              <dd className="admin-sub">{review.overdue > 0 ? `${review.overdue} past the ${review.target_working_days}-working-day target` : "None overdue"}</dd>
            </div>
          </dl>
          <div className="analytics-grid admin-panel-gap">
            <RankTable title="By channel" rows={rows(report.by_source)} valueLabel="Applications" empty="No applications in this period." />
            <RankTable title="By campaign link" rows={rows(report.by_campaign)} valueLabel="Applications" empty="No applications in this period." />
            <RankTable title="By role" rows={rows(report.by_role)} valueLabel="Applications" empty="No applications in this period." />
            <RankTable title="By stage now" rows={rows(report.by_stage)} valueLabel="Applications" empty="No applications in this period." />
          </div>
          {report.by_day.length > 0 && (
            <section className="form-card admin-panel-gap" aria-labelledby="daily-heading">
              <h2 id="daily-heading">Applications per day</h2>
              <div className="admin-table-wrap">
                <table className="admin-table">
                  <caption className="visually-hidden">Applications per day, newest first</caption>
                  <thead><tr><th scope="col">Day</th><th scope="col">Applications</th></tr></thead>
                  <tbody>
                    {[...report.by_day].reverse().map((row) => <tr key={row.day}><td>{row.day}</td><td>{row.count}</td></tr>)}
                  </tbody>
                </table>
              </div>
            </section>
          )}
        </>
      )}
    </>
  );
}
