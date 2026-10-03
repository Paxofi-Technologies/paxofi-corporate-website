import type { Metadata } from "next";
import Link from "next/link";
import {
  ArrowRight,
  ChartColumn,
  Cloud,
  CodeXml,
  Cog,
  Globe,
  Layers,
  Lightbulb,
  Megaphone,
  ShieldCheck,
  Users,
} from "lucide-react";
import { LogoMark } from "@/components/Logo";
import { CtaBand, IconCard, SectionHead } from "@/components/Sections";
import { SITE } from "@/lib/site";

export const metadata: Metadata = {
  alternates: { canonical: "/" },
  description: SITE.description,
};

const values = [
  { icon: Users, title: "People First", text: "We invest in people.", tone: "blue" },
  { icon: Lightbulb, title: "Innovation Always", text: "We turn ideas into impact.", tone: "sky" },
  { icon: ChartColumn, title: "Real Solutions", text: "We solve real problems.", tone: "teal" },
  { icon: Globe, title: "A Brighter Tomorrow", text: "We create lasting possibilities.", tone: "purple" },
] as const;

const services = [
  { icon: CodeXml, title: "Software Engineering", text: "Web platforms, APIs and business applications built for reliability." },
  { icon: Layers, title: "Digital Products", text: "From product strategy to production-ready customer experiences." },
  { icon: Cloud, title: "Technology Infrastructure", text: "Practical architecture, deployment and operational foundations." },
  { icon: Megaphone, title: "Digital Growth", text: "Web, digital marketing and technology-enabled business growth." },
];

const products = [
  {
    icon: ShieldCheck,
    label: "Paxofi Product",
    name: "Paxofi Pay",
    text: "A dependable digital payments platform focused on transaction certainty, transparency and trust.",
  },
  {
    icon: Cog,
    label: "Paxofi Technology",
    name: "Paxofi Core Framework",
    text: "A PHP application framework for building maintainable internal, client and SaaS systems.",
  },
];

const approach = [
  { step: "01", title: "Build", text: "We turn clear requirements into secure, maintainable software and products." },
  { step: "02", title: "Operate", text: "We run what we build with monitoring, support and continuous improvement." },
  { step: "03", title: "Scale", text: "We help products and teams grow on the right architecture and processes." },
];

export default function Home() {
  return (
    <>
      <section className="hero">
        <div className="container hero-grid">
          <div className="hero-copy">
            <span className="eyebrow eyebrow--on-dark">People | Products | Possibilities</span>
            <h1>
              Technology for a <span className="text-sky">Brighter</span> Tomorrow.
            </h1>
            <p className="hero-lead">
              We build people, products and solutions that create real impact across Africa and beyond.
            </p>
            <div className="actions">
              <Link className="button button--primary" href="/contact">
                Build with Paxofi <ArrowRight size={18} aria-hidden="true" />
              </Link>
              <Link className="button button--ghost" href="/services">
                Explore our services
              </Link>
            </div>
          </div>
          <div className="hero-visual" aria-hidden="true">
            <div className="hero-plane hero-plane--back" />
            <div className="hero-plane hero-plane--front" />
            <div className="hero-card">
              <LogoMark size={72} />
              <p className="hero-card-line">Build · Operate · Scale</p>
              <p className="hero-card-sub">A brighter tomorrow, together.</p>
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <SectionHead
            eyebrow="What we stand for"
            title="Consistent. Credible. Impactful."
            intro="Our values guide how we build, who we build with and the impact we aim for."
          />
          <div className="grid grid-4">
            {values.map(({ icon, title, text, tone }) => (
              <IconCard key={title} icon={icon} title={title} tone={tone}>
                {text}
              </IconCard>
            ))}
          </div>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <SectionHead
            eyebrow="What we do"
            title="Technology built around real outcomes."
            intro="Strategy, engineering and delivery in one team, from the first idea to a product people rely on."
          />
          <div className="grid grid-4">
            {services.map(({ icon, title, text }) => (
              <IconCard key={title} icon={icon} title={title}>
                {text}
              </IconCard>
            ))}
          </div>
          <Link className="text-link section-link" href="/services">
            See all services <ArrowRight size={16} aria-hidden="true" />
          </Link>
        </div>
      </section>

      <section className="section section--navy">
        <div className="container">
          <SectionHead eyebrow="Our products" title="Products with a purpose." />
          <div className="grid grid-2">
            {products.map(({ icon: Icon, label, name, text }) => (
              <article className="product-card" key={name}>
                <span className="product-icon" aria-hidden="true">
                  <Icon size={28} strokeWidth={1.75} />
                </span>
                <span className="product-label">{label}</span>
                <h3>{name}</h3>
                <p>{text}</p>
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
          <SectionHead eyebrow="How we work" title="Build. Operate. Scale." />
          <ol className="steps">
            {approach.map(({ step, title, text }) => (
              <li className="step" key={title}>
                <span className="step-number" aria-hidden="true">{step}</span>
                <h3>{title}</h3>
                <p>{text}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <CtaBand
        title="Have a problem worth solving?"
        text="Tell us what you are building. We'll help map the next practical step."
      />
    </>
  );
}
