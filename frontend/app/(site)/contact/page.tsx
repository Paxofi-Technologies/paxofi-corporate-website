import { Clock, Mail, MapPin } from "lucide-react";
import ContactForm from "./ContactForm";
import { PageHero } from "@/components/Sections";
import { contactEndpoint, resolveApiBase } from "@/lib/contact";
import { pageCopy } from "@/lib/page-copy-server";
import { SITE, pageMetadata } from "@/lib/site";

// Rendered per request so API_BASE_URL can be changed on the server
// (cPanel → Application Manager → Environment variables) without a rebuild.
export const dynamic = "force-dynamic";

export async function generateMetadata() {
  const t = await pageCopy("contact");
  return pageMetadata("Contact", t.meta_description, "/contact");
}

export default async function Contact() {
  const t = await pageCopy("contact");
  return (
    <>
      <PageHero eyebrow={t.hero_eyebrow} title={t.hero_title} intro={t.hero_intro} />
      <section className="section">
        <div className="container contact-layout">
          <div className="form-card">
            <h2>{t.form_title}</h2>
            <ContactForm endpoint={contactEndpoint(resolveApiBase(process.env))} />
          </div>
          <aside className="contact-aside" aria-label={t.aside_title}>
            <h2>{t.aside_title}</h2>
            <ul>
              <li>
                <Mail size={20} aria-hidden="true" />
                <span>
                  <strong>Email</strong>
                  <a href={`mailto:${SITE.email}`}>{SITE.email}</a>
                </span>
              </li>
              <li>
                <Clock size={20} aria-hidden="true" />
                <span>
                  <strong>Response</strong>
                  {t.response_text}
                </span>
              </li>
              <li>
                <MapPin size={20} aria-hidden="true" />
                <span>
                  <strong>Where we work</strong>
                  {t.where_text}
                </span>
              </li>
            </ul>
            <p className="aside-signoff">{t.signoff}</p>
          </aside>
        </div>
      </section>
    </>
  );
}
