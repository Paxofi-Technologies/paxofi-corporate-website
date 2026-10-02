import { ArrowUpRight, GraduationCap, HeartHandshake, Users } from "lucide-react";
import { IconCard, PageHero, SectionHead } from "@/components/Sections";
import { SITE, pageMetadata } from "@/lib/site";

export const metadata = pageMetadata(
  "Careers",
  "Build with Paxofi: opportunities for engineers, designers, product thinkers, marketers and operators, including the Paxofi Innovation Fellowship.",
  "/careers",
);

export default function Careers() {
  return (
    <>
      <PageHero
        eyebrow="Careers"
        title="Build with Paxofi."
        intro="We welcome engineers, designers, product thinkers, marketers and operators who want to learn, contribute and ship useful technology."
      />

      <section className="section">
        <div className="container">
          <SectionHead eyebrow="Why Paxofi" title="People first, always." />
          <div className="grid grid-3">
            <IconCard icon={Users} title="People First">
              We invest in people, with real responsibility and support to grow.
            </IconCard>
            <IconCard icon={GraduationCap} title="Learn by building" tone="teal">
              Work on real products and client projects, not exercises.
            </IconCard>
            <IconCard icon={HeartHandshake} title="Shared impact" tone="purple">
              Help create lasting possibilities across Africa and beyond.
            </IconCard>
          </div>
        </div>
      </section>

      <section className="section section--tint">
        <div className="container">
          <div className="spotlight">
            <div>
              <span className="eyebrow eyebrow--on-dark">Paxofi Innovation Fellowship</span>
              <h2>Learn through real product and technology work.</h2>
              <p>Our fellowship creates practical opportunities to learn through real product and technology work.</p>
            </div>
            <a className="button button--light" href={SITE.careersUrl} rel="noopener">
              View opportunities <ArrowUpRight size={18} aria-hidden="true" />
            </a>
          </div>
          <p className="page-note">
            All open roles, applications and recruitment updates are handled on our careers site,{" "}
            <a href={SITE.careersUrl} rel="noopener">career.paxofi.com</a>.
          </p>
        </div>
      </section>
    </>
  );
}
