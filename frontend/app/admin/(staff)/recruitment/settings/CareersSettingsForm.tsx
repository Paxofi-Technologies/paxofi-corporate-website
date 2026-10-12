"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, type CareersSettings, formatDay } from "@/lib/admin-api";

const SOURCES = { staff: "set here", server: "from the server setting", none: "not set" } as const;

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

  const save = async (form: HTMLFormElement) => {
    const data = new FormData(form);
    setBusy(true);
    setFields({});
    setFormError("");
    setStatus("");
    try {
      const result = await request<Raw>("/careers-settings", { method: "PATCH", body: { opens_from: String(data.get("opens_from") ?? ""), cohort1_closes: String(data.get("cohort1_closes") ?? "") } });
      setSettings(result.data);
      setStatus("Saved. The careers website follows within a minute.");
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
  const opensLine = effective.opens_from === null ? "Applications are open now (no opening day is set)." : effective.applications_open ? `Applications opened on ${formatDay(effective.opens_from)}.` : `Applications open on ${formatDay(effective.opens_from)}.`;

  return (
    <>
      <p className="admin-intro"><Link href="/admin/recruitment">Back to recruitment</Link></p>
      <p className="admin-intro">
        Choose when careers.paxofi.com starts accepting applications. Until that day, people can read about the roles but not apply. Changes apply within a minute and no server setting needs to change.
      </p>
      <p role="status"><strong>{opensLine}</strong> Now counting as <strong>{effective.cohort}</strong>.</p>

      <form
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
        <FormStatus state="error" message={formError} />
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
