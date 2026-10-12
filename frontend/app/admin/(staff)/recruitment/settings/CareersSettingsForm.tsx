"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, type CareersSettings, formatDay } from "@/lib/admin-api";

const SOURCES = { staff: "set here", server: "from the server setting", none: "not set" } as const;

/** Yesterday as a Lagos calendar day (YYYY-MM-DD): making it the last day closes applications at once. */
function lagosYesterday(now = new Date()): string {
  const today = new Intl.DateTimeFormat("en-CA", { timeZone: "Africa/Lagos", year: "numeric", month: "2-digit", day: "2-digit" }).format(now);
  const [y, m, d] = today.split("-").map(Number);
  return new Date(Date.UTC(y, m - 1, d - 1)).toISOString().slice(0, 10);
}

type Raw = CareersSettings;

/** Applications opening (D-028): administrators set the opening day, Cohort 1's last day and the staff test code. */
export default function CareersSettingsForm() {
  const { user, request } = useAdmin();
  const allowed = can(user, "content.publish");
  const [settings, setSettings] = useState<CareersSettings | null>(null);
  const [error, setError] = useState("");
  const [status, setStatus] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState("");
  const [busy, setBusy] = useState(false);
  const [newCode, setNewCode] = useState("");
  const [formKey, setFormKey] = useState(0);

  const load = useCallback(() => {
    return request<Raw>("/careers-settings")
      .then((result) => setSettings(result.data))
      .catch((e) => setError(e instanceof AdminApiError ? e.message : "The settings could not be loaded."));
  }, [request]);

  useEffect(() => {
    if (allowed) load();
  }, [allowed, load]);

  if (!allowed) return <NoAccess title="Applications opening" />;
  if (error) return <FormStatus state="error" message={error} />;
  if (settings === null) return <p role="status">Loading…</p>;

  const save = async (form: HTMLFormElement | null, closesAfter?: string, success = "Saved. The careers website follows within a minute.") => {
    const data = form ? new FormData(form) : null;
    const body = {
      opens_from: data ? String(data.get("opens_from") ?? "") : (settings.opens_from ?? ""),
      cohort1_closes: data ? String(data.get("cohort1_closes") ?? "") : (settings.cohort1_closes ?? ""),
      closes_after: closesAfter ?? (data ? String(data.get("closes_after") ?? "") : (settings.closes_after ?? "")),
    };
    setBusy(true);
    setFields({});
    setFormError("");
    setStatus("");
    try {
      const result = await request<Raw>("/careers-settings", { method: "PATCH", body });
      setSettings(result.data);
      setFormKey((k) => k + 1);
      setStatus(success);
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setFormError(e.message);
      } else {
        setFormError("The settings could not be saved.");
      }
    } finally {
      setBusy(false);
    }
  };

  const createCode = async () => {
    setStatus("");
    setFormError("");
    try {
      const result = await request<{ test_code: string }>("/careers-settings/test-code", { method: "POST" });
      setNewCode(result.data.test_code);
      await load();
    } catch (e) {
      setFormError(e instanceof AdminApiError ? e.message : "The test code could not be created.");
    }
  };

  const removeCode = async () => {
    if (!window.confirm("Remove the test code? Nobody can then submit test applications before the opening day.")) return;
    setStatus("");
    setFormError("");
    try {
      await request("/careers-settings/test-code", { method: "DELETE" });
      setNewCode("");
      await load();
      setStatus("The test code was removed.");
    } catch (e) {
      setFormError(e instanceof AdminApiError ? e.message : "The test code could not be removed.");
    }
  };

  const { effective } = settings;
  const lastDay = effective.closes_after ?? null;
  const opensLine = effective.applications_closed
    ? `Applications are closed (the last day was ${formatDay(lastDay)}).`
    : effective.opens_from === null
      ? `Applications are open now (no opening day is set)${lastDay ? `, until ${formatDay(lastDay)}` : ""}.`
      : effective.applications_open
        ? `Applications opened on ${formatDay(effective.opens_from)}${lastDay ? ` and close after ${formatDay(lastDay)}` : ""}.`
        : `Applications open on ${formatDay(effective.opens_from)}${lastDay ? ` and close after ${formatDay(lastDay)}` : ""}.`;

  const closeNow = () => {
    if (!window.confirm("Close applications now? careers.paxofi.com stops accepting applications within a minute. You can reopen them here at any time.")) return;
    save(null, lagosYesterday(), "Applications are closed. The careers website follows within a minute.");
  };
  const reopen = () => save(null, "", "The last day to apply was removed. Applications follow the opening day again.");

  return (
    <>
      <p className="admin-intro"><Link href="/admin/recruitment">Back to recruitment</Link></p>
      <p className="admin-intro">
        Choose when careers.paxofi.com starts and stops accepting applications. Outside those days, people can read about the roles but not apply. Changes apply within a minute and no server setting needs to change.
      </p>
      <p role="status"><strong>{opensLine}</strong>{effective.cohort ? <> Now counting as <strong>{effective.cohort}</strong>.</> : null}</p>

      <FormStatus state="error" message={formError} />
      <p>
        {effective.applications_closed ? (
          <button type="button" className="button button--outline" onClick={reopen} disabled={busy}>Reopen applications</button>
        ) : (
          <button type="button" className="button button--outline admin-danger" onClick={closeNow} disabled={busy}>Close applications now</button>
        )}
      </p>

      <form
        key={formKey}
        className="contact-form"
        aria-labelledby="dates-title"
        onSubmit={(event) => {
          event.preventDefault();
          save(event.currentTarget);
        }}
      >
        <h2 id="dates-title" className="admin-card-title">Dates</h2>
        <Field name="opens_from" label="Applications open on" type="date" defaultValue={settings.opens_from ?? ""} error={fields.opens_from} hint={`Lagos time, from midnight. Now ${SOURCES[effective.opens_from_source]}. Leave empty to use the server setting${settings.server.opens_from ? ` (${formatDay(settings.server.opens_from)})` : ""}, or open immediately if there is none.`} />
        <Field name="cohort1_closes" label="Last day for Cohort 1" type="date" defaultValue={settings.cohort1_closes ?? ""} error={fields.cohort1_closes} hint={`Applications after this day count towards Cohort 2 and the talent pool. Now ${SOURCES[effective.cohort1_closes_source]}.`} />
        <Field name="closes_after" label="Last day to apply (optional)" type="date" defaultValue={settings.closes_after ?? ""} error={fields.closes_after} hint="Lagos time: applications stop at midnight at the end of this day. Leave empty to keep applications open." />
        <FormStatus state="success" message={status} />
        <button type="submit" className="button button--primary" disabled={busy}>{busy ? "Saving…" : "Save dates"}</button>
      </form>

      <section aria-labelledby="code-title">
        <h2 id="code-title" className="admin-card-title">Staff test code</h2>
        <p className="admin-sub">
          With the code, staff can submit test applications before the opening day (add <code className="admin-mono">?test</code> to a role address). Test applications are flagged and left out of reports.
          {settings.test_code.by_server && !settings.test_code.by_staff ? " A code is currently set on the server." : ""}
        </p>
        {newCode && (
          <p role="status" className="notice">
            Your new code (shown once, copy it now): <code className="admin-mono">{newCode}</code>
          </p>
        )}
        <p>
          <button type="button" className="button button--outline" onClick={createCode}>{settings.test_code.by_staff ? "Replace the test code" : "Create a test code"}</button>{" "}
          {settings.test_code.by_staff && <button type="button" className="button button--outline" onClick={removeCode}>Remove the test code</button>}
        </p>
      </section>
    </>
  );
}
