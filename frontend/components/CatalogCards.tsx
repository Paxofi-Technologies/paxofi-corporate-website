import { createElement } from "react";
import type { LucideIcon } from "lucide-react";
import {
  Briefcase,
  Cloud,
  CodeXml,
  Cog,
  Database,
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
import type { CatalogItem } from "@/lib/catalog";

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
    </article>
  );
}

/** Service card as on /services (also used for the staff-area preview). */
export function ServiceCard({ item }: { item: CatalogItem }) {
  return (
    <article className="icon-card">
      <span className="icon-badge icon-badge--blue" aria-hidden="true">
        <CatalogIcon name={item.icon} size={24} />
      </span>
      <h3>{item.name}</h3>
      <p>{item.summary}</p>
    </article>
  );
}
