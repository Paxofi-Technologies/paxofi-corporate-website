"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FieldShell, FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, ENQUIRY_STATUSES, formatDateTime, statusLabel } from "@/lib/admin-api";

type Enquiry = {
  id: string;
  name: string;
  email: string;
  company: string | null;
  message: string;
  status: string;
  source_ip: string | null;
  user_agent: string | null;
  request_id: string | null;
  created_at: string;
};

export default function EnquiryDetail({ id }: { id: string }) {
  const { user, request } = useAdmin();
  const [enquiry, setEnquiry] = useState<Enquiry | null>(null);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!can(user, "enquiries.read")) return;
    let cancelled = false;
    request<Enquiry>(`/enquiries/${encodeURIComponent(id)}`)
      .then((result) => !cancelled && setEnquiry(result.data))
      .catch((e) => !cancelled && setError(e instanceof AdminApiError ? e.message : "The enquiry could not be loaded."));
    return () => {
      cancelled = true;
    };
  }, [user, request, id]);

  if (!can(user, "enquiries.read")) return <NoAccess title="Enquiry" />;

  async function updateStatus(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const status = String(new FormData(event.currentTarget).get("status") ?? "");
    setBusy(true);
    setError("");
    setSaved("");
    try {
      const result = await request<Enquiry>(`/enquiries/${encodeURIComponent(id)}`, { method: "PATCH", body: { status } });
      setEnquiry(result.data);
      setSaved(`Status changed to “${statusLabel(result.data.status)}”.`);
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "The status could not be changed.");
    } finally {
      setBusy(false);
    }
  }

  const back = <p><Link href="/admin/enquiries">← All enquiries</Link></p>;

  if (!enquiry) {
    return (
      <>
        {back}
        <h1 className="admin-title">Enquiry</h1>
        {error ? <FormStatus state="error" message={error} /> : <p role="status">Loading enquiry…</p>}
      </>
    );
  }

  const subject = encodeURIComponent("Re: your enquiry to Paxofi Technologies");

  return (
    <>
      {back}
      <h1 className="admin-title">Enquiry from {enquiry.name}</h1>
      <div className="admin-detail">
        <section className="form-card" aria-labelledby="message-heading">
          <h2 id="message-heading">Message</h2>
          <p className="admin-message">{enquiry.message}</p>
          <p>
            <a className="button button--primary" href={`mailto:${enquiry.email}?subject=${subject}`}>Reply by email</a>
          </p>
        </section>
        <aside className="form-card" aria-label="Details and status">
          <h2>Details</h2>
          <dl className="admin-dl">
            <dt>Name</dt><dd>{enquiry.name}</dd>
            <dt>Email</dt><dd><a href={`mailto:${enquiry.email}`}>{enquiry.email}</a></dd>
            <dt>Company</dt><dd>{enquiry.company || "—"}</dd>
            <dt>Received</dt><dd>{formatDateTime(enquiry.created_at)}</dd>
            <dt>Status</dt><dd><span className="status-pill" data-status={enquiry.status}>{statusLabel(enquiry.status)}</span></dd>
            <dt>Reference</dt><dd className="admin-mono">{enquiry.request_id || "—"}</dd>
            <dt>IP address</dt><dd className="admin-mono">{enquiry.source_ip || "Cleared after 90 days"}</dd>
            <dt>Browser</dt><dd className="admin-small">{enquiry.user_agent || "—"}</dd>
          </dl>
          {can(user, "enquiries.update") && (
            <form className="contact-form" onSubmit={updateStatus}>
              <FieldShell label="Change status">
                {(props) => (
                  <select name="status" defaultValue={enquiry.status} key={enquiry.status} {...props}>
                    {ENQUIRY_STATUSES.map((s) => (
                      <option key={s.value} value={s.value}>{s.label}</option>
                    ))}
                  </select>
                )}
              </FieldShell>
              <FormStatus state="success" message={saved} />
              <FormStatus state="error" message={error} />
              <button className="button button--primary" type="submit" disabled={busy}>{busy ? "Saving…" : "Save status"}</button>
            </form>
          )}
        </aside>
      </div>
    </>
  );
}
