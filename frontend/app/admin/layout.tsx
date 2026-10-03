import type { Metadata } from "next";
import { AdminProvider } from "@/components/admin/AdminContext";
import { resolveApiBase } from "@/lib/contact";

// Staff area (decision D-009). Never indexed; the API enforces every permission,
// this UI only hides what a role cannot use.
export const metadata: Metadata = {
  title: { default: "Staff area", template: "%s — Staff area | Paxofi Technologies" },
  robots: { index: false, follow: false },
};

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="admin">
      <a className="skip-link" href="#main">Skip to content</a>
      <AdminProvider apiBase={resolveApiBase(process.env)}>{children}</AdminProvider>
    </div>
  );
}
