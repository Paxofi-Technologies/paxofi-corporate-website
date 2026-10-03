import type { Metadata } from "next";
import AnalyticsReportView from "./AnalyticsReportView";

export const metadata: Metadata = { title: "Analytics" };

export default function AnalyticsPage() {
  return <AnalyticsReportView />;
}
