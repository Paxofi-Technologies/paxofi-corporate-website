import type { Metadata } from "next";
import { Suspense } from "react";
import ContentList from "./ContentList";

export const metadata: Metadata = { title: "Content" };

export default function ContentPage() {
  return (
    <Suspense fallback={<p role="status">Loading…</p>}>
      <ContentList />
    </Suspense>
  );
}
