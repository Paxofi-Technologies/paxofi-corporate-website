import type { Metadata } from "next";
import ApplicationList from "./ApplicationList";

export const metadata: Metadata = { title: "Recruitment" };

export default function RecruitmentPage() {
  return <ApplicationList />;
}
