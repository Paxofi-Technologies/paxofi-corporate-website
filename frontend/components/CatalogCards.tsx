import { createElement } from "react";
import type { LucideIcon } from "lucide-react";
import {
  Briefcase,
  Cloud,
  CodeXml,
  Cog,
  Database,
  FileDown,
  Globe,
  Layers,
  Lightbulb,
  Lock,
  Megaphone,
  Network,
  Rocket,
  ShieldCheck,
  Smartphone,
  Workflow,
} from "lucide-react";
import { describeDownload, type CatalogItem } from "@/lib/catalog";

const ICONS: Record<string, LucideIcon> = {
  "shield-check": ShieldCheck,
  cog: Cog,
  code: CodeXml,
  network: Network,
  rocket: Rocket,
  workflow: Workflow,
  cloud: Cloud,
  megaphone: Megaphone,
  layers: Layers,
  globe: Globe,
  lightbulb: Lightbulb,
  briefcase: Briefcase,
  smartphone: Smartphone,
  database: Database,
  lock: Lock,
};

/** Draws one of the staff-selectable icons (unknown keys fall back to Layers). */
export function CatalogIcon({ name, size }: { name: string; size: number }) {
  return createElement(ICONS[name] ?? Layers, { size, strokeWidth: 1.75 });
}

/**
 * The item's picture (D-012). A plain img: the file comes from the API host,
 * already resized and stripped of metadata on upload, so Next's image
 * optimiser is not needed; width/height reserve the space while it loads.
 */
function CardImage({ item }: { item: CatalogItem }) {
  if (!item.image) return null;
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      className="card-image"
      src={item.image.src}
      alt={item.image.alt}
      width={item.image.width}
      height={item.image.height}
      loading="lazy"
      decoding="async"
    />
  );
}

/** Download link for the item's document, with its format and size for screen readers too. */
function CardDownload({ item }: { item: CatalogItem }) {
  if (!item.document) return null;
  return (
    <a className="card-download" href={item.document.href} download>
      <FileDown size={18} strokeWidth={1.75} aria-hidden="true" />
      <span>
        {item.document.title}{" "}
        <span className="card-download__meta">({describeDownload(item.document.format, item.document.sizeBytes)})</span>
      </span>
    </a>
  );
}

/** Product card as on /products (also used for the staff-area preview). */
export function ProductCard({
  item,
  large = true,
  headingLevel = 2,
}: {
  item: CatalogItem;
  large?: boolean;
  headingLevel?: 2 | 3;
}) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  return (
    <article
      className={large ? "product-card product-card--large" : "product-card"}
    >
      <CardImage item={item} />
      <span className="product-icon" aria-hidden="true">
        <CatalogIcon name={item.icon} size={28} />
      </span>
      {item.label && <span className="product-label">{item.label}</span>}
      <Heading>{item.name}</Heading>
      <p>{item.summary}</p>
      {large && item.points.length > 0 && (
        <ul className="check-list">
          {item.points.map((point) => (
            <li key={point}>{point}</li>
          ))}
        </ul>
      )}
      {large && <CardDownload item={item} />}
    </article>
  );
}

/** Service card as on /services (also used for the staff-area preview). */
export function ServiceCard({ item }: { item: CatalogItem }) {
  return (
    <article className="icon-card">
      <CardImage item={item} />
      <span className="icon-badge icon-badge--blue" aria-hidden="true">
        <CatalogIcon name={item.icon} size={24} />
      </span>
      <h3>{item.name}</h3>
      <p>{item.summary}</p>
      <CardDownload item={item} />
    </article>
  );
}
