"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { can, useAdmin } from "@/components/admin/AdminContext";
import { FormStatus } from "@/components/admin/AdminForm";
import { CatalogForm, CatalogFormValues, EMPTY_VALUES, toPayload, useMediaList } from "@/components/admin/CatalogForm";
import { NoAccess } from "@/components/admin/StaffShell";
import { AdminApiError, CATALOG_KINDS, CatalogDetail } from "@/lib/admin-api";

/** Adds a product, service or industry; it stays hidden until an administrator shows it. */
export default function NewCatalogItem({ type }: { type: string }) {
  const { user, request, apiBase } = useAdmin();
  const media = useMediaList();
  const router = useRouter();
  const kind = CATALOG_KINDS.find((k) => k.value === type);
  const [values, setValues] = useState<CatalogFormValues>({ ...EMPTY_VALUES, status: type === "products" ? "planned" : "" });
  const [fields, setFields] = useState<Record<string, string>>({});
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  if (!can(user, "content.edit") || !kind) return <NoAccess title="Add content" />;

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!kind) return;
    setBusy(true);
    setFields({});
    setError("");
    try {
      const result = await request<CatalogDetail>(`/catalog/${kind.value}`, { method: "POST", body: toPayload(values) });
      router.replace(`/admin/content/${kind.value}/${result.data.item.id}?created=1`);
    } catch (e) {
      if (e instanceof AdminApiError) {
        setFields(e.fields);
        setError(e.message);
      } else {
        setError("It could not be added.");
      }
      setBusy(false);
    }
  }

  return (
    <>
      <p><Link href="/admin/content">← Content</Link></p>
      <h1 className="admin-title">Add {kind.a}</h1>
      <p className="admin-intro">New {kind.label.toLowerCase()} start hidden. An administrator shows them on the website when they are ready.</p>
      <CatalogForm kind={kind.value} values={values} onChange={setValues} fields={fields} onSubmit={submit} media={media} apiBase={apiBase}>
        <FormStatus state="error" message={error} />
        <button className="button button--primary" type="submit" disabled={busy}>{busy ? "Adding…" : `Add ${kind.singular} (hidden)`}</button>
      </CatalogForm>
    </>
  );
}
