import type { Metadata } from "next";
import SetupForm from "./SetupForm";

export const metadata: Metadata = { title: "First-time setup" };

export default function SetupPage() {
  return <SetupForm />;
}
