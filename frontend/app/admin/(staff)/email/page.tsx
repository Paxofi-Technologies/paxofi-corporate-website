import type { Metadata } from "next";
import EmailSettings from "./EmailSettings";

export const metadata: Metadata = { title: "Email" };

export default function EmailPage() {
  return <EmailSettings />;
}
