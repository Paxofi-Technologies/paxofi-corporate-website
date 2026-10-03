import type { Metadata } from "next";
import EnquiryDetail from "./EnquiryDetail";

export const metadata: Metadata = { title: "Enquiry" };

export default async function EnquiryPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <EnquiryDetail id={id} />;
}
