import type { Metadata } from "next";
import TalentPool from "./TalentPool";

export const metadata: Metadata = { title: "Talent pool" };

export default function TalentPoolPage() {
  return <TalentPool />;
}
