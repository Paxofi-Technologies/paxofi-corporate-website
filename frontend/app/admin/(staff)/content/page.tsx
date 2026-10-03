import type { Metadata } from "next";
import ContentList from "./ContentList";

export const metadata: Metadata = { title: "Content" };

export default function ContentPage() {
  return <ContentList />;
}
