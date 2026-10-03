import type { Metadata } from "next";
import MediaLibrary from "./MediaLibrary";

export const metadata: Metadata = { title: "Media" };

export default function MediaPage() {
  return <MediaLibrary />;
}
