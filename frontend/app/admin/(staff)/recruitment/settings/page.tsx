import type { Metadata } from "next";
import CareersSettingsForm from "./CareersSettingsForm";

export const metadata: Metadata = { title: "Applications opening" };

export default function CareersSettingsPage() {
  return <CareersSettingsForm />;
}
