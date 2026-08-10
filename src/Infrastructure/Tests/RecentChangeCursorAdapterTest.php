<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\RecentChange\RecentChangeCursorAdapter;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Simplon\Mysql\Mysql;

class RecentChangeCursorAdapterTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        $this->originalTimezone = date_default_timezone_get();
        // Reproduces myBootstrap.php's runtime setting — this is what caused the bug
        // (a naive DATETIME string re-parsed under a non-UTC default timezone).
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
    }

    public function testSetStoresTimestampConvertedToUtc()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchColumn')->willReturn(null); // no existing row
        $db->expects($this->once())
            ->method('insert')
            ->with(
                'rc_cursor',
                $this->callback(fn(array $data) => $data['last_timestamp'] === '2026-08-10 12:05:20')
            );

        // 14:05:20 Paris time (DST, UTC+2) must be stored as 12:05:20 UTC.
        $timestamp = new DateTimeImmutable('2026-08-10 14:05:20', new DateTimeZone('Europe/Paris'));

        (new RecentChangeCursorAdapter($db))->set('mediawiki-rc', $timestamp);
    }

    public function testGetInterpretsStoredValueAsUtcRegardlessOfDefaultTimezone()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchColumn')->willReturn('2026-08-10 12:05:20');

        $result = (new RecentChangeCursorAdapter($db))->get('mediawiki-rc');

        // Must read back as 12:05:20 UTC, not 12:05:20 Paris (which would be a
        // different, wrong instant despite the identical wall-clock digits).
        $this::assertSame('2026-08-10T12:05:20+00:00', $result->format('c'));
    }

    public function testSetThenGetRoundTripsToTheExactSameInstant()
    {
        $stored = null;
        $db = $this->createMock(Mysql::class);
        $db->method('fetchColumn')->willReturnCallback(function () use (&$stored) {
            return $stored;
        });
        $db->method('insert')->willReturnCallback(function (string $table, array $data) use (&$stored) {
            $stored = $data['last_timestamp'];

            return 1;
        });

        $adapter = new RecentChangeCursorAdapter($db);
        $original = new DateTimeImmutable('2026-08-10 14:05:20', new DateTimeZone('Europe/Paris'));

        $adapter->set('mediawiki-rc', $original);
        $roundTripped = $adapter->get('mediawiki-rc');

        $this::assertSame($original->getTimestamp(), $roundTripped->getTimestamp());
    }

    public function testGetReturnsNullWhenNoCursorYet()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchColumn')->willReturn(null);

        $this::assertNull((new RecentChangeCursorAdapter($db))->get('mediawiki-rc'));
    }
}
