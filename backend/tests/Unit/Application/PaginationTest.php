<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Pagination;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function testDefaults(): void
    {
        $pagination = Pagination::fromQuery([]);

        self::assertSame(1, $pagination->page);
        self::assertSame(20, $pagination->perPage);
        self::assertSame(0, $pagination->offset());
    }

    public function testOffsetAndMeta(): void
    {
        $pagination = Pagination::fromQuery(['page' => '3', 'per_page' => '10']);

        self::assertSame(20, $pagination->offset());
        self::assertSame(['page' => 3, 'per_page' => 10, 'total' => 25, 'total_pages' => 3], $pagination->meta(25));
    }

    public function testRejectsOutOfRangeValues(): void
    {
        $this->expectException(ValidationFailed::class);
        Pagination::fromQuery(['page' => '0', 'per_page' => '500']);
    }

    public function testRejectsPagesThatWouldOverflowTheOffset(): void
    {
        $this->expectException(ValidationFailed::class);
        Pagination::fromQuery(['page' => (string) PHP_INT_MAX]);
    }

    public function testAcceptsTheLastAllowedPage(): void
    {
        self::assertSame((Pagination::MAX_PAGE - 1) * 50, Pagination::fromQuery(['page' => (string) Pagination::MAX_PAGE, 'per_page' => '50'])->offset());
    }

    public function testRejectsNonNumericValues(): void
    {
        $this->expectException(ValidationFailed::class);
        Pagination::fromQuery(['page' => '1 OR 1=1']);
    }
}
