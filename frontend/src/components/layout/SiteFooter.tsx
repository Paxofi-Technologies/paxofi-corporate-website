import { Container } from '@/components/ui/Container';

export function SiteFooter() {
  return (
    <footer className="border-t border-foreground/10 py-8">
      <Container className="text-sm text-foreground/60">
        <p>© {new Date().getFullYear()} Paxofi Technologies LTD. All rights reserved.</p>
      </Container>
    </footer>
  );
}
