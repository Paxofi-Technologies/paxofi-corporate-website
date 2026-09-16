import Link from 'next/link';
import { Container } from '@/components/ui/Container';

const navigation = [
  { href: '/', label: 'Home' },
  { href: '/services', label: 'Services' },
  { href: '/products', label: 'Products' },
  { href: '/careers', label: 'Careers' },
  { href: '/contact', label: 'Contact' },
];

export function SiteHeader() {
  return (
    <header className="border-b border-foreground/10">
      <Container className="flex min-h-16 items-center justify-between gap-6">
        <Link href="/" className="text-lg font-semibold tracking-tight" aria-label="Paxofi Technologies home">
          Paxofi
        </Link>
        <nav aria-label="Primary navigation" className="hidden gap-6 md:flex">
          {navigation.map((item) => (
            <Link key={item.href} href={item.href} className="text-sm hover:underline focus-visible:outline-2 focus-visible:outline-offset-4">
              {item.label}
            </Link>
          ))}
        </nav>
      </Container>
    </header>
  );
}
