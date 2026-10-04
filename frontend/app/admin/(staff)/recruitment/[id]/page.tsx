import type { Metadata } from "next";
import ApplicationView from "./ApplicationView";

export const metadata: Metadata = { title: "Application" };

export default async function ApplicationPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <ApplicationView id={id} />;
}
