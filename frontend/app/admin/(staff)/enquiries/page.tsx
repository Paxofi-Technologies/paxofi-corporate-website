import type { Metadata } from "next";
import EnquiryInbox from "./EnquiryInbox";

export const metadata: Metadata = { title: "Enquiries" };

export default function EnquiriesPage() {
  return <EnquiryInbox />;
}
