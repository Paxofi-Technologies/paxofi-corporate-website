import Link from "next/link";
import type { LucideIcon } from "lucide-react";
import { ArrowRight } from "lucide-react";

/** Inner-page hero on Light Gray with the brand dot pattern. */
export function PageHero({ eyebrow, title, intro, children }: {
  eyebrow: string;
  title: React.ReactNode;
  intro?: React.ReactNode;
  children?: React.ReactNode;
}) {
  return (
    <section className="page-hero">
      <div className="container page-hero-inner">
        <span className="eyebrow">{eyebrow}</span>
        <h1>{title}</h1>
        {intro && <p className="lead">{intro}</p>}
        {children}
      </div>
    </section>
  );
}

/** Section heading block used inside sections. */
export function SectionHead({ eyebrow, title, intro, align = "left" }: {
  eyebrow: string;
  title: React.ReactNode;
  intro?: React.ReactNode;
  align?: "left" | "center";
}) {
  return (
    <div className={`section-head section-head--${align}`}>
      <span className="eyebrow">{eyebrow}</span>
      <h2>{title}</h2>
      {intro && <p className="section-intro">{intro}</p>}
    </div>
  );
}

/** Card with an outline icon, title and text. */
export function IconCard({ icon: Icon, title, children, tone = "blue" }: {
  icon: LucideIcon;
  title: string;
  children: React.ReactNode;
  tone?: "blue" | "sky" | "teal" | "purple";
}) {
  return (
    <article className="icon-card">
      <span className={`icon-badge icon-badge--${tone}`} aria-hidden="true">
        <Icon size={24} strokeWidth={1.75} />
      </span>
      <h3>{title}</h3>
      <p>{children}</p>
    </article>
  );
}

/** Gradient call-to-action band (Paxofi Blue → Sky Blue). */
export function CtaBand({ title, text, href = "/contact", label = "Start a conversation" }: {
  title: string;
  text: string;
  href?: string;
  label?: string;
}) {
  return (
    <section className="cta-band">
      <div className="container cta-band-inner">
        <div>
          <h2>{title}</h2>
          <p>{text}</p>
        </div>
        <Link className="button button--light" href={href}>
          {label} <ArrowRight size={18} aria-hidden="true" />
        </Link>
      </div>
    </section>
  );
}
