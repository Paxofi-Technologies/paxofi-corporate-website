<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application\Careers;

use Paxofi\CorporateWebsite\Application\Careers\CertificatePdf;
use PHPUnit\Framework\TestCase;

final class CertificatePdfTest extends TestCase
{
    private const CERTIFICATE = [
        'holder_name' => 'Ada Lovelace', 'role_title' => 'Software Engineer Fellow', 'engagement_label' => 'Fellow', 'cohort' => 'Cohort 1',
        'started_on' => '2026-11-02', 'completed_on' => '2027-04-20', 'code' => 'PIF-K7M2-9QXA-R4TD', 'issued_on' => '2027-04-21',
        'verify_url' => 'https://careers.paxofi.com/verify',
    ];

    public function testItIsAWellFormedOnePagePdfWithTheFactsPrinted(): void
    {
        $pdf = CertificatePdf::render(self::CERTIFICATE);

        self::assertStringStartsWith("%PDF-1.4\n", $pdf);
        self::assertStringEndsWith("%%EOF\n", $pdf);
        foreach (['(Ada Lovelace)', 'PIF-K7M2-9QXA-R4TD', 'Software Engineer Fellow', 'Cohort 1, from 2 November 2026 to 20 April 2027', 'https://careers.paxofi.com/verify', 'not a record of employment'] as $text) {
            self::assertStringContainsString($text, $pdf);
        }
        self::assertSame(1, preg_match_all('/\/Type \/Page /', $pdf));
    }

    public function testTheCrossReferenceTableIsExact(): void
    {
        $pdf = CertificatePdf::render(self::CERTIFICATE);

        self::assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF/', $pdf, $start));
        self::assertSame('xref', substr($pdf, (int) $start[1], 4));
        self::assertGreaterThan(0, preg_match_all('/(\d{10}) 00000 n \n/', $pdf, $offsets));
        foreach ($offsets[1] as $index => $offset) {
            self::assertStringStartsWith(($index + 1) . ' 0 obj', substr($pdf, (int) $offset, 8));
        }
        self::assertSame(1, preg_match('/\/Length (\d+) >>\nstream\n/', $pdf, $length));
        self::assertSame((int) $length[1], strlen(substr($pdf, (int) strpos($pdf, "stream\n") + 7, (int) strpos($pdf, 'endstream') - (int) strpos($pdf, "stream\n") - 7)));
    }

    public function testSpecialCharactersCannotBreakTheContentStream(): void
    {
        $pdf = CertificatePdf::render(['holder_name' => "Ana (O'Brien) \\ Ñandú \u{4E2D}\n) Tj ET", 'cohort' => null, 'started_on' => null] + self::CERTIFICATE);

        self::assertStringContainsString('Ana \\(O\'Brien\\) \\\\ ', $pdf, 'brackets and backslashes are escaped');
        self::assertStringContainsString("\xD1" . 'and', $pdf, 'accented Latin letters are kept as Windows-1252');
        self::assertStringContainsString('completing the programme on 20 April 2027', $pdf);
        self::assertStringNotContainsString("\n) Tj ET", $pdf);
    }

    public function testALongNameIsShrunkToFit(): void
    {
        $pdf = CertificatePdf::render(['holder_name' => str_repeat('Wolfeschlegelsteinhausenbergerdorff ', 4)] + self::CERTIFICATE);

        self::assertSame(1, preg_match('/BT \/F2 ([\d.]+) Tf [\d. ]+ rg ([\d.]+) [\d.]+ Td \(Wolfe/', $pdf, $match));
        self::assertLessThan(34.0, (float) $match[1], 'smaller than the normal 34 pt');
        self::assertGreaterThanOrEqual(20.0, (float) $match[2], 'and still inside the page border');
    }
}
