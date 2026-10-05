"use client";

import { FormEvent, useEffect, useState } from "react";
import { useAdmin } from "./AdminContext";
import { IndustryCard, ProductCard, ServiceCard } from "@/components/CatalogCards";
import { FieldShell } from "./AdminForm";
import Link from "next/link";
import { CatalogContent, CatalogKind, CatalogSummary, MediaItem, formatBytes, mediaHref } from "@/lib/admin-api";
import { CATALOG_ICONS, PRODUCT_STATUSES, isProductStatus, paragraphs } from "@/lib/catalog";

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
  /** Products: a PRODUCT_STATUSES key, or "" for no label (D-022). */
  status: string;
  /** Industries: paragraphs separated by a blank line. */
  description: string;
  /** Industries: "product:<slug>" / "service:<slug>", in the chosen order. */
  related: string[];
};

export const EMPTY_VALUES: CatalogFormValues = {
  name: "",
  label: "",
  icon: "layers",
  summary: "",
  points: "",
  sort_order: "100",
  image_id: "",
  document_id: "",
  status: "",
  description: "",
  related: [],
};

const MAX_RELATED = 6;

/** "12 of 300 characters" — no maxLength on the boxes, so pasted text is never cut silently; the API refuses what is too long. */
function lengthNote(text: string, max: number): string {
  const length = [...text.trim()].length;
  return length > max ? `${length} of ${max} characters: ${length - max} too many.` : `${length} of ${max} characters.`;
}

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
    status: content.status ?? "",
    description: content.description ?? "",
    related: content.related ?? [],
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
    status: values.status || null,
    description: values.description,
    related: values.related,
  };
}

/** Products and services an industry can link to (shown or hidden; the website links only shown ones). */
function useRelatedOptions(enabled: boolean): { value: string; label: string }[] | null {
  const { request } = useAdmin();
  const [options, setOptions] = useState<{ value: string; label: string }[] | null>(null);
  useEffect(() => {
    if (!enabled) return;
    let cancelled = false;
    Promise.all([request<CatalogSummary[]>("/catalog/products"), request<CatalogSummary[]>("/catalog/services")])
      .then(([products, services]) => {
        if (cancelled) return;
        const option = (type: string, noun: string) => (row: CatalogSummary) => ({ value: `${type}:${row.slug}`, label: `${row.name} (${noun}${row.visible ? "" : ", hidden"})` });
        setOptions([...products.data.map(option("product", "product")), ...services.data.map(option("service", "service"))]);
      })
      .catch(() => !cancelled && setOptions([]));
    return () => {
      cancelled = true;
    };
  }, [enabled, request]);
  return options;
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
  banknote: "Bank note",
  "graduation-cap": "Graduation cap",
  "heart-pulse": "Heart",
  "shopping-cart": "Shopping cart",
  truck: "Truck",
  landmark: "Public building",
  sprout: "Sprout",
  factory: "Factory",
  "hand-heart": "Helping hand",
  store: "Shop",
};

/** Fields for a product, service or industry, with a live preview of the website card. */
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
  const products = kind === "products";
  const industries = kind === "industries";
  const relatedOptions = useRelatedOptions(industries);
  const toggleRelated = (value: string, checked: boolean) =>
    onChange({ ...values, related: checked ? [...values.related, value] : values.related.filter((r) => r !== value) });
  const preview = {
    image: image && image.width && image.height ? { src: mediaHref(apiBase, image.path), alt: image.alt_text ?? "", width: image.width, height: image.height } : null,
    document: document ? { href: mediaHref(apiBase, document.path), title: document.title ?? document.filename, format: document.format, sizeBytes: document.size_bytes } : null,
    slug: "preview",
    name: values.name || "Name",
    label: values.label || null,
    icon: values.icon,
    summary: values.summary || "Summary",
    points: values.points.split("\n").map((p) => p.trim()).filter(Boolean).slice(0, 5),
    status: isProductStatus(values.status) ? values.status : null,
  };

  return (
    <div className="admin-detail">
      <form className="contact-form form-card" onSubmit={onSubmit} noValidate>
        <FieldShell label="Name" hint={`${lengthNote(values.name, 80)} At least 2.`} error={fields.name}>
          {(props) => <input name="name" value={values.name} onChange={set("name")} required {...props} />}
        </FieldShell>
        {products && (
          <FieldShell label="Label (optional)" hint="Small line above the name, e.g. Paxofi Product." error={fields.label}>
            {(props) => <input name="label" value={values.label} onChange={set("label")} {...props} />}
          </FieldShell>
        )}
        {products && (
          <FieldShell label="Status" hint="How far along the product is. Shown as a label on the product (SRS 14.7)." error={fields.status}>
            {(props) => (
              <select name="status" value={values.status} onChange={set("status")} {...props}>
                <option value="">No label</option>
                {Object.entries(PRODUCT_STATUSES).map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
            )}
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
        <FieldShell label="Summary" hint={`${lengthNote(values.summary, 300)} At least 10. Plain text.`} error={fields.summary}>
          {(props) => <textarea name="summary" rows={4} value={values.summary} onChange={set("summary")} required {...props} />}
        </FieldShell>
        {industries && (
          <FieldShell
            label="Description (optional)"
            hint={`${lengthNote(values.description, 1500)} Shown on the industry's own page. Leave a blank line between paragraphs. Plain text.`}
            error={fields.description}
          >
            {(props) => <textarea name="description" rows={8} value={values.description} onChange={set("description")} {...props} />}
          </FieldShell>
        )}
        {(products || industries) && (
          <FieldShell
            label={industries ? "Example solutions (optional)" : "Key points (optional)"}
            hint="One per line, up to 5, each up to 80 characters."
            error={fields.points}
          >
            {(props) => <textarea name="points" rows={5} value={values.points} onChange={set("points")} {...props} />}
          </FieldShell>
        )}
        {industries && (
          <fieldset className="admin-fieldset" aria-describedby="related-hint">
            <legend>Related products and services (optional)</legend>
            <p id="related-hint" className="form-note">
              Up to {MAX_RELATED}, linked from the industry&apos;s page in the order you tick them. Hidden ones are not linked until they are shown.
            </p>
            {fields.related && <p className="field-error" role="alert">{fields.related}</p>}
            {relatedOptions === null ? (
              <p role="status">Loading…</p>
            ) : (
              <ul className="admin-checklist">
                {relatedOptions.map((option) => {
                  const checked = values.related.includes(option.value);
                  return (
                    <li key={option.value}>
                      <label>
                        <input
                          type="checkbox"
                          name="related"
                          value={option.value}
                          checked={checked}
                          disabled={!checked && values.related.length >= MAX_RELATED}
                          onChange={(event) => toggleRelated(option.value, event.target.checked)}
                        />{" "}
                        {option.label}
                      </label>
                    </li>
                  );
                })}
              </ul>
            )}
          </fieldset>
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
        <p className="form-note">How it looks on the {products ? "Products" : industries ? "Industries" : "Services"} page.</p>
        {products ? <ProductCard item={preview} headingLevel={3} /> : industries ? <IndustryCard item={preview} preview /> : <ServiceCard item={preview} />}
        {industries && paragraphs(values.description).length > 0 && (
          <div className="prose admin-preview__text">
            {paragraphs(values.description).map((paragraph) => (
              <p key={paragraph}>{paragraph}</p>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
