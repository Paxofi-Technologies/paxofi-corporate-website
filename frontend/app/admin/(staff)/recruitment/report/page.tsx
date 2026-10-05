import type { Metadata } from "next";
import RecruitmentReport from "./RecruitmentReport";

export const metadata: Metadata = { title: "Recruitment report" };

export default function RecruitmentReportPage() {
  return <RecruitmentReport />;
}
