"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";
import { AdminFrame } from "@/components/admin/AdminFrame";
import { useAdmin } from "@/components/admin/AdminContext";
import { Field, FormStatus, PASSWORD_HINT, formValues } from "@/components/admin/AdminForm";
import { AdminApiError, StaffUser } from "@/lib/admin-api";

/** Creates the first administrator with the one-time ADMIN_SETUP_TOKEN (D-009). */
export default function SetupForm() {
  const { request, setUser } = useAdmin();
  const router = useRouter();
  const [available, setAvailable] = useState<boolean | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [fields, setFields] = useState<Record<string, string>>({});

  useEffect(() => {
    request<{ available: boolean }>("/setup")
      .then((result) => setAvailable(result.data.available))
      .catch(() => setAvailable(false));
  }, [request]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError("");
    setFields({});
    try {
      const result = await request<StaffUser>("/setup", { method: "POST", body: formValues(event.currentTarget) });
      setUser(result.data);
      router.replace("/admin/enquiries");
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(e.message);
      } else {
        setError("Setup failed. Please try again.");
      }
      setBusy(false);
    }
  }

  if (available === null) {
    return (
      <AdminFrame title="First-time setup">
        <p role="status">Checking…</p>
      </AdminFrame>
    );
  }

  if (!available) {
    return (
      <AdminFrame title="First-time setup">
        <p>Setup is not available: an administrator already exists, or setup has not been enabled on the server.</p>
        <p>
          <Link className="button button--primary" href="/admin/login">Go to sign in</Link>
        </p>
      </AdminFrame>
    );
  }

  return (
    <AdminFrame
      title="First-time setup"
      intro="Create the first administrator account. You need the setup code from the server configuration (ADMIN_SETUP_TOKEN)."
    >
      <form className="contact-form" method="post" onSubmit={submit}>
        <Field name="setup_token" label="Setup code" type="password" autoComplete="off" required error={fields.setup_token} />
        <Field name="display_name" label="Your name" autoComplete="name" required maxLength={160} error={fields.display_name} />
        <Field name="email" label="Email" type="email" autoComplete="username" required maxLength={255} error={fields.email} />
        <Field
          name="password"
          label="Password"
          type="password"
          autoComplete="new-password"
          required
          minLength={12}
          maxLength={256}
          hint={PASSWORD_HINT}
          error={fields.password}
        />
        <FormStatus state="error" message={error} />
        <button className="button button--primary" type="submit" disabled={busy}>
          {busy ? "Creating…" : "Create administrator"}
        </button>
      </form>
    </AdminFrame>
  );
}
