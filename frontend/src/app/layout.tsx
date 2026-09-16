import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import './globals.css';

export const metadata: Metadata = {
  title: {
    default: 'Paxofi Technologies LTD',
    template: '%s | Paxofi Technologies LTD',
  },
  description: 'Paxofi Technologies LTD corporate website.',
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
