"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { Field, FieldShell, FormStatus, PASSWORD_HINT, formValues } from "@/components/admin/AdminForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, StaffUser, formatDateTime } from "@/lib/admin-api";

type RoleOption = { value: string; label: string };

export default function StaffUsers() {
  const { user, request } = useAdmin();
  const [users, setUsers] = useState<StaffUser[] | null>(null);
  const [roles, setRoles] = useState<RoleOption[]>([]);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const load = useCallback(() => {
    return request<StaffUser[]>("/users")
      .then((result) => {
        setUsers(result.data);
        setRoles(Array.isArray(result.meta.roles) ? (result.meta.roles as RoleOption[]) : []);
      })
      .catch((e) => setError(e instanceof AdminApiError ? e.message : "Users could not be loaded."));
  }, [request]);

  useEffect(() => {
    if (can(user, "users.manage")) load();
  }, [user, load]);

  if (!can(user, "users.manage")) return <NoAccess title="Users" />;

  function changed(message: string) {
    setNotice(message);
    load();
  }

  return (
    <>
      <h1 className="admin-title">Users</h1>
      <p className="admin-intro">
        Staff who can sign in. Give each person a temporary password through a secure channel and ask them to change it on their account page.
      </p>
      <FormStatus state="success" message={notice} />
      <FormStatus state="error" message={error} />

      <details className="form-card admin-panel">
        <summary>Add a user</summary>
        <CreateUser roles={roles} onCreated={changed} />
      </details>

      {users === null ? (
        <p role="status">Loading users…</p>
      ) : (
        <ul className="admin-cards">
          {users.map((staff) => (
            <li key={staff.id} className="form-card">
              <div className="admin-card-head">
                <h2>{staff.display_name}</h2>
                <span className="status-pill" data-status={staff.status}>{staff.status === "active" ? "Active" : "Disabled"}</span>
              </div>
              <p className="admin-sub">
                {staff.email} · {staff.role_label ?? "No role"} · Two-factor {staff.two_factor_enabled ? "on" : "off"} · Last sign-in{" "}
                {formatDateTime(staff.last_login_at)}
              </p>
              {staff.id === user?.id ? (
                <p className="form-note">This is you. Change your password on the My account page.</p>
              ) : (
                <details className="admin-panel">
                  <summary>Edit {staff.display_name}</summary>
                  <EditUser staff={staff} roles={roles} onSaved={changed} />
                </details>
              )}
            </li>
          ))}
        </ul>
      )}
    </>
  );
}

function CreateUser({ roles, onCreated }: { roles: RoleOption[]; onCreated: (message: string) => void }) {
  const { request } = useAdmin();
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    setBusy(true);
    setFields({});
    setError("");
    try {
      const result = await request<StaffUser>("/users", { method: "POST", body: formValues(form) });
      form.reset();
      onCreated(`${result.data.display_name} can now sign in with the temporary password you set.`);
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(e.message);
      } else {
        setError("The user could not be added.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="contact-form" onSubmit={submit}>
      <Field name="display_name" label="Name" required maxLength={160} error={fields.display_name} />
      <Field name="email" label="Email" type="email" autoComplete="off" required maxLength={255} error={fields.email} />
      <RoleSelect roles={roles} error={fields.role} />
      <Field
        name="password"
        label="Temporary password"
        type="password"
        autoComplete="new-password"
        required
        minLength={12}
        maxLength={256}
        hint={PASSWORD_HINT}
        error={fields.password}
      />
      <FormStatus state="error" message={error} />
      <button className="button button--primary" type="submit" disabled={busy}>{busy ? "Adding…" : "Add user"}</button>
    </form>
  );
}

function EditUser({ staff, roles, onSaved }: { staff: StaffUser; roles: RoleOption[]; onSaved: (message: string) => void }) {
  const { request } = useAdmin();
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = formValues(event.currentTarget);
    const body: Record<string, string> = {};
    if (values.display_name !== staff.display_name) body.display_name = values.display_name;
    if (values.role !== (staff.role ?? "")) body.role = values.role;
    if (values.status !== staff.status) body.status = values.status;
    if (values.password) body.password = values.password;
    if (Object.keys(body).length === 0) {
      setError("Nothing has changed.");
      return;
    }
    setBusy(true);
    setFields({});
    setError("");
    try {
      await request<StaffUser>(`/users/${encodeURIComponent(staff.id)}`, { method: "PATCH", body });
      onSaved(`Saved changes to ${values.display_name || staff.display_name}.`);
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(e.message);
      } else {
        setError("The changes could not be saved.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <form className="contact-form" onSubmit={submit}>
      <Field name="display_name" label="Name" required maxLength={160} defaultValue={staff.display_name} error={fields.display_name} />
      <RoleSelect roles={roles} value={staff.role ?? ""} error={fields.role} />
      <FieldShell label="Account" error={fields.status}>
        {(props) => (
          <select name="status" defaultValue={staff.status} {...props}>
            <option value="active">Active: can sign in</option>
            <option value="disabled">Disabled: cannot sign in</option>
          </select>
        )}
      </FieldShell>
      <Field
        name="password"
        label="New temporary password (optional)"
        type="password"
        autoComplete="new-password"
        minLength={12}
        maxLength={256}
        hint="Leave empty to keep the current password. Changing it signs the user out everywhere."
        error={fields.password}
      />
      <FormStatus state="error" message={error} />
      <button className="button button--primary" type="submit" disabled={busy}>{busy ? "Saving…" : "Save changes"}</button>
      {staff.two_factor_enabled && <ResetTwoFactor staff={staff} onReset={onSaved} />}
    </form>
  );
}

/** For a lost phone: removes the person's two-factor and signs them out everywhere (D-010). */
function ResetTwoFactor({ staff, onReset }: { staff: StaffUser; onReset: (message: string) => void }) {
  const { request } = useAdmin();
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  async function reset() {
    setBusy(true);
    setError("");
    try {
      await request(`/users/${encodeURIComponent(staff.id)}/two-factor/reset`, { method: "POST" });
      onReset(`Two-factor sign-in was reset for ${staff.display_name}. They set it up again at their next sign-in${staff.role === "administrator" ? " (required for administrators)" : ""}.`);
    } catch (e) {
      setError(e instanceof AdminApiError ? e.message : "Two-factor could not be reset.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="admin-reset">
      <p className="form-note">Lost phone? Resetting removes their authenticator and recovery codes and signs them out everywhere.</p>
      <FormStatus state="error" message={error} />
      <button type="button" className="button button--outline" onClick={reset} disabled={busy}>
        {busy ? "Resetting…" : `Reset two-factor for ${staff.display_name}`}
      </button>
    </div>
  );
}

function RoleSelect({ roles, value, error }: { roles: RoleOption[]; value?: string; error?: string }) {
  return (
    <FieldShell label="Role" error={error}>
      {(props) => (
        <select name="role" defaultValue={value ?? ""} required {...props}>
          <option value="" disabled>Choose a role</option>
          {roles.map((role) => (
            <option key={role.value} value={role.value}>{role.label}</option>
          ))}
        </select>
      )}
    </FieldShell>
  );
}
