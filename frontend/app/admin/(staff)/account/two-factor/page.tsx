import type { Metadata } from "next";
import TwoFactorSettings from "./TwoFactorSettings";

export const metadata: Metadata = { title: "Two-factor sign-in" };

export default function TwoFactorPage() {
  return <TwoFactorSettings />;
}
