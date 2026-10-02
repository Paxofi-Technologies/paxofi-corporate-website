import { Clock, Mail, MapPin } from "lucide-react";
import ContactForm from "./ContactForm";
import { PageHero } from "@/components/Sections";
import { contactEndpoint, resolveApiBase } from "@/lib/contact";
import { SITE, pageMetadata } from "@/lib/site";

// Rendered per request so API_BASE_URL can be changed on the server
// (cPanel → Application Manager → Environment variables) without a rebuild.
export const dynamic = "force-dynamic";

export const metadata = pageMetadata(
  "Contact",
  "Talk to Paxofi Technologies about software engineering, digital products, infrastructure or partnerships.",
  "/contact",
);

export default function Contact() {
  return (
    <>
      <PageHero
        eyebrow="Contact"
        title="Let's talk about what you're building."
        intro="Tell us what you are trying to achieve and we'll help map the next practical step."
      />
      <section className="section">
        <div className="container contact-layout">
          <div className="form-card">
            <h2>Send us a message</h2>
            <ContactForm endpoint={contactEndpoint(resolveApiBase(process.env))} />
          </div>
          <aside className="contact-aside" aria-label="Other ways to reach us">
            <h2>Other ways to reach us</h2>
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
                  We reply to enquiries by email.
                </span>
              </li>
              <li>
                <MapPin size={20} aria-hidden="true" />
                <span>
                  <strong>Where we work</strong>
                  Across Africa and beyond.
                </span>
              </li>
            </ul>
            <p className="aside-signoff">A brighter tomorrow, together.</p>
          </aside>
        </div>
      </section>
    </>
  );
}
