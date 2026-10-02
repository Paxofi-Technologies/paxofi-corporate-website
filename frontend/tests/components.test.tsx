// Component tests: render the shared components to static HTML and check the
// markup contract the pages and the accessibility scan depend on.
import { test } from "node:test";
import assert from "node:assert/strict";
import { renderToStaticMarkup } from "react-dom/server";
import { Users } from "lucide-react";
import Logo, { LogoMark } from "@/components/Logo";
import { CtaBand, IconCard, PageHero, SectionHead } from "@/components/Sections";
import SiteFooter from "@/components/SiteFooter";
import SiteHeader from "@/components/SiteHeader";
import { NAV_LINKS, SITE } from "@/lib/site";

const html = (node: React.ReactElement) => renderToStaticMarkup(node);

test("PageHero renders one h1 with the eyebrow and optional intro", () => {
  const withIntro = html(<PageHero eyebrow="About" title="About Paxofi." intro="Intro text" />);
  assert.equal(withIntro.match(/<h1/g)?.length, 1);
  assert.match(withIntro, /<span class="eyebrow">About<\/span>/);
  assert.match(withIntro, /<p class="lead">Intro text<\/p>/);
  assert.doesNotMatch(html(<PageHero eyebrow="About" title="T" />), /class="lead"/);
});

test("SectionHead uses an h2 and the requested alignment", () => {
  const markup = html(<SectionHead eyebrow="What we do" title="Title" align="center" />);
  assert.match(markup, /section-head--center/);
  assert.match(markup, /<h2>Title<\/h2>/);
});

test("IconCard hides its decorative icon from assistive technology", () => {
  const markup = html(<IconCard icon={Users} title="People First" tone="teal">We invest in people.</IconCard>);
  assert.match(markup, /<span class="icon-badge icon-badge--teal" aria-hidden="true"><svg/);
  assert.match(markup, /<h3>People First<\/h3>/);
});

test("CtaBand links to the contact page by default", () => {
  const markup = html(<CtaBand title="Ready?" text="Talk to us." />);
  assert.match(markup, /<a class="button button--light" href="\/contact">Start a conversation/);
  assert.match(html(<CtaBand title="T" text="t" href="/services" label="See services" />), /href="\/services">See services/);
});

test("the logo mark is decorative and its gradient ids are unique per instance", () => {
  const markup = html(<><LogoMark /><LogoMark /></>);
  assert.equal(markup.match(/aria-hidden="true"/g)?.length, 2);
  const ids = [...markup.matchAll(/linearGradient id="([^"]+)"/g)].map((m) => m[1]);
  assert.equal(new Set(ids).size, 2);
  assert.match(html(<Logo />), /Paxofi/);
});

test("the header lists every main section and the contact call to action", () => {
  const markup = html(<SiteHeader />);
  for (const { href, label } of NAV_LINKS) assert.match(markup, new RegExp(`href="${href}"[^>]*>${label}<`));
  assert.match(markup, /class="nav-cta"[^>]*>Talk to us</);
  assert.match(markup, /aria-expanded="false"/);
  assert.match(markup, /aria-label="Main"/);
});

test("the footer links to careers, privacy, terms and the contact email", () => {
  const markup = html(<SiteFooter />);
  assert.match(markup, new RegExp(`href="${SITE.careersUrl}"`));
  assert.match(markup, /href="\/privacy"/);
  assert.match(markup, /href="\/terms"/);
  assert.match(markup, new RegExp(`href="mailto:${SITE.email}"`));
  assert.match(markup, new RegExp(`© ${new Date().getFullYear()} ${SITE.legalName}`));
});

