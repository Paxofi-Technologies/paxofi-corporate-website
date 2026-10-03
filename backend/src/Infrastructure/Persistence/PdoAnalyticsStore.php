<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Analytics\AnalyticsStore;
use Paxofi\CorporateWebsite\Application\Analytics\PageView;

/** analytics_* tables (migration 011, D-014). */
final class PdoAnalyticsStore implements AnalyticsStore
{
    use GuardedQueries;

    /** New source domains per day beyond this are counted as "(other)", so junk referrers cannot grow the table. */
    private const MAX_SOURCES_PER_DAY = 500;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function salt(DateTimeImmutable $day): string
    {
        $date = $day->format('Y-m-d');
        $salt = $this->select('SELECT salt FROM analytics_salts WHERE day = :day', ['day' => $date])[0]['salt'] ?? null;
        if (is_string($salt)) {
            return $salt;
        }

        // A new day: earlier salts and visitor hashes are no longer needed, so they go now.
        $this->write('DELETE FROM analytics_visitors WHERE day < :day', ['day' => $date]);
        $this->write('DELETE FROM analytics_salts WHERE day < :day', ['day' => $date]);
        $this->write('INSERT IGNORE INTO analytics_salts (day, salt) VALUES (:day, :salt)', ['day' => $date, 'salt' => bin2hex(random_bytes(32))]);

        return (string) ($this->select('SELECT salt FROM analytics_salts WHERE day = :day', ['day' => $date])[0]['salt'] ?? '');
    }

    public function record(DateTimeImmutable $day, PageView $view, string $visitorHash): void
    {
        $date = $day->format('Y-m-d');
        foreach (['', $view->path] as $path) {
            $newVisitor = $this->write(
                'INSERT IGNORE INTO analytics_visitors (day, path, visitor) VALUES (:day, :path, :visitor)',
                ['day' => $date, 'path' => $path, 'visitor' => $visitorHash],
            );
            $this->write(
                'INSERT INTO analytics_daily (day, path, views, visitors) VALUES (:day, :path, 1, :new)
                 ON DUPLICATE KEY UPDATE views = views + 1, visitors = visitors + :new2',
                ['day' => $date, 'path' => $path, 'new' => $newVisitor > 0 ? 1 : 0, 'new2' => $newVisitor > 0 ? 1 : 0],
            );
        }

        if ($view->source !== null) {
            $source = $view->source;
            $known = $this->select('SELECT 1 FROM analytics_sources WHERE day = :day AND source = :source', ['day' => $date, 'source' => $source]) !== [];
            if (!$known && (int) ($this->select('SELECT COUNT(*) AS n FROM analytics_sources WHERE day = :day', ['day' => $date])[0]['n'] ?? 0) >= self::MAX_SOURCES_PER_DAY) {
                $source = PageView::OTHER_PAGE;
            }
            $this->write(
                'INSERT INTO analytics_sources (day, source, views) VALUES (:day, :source, 1) ON DUPLICATE KEY UPDATE views = views + 1',
                ['day' => $date, 'source' => $source],
            );
        }

        $this->write(
            'INSERT INTO analytics_devices (day, device, views) VALUES (:day, :device, 1) ON DUPLICATE KEY UPDATE views = views + 1',
            ['day' => $date, 'device' => $view->device],
        );
    }

    public function report(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $range = ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')];

        $daily = array_map(static fn (array $r): array => [
            'day' => (string) $r['day'],
            'views' => (int) $r['views'],
            'visitors' => (int) $r['visitors'],
        ], $this->select("SELECT day, views, visitors FROM analytics_daily WHERE path = '' AND day BETWEEN :from AND :to ORDER BY day", $range));

        $pages = array_map(static fn (array $r): array => [
            'path' => (string) $r['path'],
            'views' => (int) $r['views'],
            'visitors' => (int) $r['visitors'],
        ], $this->select(
            "SELECT path, SUM(views) AS views, SUM(visitors) AS visitors FROM analytics_daily
             WHERE path <> '' AND day BETWEEN :from AND :to GROUP BY path ORDER BY views DESC, path",
            $range,
        ));

        $sources = array_map(static fn (array $r): array => [
            'source' => (string) $r['source'],
            'views' => (int) $r['views'],
        ], $this->select(
            'SELECT source, SUM(views) AS views FROM analytics_sources WHERE day BETWEEN :from AND :to GROUP BY source ORDER BY views DESC, source LIMIT 20',
            $range,
        ));

        $devices = array_map(static fn (array $r): array => [
            'device' => (string) $r['device'],
            'views' => (int) $r['views'],
        ], $this->select(
            'SELECT device, SUM(views) AS views FROM analytics_devices WHERE day BETWEEN :from AND :to GROUP BY device ORDER BY views DESC, device',
            $range,
        ));

        $enquiries = [];
        foreach ($this->select(
            "SELECT DATE(created_at) AS day, COUNT(*) AS n FROM enquiries
             WHERE created_at >= :from AND created_at < DATE_ADD(:to, INTERVAL 1 DAY) AND status <> 'spam'
             GROUP BY DATE(created_at)",
            $range,
        ) as $row) {
            $enquiries[(string) $row['day']] = (int) $row['n'];
        }

        return ['daily' => $daily, 'pages' => $pages, 'sources' => $sources, 'devices' => $devices, 'enquiries' => $enquiries];
    }
}
