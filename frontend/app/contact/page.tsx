import ContactForm from "./ContactForm";
import { contactEndpoint, resolveApiBase } from "@/lib/contact";
import { pageMetadata } from "@/lib/site";
import "./contact.css";

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
    <section className="section">
      <div className="container">
        <span className="eyebrow">CONTACT</span>
        <h1>Let&apos;s talk about what you&apos;re building.</h1>
        <p className="hero-copy">
          Tell us what you are trying to achieve and we&apos;ll help map the next practical step.
        </p>
        <ContactForm endpoint={contactEndpoint(resolveApiBase(process.env))} />
      </div>
    </section>
  );
}
