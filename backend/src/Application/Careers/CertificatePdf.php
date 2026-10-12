<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/**
 * Draws a participation certificate as a one-page A4 landscape PDF, with no
 * library: the standard Helvetica fonts only, so nothing is embedded. Text is
 * converted to Windows-1252, so characters outside it print as "?".
 */
final class CertificatePdf
{
    private const WIDTH = 842.0;
    private const HEIGHT = 595.0;

    /** Helvetica advance widths for the characters 32 to 126, in 1/1000 em. */
    private const REGULAR = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
    /** Helvetica-Bold advance widths for the same range. */
    private const BOLD = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    private string $content = '';

    /**
     * @param array{holder_name: string, role_title: string, engagement_label: string, cohort: ?string, started_on: ?string, completed_on: string, code: string, issued_on: string, verify_url: string} $certificate
     */
    public static function render(array $certificate): string
    {
        $pdf = new self();
        $navy = '0.039 0.122 0.267';
        $blue = '0 0.4 1';
        $grey = '0.35 0.4 0.47';

        $pdf->content .= "{$blue} RG 3 w 30 30 782 535 re S\n0.7 0.8 1 RG 0.75 w 38 38 766 519 re S\n";
        $pdf->centered('PAXOFI TECHNOLOGIES LTD', 495, 12, true, $blue);
        $pdf->centered('Certificate of Participation', 450, 36, true, $navy);
        $pdf->centered('Paxofi Innovation Fellowship (PIF) 2026', 420, 14, false, $grey);
        $pdf->centered('This certifies that', 372, 13, false, $grey);
        $pdf->centered($certificate['holder_name'], 325, 34, true, $navy, 700.0);
        $pdf->content .= "{$blue} RG 1.5 w 271 312 m 571 312 l S\n";
        $pdf->centered('took part in the Paxofi Innovation Fellowship as ' . $certificate['role_title'], 280, 14, false, $navy, 720.0);
        $period = $certificate['started_on'] !== null
            ? 'from ' . self::date($certificate['started_on']) . ' to ' . self::date($certificate['completed_on'])
            : 'completing the programme on ' . self::date($certificate['completed_on']);
        $pdf->centered(($certificate['cohort'] !== null ? $certificate['cohort'] . ', ' : '') . $period, 258, 14, false, $navy, 720.0);
        $pdf->centered('PIF is an unpaid learning and development programme. This certificate records participation and is not a record of employment.', 215, 10, false, $grey, 720.0);
        $pdf->centered('Certificate code ' . $certificate['code'], 140, 13, true, $navy);
        $pdf->centered('Check it at ' . $certificate['verify_url'], 120, 11, false, $blue, 720.0);
        $pdf->centered('Issued on ' . self::date($certificate['issued_on']) . ' by Paxofi Technologies Ltd, Human Resources', 80, 10, false, $grey, 720.0);

        return $pdf->document();
    }

    public static function date(string $iso): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($iso, 0, 10));

        return $date === false ? $iso : $date->format('j F Y');
    }

    private function centered(string $text, float $y, float $size, bool $bold, string $color, float $maxWidth = 760.0): void
    {
        $text = self::encode($text);
        $width = self::measure($text, $bold) * $size / 1000;
        if ($width > $maxWidth) {
            $size = max(6.0, $size * $maxWidth / $width);
            $width = self::measure($text, $bold) * $size / 1000;
        }
        $x = (self::WIDTH - $width) / 2;
        $this->content .= sprintf("BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $color, $x, $y, self::escape($text));
    }

    /** Windows-1252 bytes; characters it cannot hold become "?". */
    private static function encode(string $text): string
    {
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', mb_scrub($text, 'UTF-8')) ?? '';

        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    private static function measure(string $text, bool $bold): float
    {
        $table = $bold ? self::BOLD : self::REGULAR;
        $total = 0.0;
        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $code = ord($text[$i]);
            $total += $code >= 32 && $code <= 126 ? $table[$code - 32] : 556;
        }

        return $total;
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function document(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>', self::WIDTH, self::HEIGHT),
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            6 => '<< /Length ' . strlen($this->content) . " >>\nstream\n" . $this->content . 'endstream',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
