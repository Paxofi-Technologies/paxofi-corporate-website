import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowRight,
  ChartColumn,
  Cloud,
  CodeXml,
  Globe,
  Layers,
  Lightbulb,
  Megaphone,
  Users,
} from "lucide-react";
import { CatalogIcon } from "@/components/CatalogCards";
import { LogoMark } from "@/components/Logo";
import { CtaBand, IconCard, SectionHead } from "@/components/Sections";
import { loadCatalog } from "@/lib/catalog";
import { resolveApiBase } from "@/lib/contact";
import { pageCopy } from "@/lib/page-copy-server";

export async function generateMetadata(): Promise<Metadata> {
  const t = await pageCopy("home");
  return { alternates: { canonical: "/" }, description: t.meta_description };
}

// Card icons and colours stay fixed; their words are edited in the staff area (D-015).
const values = [
  { icon: Users, tone: "blue" },
  { icon: Lightbulb, tone: "sky" },
  { icon: ChartColumn, tone: "teal" },
  { icon: Globe, tone: "purple" },
] as const;
const services = [CodeXml, Layers, Cloud, Megaphone];
const approach = ["01", "02", "03"];

export default async function Home() {
  // Products are edited in the staff area (D-011); every other word on the page too (D-015).
  const [products, t] = await Promise.all([loadCatalog("products", resolveApiBase(process.env)), pageCopy("home")]);
  return (
    <>
      <section className="hero">
        <div className="container hero-grid">
          <div className="hero-copy">
            <span className="eyebrow eyebrow--on-dark">{t.hero_eyebrow}</span>
            <h1>
              {t.hero_title_start} <span className="text-sky">{t.hero_title_highlight}</span> {t.hero_title_end}
            </h1>
            <p className="hero-lead">{t.hero_lead}</p>
            <div className="actions">
              <Link className="button button--primary" href="/contact">
                {t.hero_primary_button} <ArrowRight size={18} aria-hidden="true" />
              </Link>
              <Link className="button button--ghost" href="/services">
                {t.hero_secondary_button}
              </Link>
            </div>
          </div>
          <div className="hero-visual" aria-hidden="true">
            <div className="hero-plane hero-plane--back" />
            <div className="hero-plane hero-plane--front" />
            <div className="hero-card">
              <LogoMark size={72} />
              <p className="hero-card-line">{t.hero_card_line}</p>
              <p className="hero-card-sub">{t.hero_card_sub}</p>
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <SectionHead eyebrow={t.values_eyebrow} title={t.values_title} intro={t.values_intro} />
          <div className="grid grid-4">
            {values.map(({ icon, tone }, i) => (
              <IconCard key={i} icon={icon} title={t[`value_${i + 1}_title`]} tone={tone}>
                {t[`value_${i + 1}_text`]}
              </IconCard>
            ))}
          </div>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <SectionHead eyebrow={t.services_eyebrow} title={t.services_title} intro={t.services_intro} />
          <div className="grid grid-4">
            {services.map((icon, i) => (
              <IconCard key={i} icon={icon} title={t[`service_${i + 1}_title`]}>
                {t[`service_${i + 1}_text`]}
              </IconCard>
            ))}
          </div>
          <Link className="text-link section-link" href="/services">
            {t.services_link} <ArrowRight size={16} aria-hidden="true" />
          </Link>
        </div>
      </section>

      <section className="section section--navy">
        <div className="container">
          <SectionHead eyebrow={t.products_eyebrow} title={t.products_title} />
          <div className="grid grid-2">
            {products.map(({ slug, icon, label, name, summary }) => (
              <article className="product-card" key={slug}>
                <span className="product-icon" aria-hidden="true">
                  <CatalogIcon name={icon} size={28} />
                </span>
                {label && <span className="product-label">{label}</span>}
                <h3>{name}</h3>
                <p>{summary}</p>
                <Link href="/products">
                  More about {name} <ArrowRight size={16} aria-hidden="true" />
                </Link>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <SectionHead eyebrow={t.approach_eyebrow} title={t.approach_title} />
          <ol className="steps">
            {approach.map((step, i) => (
              <li className="step" key={step}>
                <span className="step-number" aria-hidden="true">
                  {step}
                </span>
                <h3>{t[`step_${i + 1}_title`]}</h3>
                <p>{t[`step_${i + 1}_text`]}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <CtaBand title={t.cta_title} text={t.cta_text} label={t.cta_button} />
    </>
  );
}
