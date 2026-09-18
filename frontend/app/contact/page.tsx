import type { Metadata } from "next";
import ContactForm from "./ContactForm";
import "./contact.css";

export const metadata: Metadata = { title: "Contact" };

export default function Contact() {
  return (
    <section className="section">
      <div className="container">
        <span className="eyebrow">CONTACT</span>
        <h1>Let&apos;s talk about what you&apos;re building.</h1>
        <p className="hero-copy">
          Tell us what you are trying to achieve and we&apos;ll help map the next practical step.
        </p>
        <ContactForm apiUrl={process.env.NEXT_PUBLIC_API_URL || "/api/v1/forms/contact/submit"} />
      </div>
    </section>
  );
}
