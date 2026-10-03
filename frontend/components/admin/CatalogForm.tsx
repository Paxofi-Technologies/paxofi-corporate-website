"use client";

import { FormEvent } from "react";
import { ProductCard, ServiceCard } from "@/components/CatalogCards";
import { FieldShell } from "./AdminForm";
import { CatalogContent, CatalogKind } from "@/lib/admin-api";
import { CATALOG_ICONS } from "@/lib/catalog";

export type CatalogFormValues = { name: string; label: string; icon: string; summary: string; points: string; sort_order: string };

export function toFormValues(content: CatalogContent): CatalogFormValues {
  return {
    name: content.name,
    label: content.label ?? "",
    icon: content.icon,
    summary: content.summary,
    points: content.points.join("\n"),
    sort_order: String(content.sort_order),
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
  };
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
  children,
}: {
  kind: CatalogKind;
  values: CatalogFormValues;
  onChange: (values: CatalogFormValues) => void;
  fields: Record<string, string>;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  children: React.ReactNode;
}) {
  const set = (key: keyof CatalogFormValues) => (event: { target: { value: string } }) => onChange({ ...values, [key]: event.target.value });
  const preview = {
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
