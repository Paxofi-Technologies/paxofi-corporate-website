"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdmin } from "./AdminContext";
import { ProductCard, ServiceCard } from "@/components/CatalogCards";
import { FieldShell } from "./AdminForm";
import Link from "next/link";
import { CatalogContent, CatalogKind, MediaItem, formatBytes, mediaHref } from "@/lib/admin-api";
import { CATALOG_ICONS } from "@/lib/catalog";

export type CatalogFormValues = {
  name: string;
  label: string;
  icon: string;
  summary: string;
  points: string;
  sort_order: string;
  /** Media library ids (D-012); "" for none. */
  image_id: string;
  document_id: string;
};

export function toFormValues(content: CatalogContent): CatalogFormValues {
  return {
    name: content.name,
    label: content.label ?? "",
    icon: content.icon,
    summary: content.summary,
    points: content.points.join("\n"),
    sort_order: String(content.sort_order),
    image_id: content.image_id ?? "",
    document_id: content.document_id ?? "",
  };
}

export function toPayload(values: CatalogFormValues): Record<string, unknown> {
  return {
    name: values.name,
    label: values.label,
    icon: values.icon,
    summary: values.summary,
    points: values.points.split("\n").map((p) => p.trim()).filter(Boolean),
    sort_order: values.sort_order.trim(),
    image_id: values.image_id || null,
    document_id: values.document_id || null,
  };
}

/** The media library for the pickers; an empty list if it cannot be loaded (the form still works). */
export function useMediaList(): MediaItem[] | null {
  const { request } = useAdmin();
  const [media, setMedia] = useState<MediaItem[] | null>(null);
  useEffect(() => {
    let cancelled = false;
    request<MediaItem[]>("/media")
      .then((result) => !cancelled && setMedia(result.data))
      .catch(() => !cancelled && setMedia([]));
    return () => {
      cancelled = true;
    };
  }, [request]);
  return media;
}

const ICON_LABELS: Record<string, string> = {
  "shield-check": "Shield",
  cog: "Cog",
  code: "Code",
  network: "Network",
  rocket: "Rocket",
  workflow: "Workflow",
  cloud: "Cloud",
  megaphone: "Megaphone",
  layers: "Layers",
  globe: "Globe",
  lightbulb: "Light bulb",
  briefcase: "Briefcase",
  smartphone: "Phone",
  database: "Database",
  lock: "Lock",
};

/** Fields for a product or service, with a live preview of the website card. */
export function CatalogForm({
  kind,
  values,
  onChange,
  fields,
  onSubmit,
  media,
  apiBase,
  children,
}: {
  kind: CatalogKind;
  /** The media library, for choosing a picture and a document; null while loading. */
  media: MediaItem[] | null;
  apiBase?: string;
  values: CatalogFormValues;
  onChange: (values: CatalogFormValues) => void;
  fields: Record<string, string>;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  children: React.ReactNode;
}) {
  const set = (key: keyof CatalogFormValues) => (event: { target: { value: string } }) => onChange({ ...values, [key]: event.target.value });
  const images = (media ?? []).filter((m) => m.kind === "image");
  const documents = (media ?? []).filter((m) => m.kind === "document");
  const image = images.find((m) => m.id === values.image_id);
  const document = documents.find((m) => m.id === values.document_id);
  const preview = {
    image: image && image.width && image.height ? { src: mediaHref(apiBase, image.path), alt: image.alt_text ?? "", width: image.width, height: image.height } : null,
    document: document ? { href: mediaHref(apiBase, document.path), title: document.title ?? document.filename, format: document.format, sizeBytes: document.size_bytes } : null,
    slug: "preview",
    name: values.name || "Name",
    label: values.label || null,
    icon: values.icon,
    summary: values.summary || "Summary",
    points: values.points.split("\n").map((p) => p.trim()).filter(Boolean).slice(0, 5),
  };
  const products = kind === "products";

  return (
    <div className="admin-detail">
      <form className="contact-form form-card" onSubmit={onSubmit} noValidate>
        <FieldShell label="Name" hint="2 to 80 characters." error={fields.name}>
          {(props) => <input name="name" value={values.name} onChange={set("name")} maxLength={80} required {...props} />}
        </FieldShell>
        {products && (
          <FieldShell label="Label (optional)" hint="Small line above the name, e.g. Paxofi Product." error={fields.label}>
            {(props) => <input name="label" value={values.label} onChange={set("label")} maxLength={40} {...props} />}
          </FieldShell>
        )}
        <FieldShell label="Icon" error={fields.icon}>
          {(props) => (
            <select name="icon" value={values.icon} onChange={set("icon")} {...props}>
              {CATALOG_ICONS.map((icon) => (
                <option key={icon} value={icon}>{ICON_LABELS[icon] ?? icon}</option>
              ))}
            </select>
          )}
        </FieldShell>
        <FieldShell label="Summary" hint={`${values.summary.length}/300 characters. Plain text.`} error={fields.summary}>
          {(props) => <textarea name="summary" rows={4} value={values.summary} onChange={set("summary")} maxLength={300} required {...props} />}
        </FieldShell>
        {products && (
          <FieldShell label="Key points (optional)" hint="One per line, up to 5, each up to 80 characters." error={fields.points}>
            {(props) => <textarea name="points" rows={5} value={values.points} onChange={set("points")} {...props} />}
          </FieldShell>
        )}
        <FieldShell
          label="Picture (optional)"
          hint={images.length === 0 && media !== null ? "No pictures yet: upload one under Media." : "Shown at the top of the card. Upload pictures under Media."}
          error={fields.image_id}
        >
          {(props) => (
            <select name="image_id" value={values.image_id} onChange={set("image_id")} {...props}>
              <option value="">No picture (icon only)</option>
              {values.image_id && !image && <option value={values.image_id}>Chosen picture (loading…)</option>}
              {images.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.alt_text ? `${m.alt_text} (${m.filename})` : m.filename}
                </option>
              ))}
            </select>
          )}
        </FieldShell>
        <FieldShell
          label="Document to download (optional)"
          hint={documents.length === 0 && media !== null ? "No documents yet: upload one under Media." : "For example a brochure. Visitors download it from the card."}
          error={fields.document_id}
        >
          {(props) => (
            <select name="document_id" value={values.document_id} onChange={set("document_id")} {...props}>
              <option value="">No document</option>
              {values.document_id && !document && <option value={values.document_id}>Chosen document (loading…)</option>}
              {documents.map((m) => (
                <option key={m.id} value={m.id}>
                  {(m.title ?? m.filename) + ` (${m.format}, ${formatBytes(m.size_bytes)})`}
                </option>
              ))}
            </select>
          )}
        </FieldShell>
        <p className="form-note">
          <Link href="/admin/media">Open the media library</Link> to upload pictures and documents.
        </p>
        <FieldShell label="Display order" hint="Lower numbers come first (0–999)." error={fields.sort_order}>
          {(props) => <input name="sort_order" inputMode="numeric" value={values.sort_order} onChange={set("sort_order")} maxLength={3} {...props} />}
        </FieldShell>
        {children}
      </form>
      <section className="admin-preview" aria-labelledby="preview-heading">
        <h2 id="preview-heading">Preview</h2>
        <p className="form-note">How it looks on the {products ? "Products" : "Services"} page.</p>
        {products ? <ProductCard item={preview} headingLevel={3} /> : <ServiceCard item={preview} />}
      </section>
    </div>
  );
}
