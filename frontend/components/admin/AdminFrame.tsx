import Link from "next/link";
import Logo from "@/components/Logo";

/** Chrome for the signed-out screens (sign in, first-time setup). */
export function AdminFrame({ title, intro, children }: { title: string; intro?: string; children: React.ReactNode }) {
  return (
    <>
      <header className="admin-bar">
        <div className="admin-bar__inner">
          <Link href="/" className="admin-brand" aria-label="Paxofi Technologies home">
            <Logo tone="dark" />
          </Link>
          <span className="admin-badge">Staff area</span>
        </div>
      </header>
      <main id="main" tabIndex={-1} className="admin-main admin-main--narrow">
        <div className="form-card">
          <h1 className="admin-title">{title}</h1>
          {intro && <p className="admin-intro">{intro}</p>}
          {children}
        </div>
      </main>
    </>
  );
}
