<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Domain\RecentChange\RecentChangeEvent;
use App\Domain\RecentChange\UserKind;
use App\Infrastructure\RecentChange\RecentChangeSignalAdapter;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Simplon\Mysql\Mysql;

class RecentChangeSignalAdapterTest extends TestCase
{
    public function testRecordInsertsMappedRowWithInsertIgnore()
    {
        $event = new RecentChangeEvent(
            revid: 123,
            oldRevid: 100,
            page: 'Some Page',
            ns: 0,
            user: 'Alice',
            userKind: UserKind::Registered,
            timestamp: new DateTimeImmutable('2026-08-10 14:05:20', new DateTimeZone('Europe/Paris')),
            sizeDiff: 42,
            comment: 'fixed a typo',
            tags: ['visualeditor', 'mobile edit'],
        );

        $db = $this->createMock(Mysql::class);
        $db->expects($this->once())
            ->method('insert')
            ->with(
                'rc_signal',
                $this->callback(function (array $data) {
                    $this::assertSame(123, $data['revid']);
                    $this::assertSame(100, $data['old_revid']);
                    $this::assertSame('Some Page', $data['page']);
                    $this::assertSame('registered', $data['user_kind']);
                    // Paris DST (+2) converted to UTC for storage.
                    $this::assertSame('2026-08-10 12:05:20', $data['rc_timestamp']);
                    $this::assertSame(42, $data['size_diff']);
                    $this::assertSame('visualeditor,mobile edit', $data['tags']);
                    $this::assertSame('observed', $data['signal']);
                    $this::assertSame('new', $data['state']);

                    return true;
                }),
                true
            );

        (new RecentChangeSignalAdapter($db))->record($event, 'observed');
    }

    public function testRecordTruncatesOverlongCommentAndTags()
    {
        $event = new RecentChangeEvent(
            revid: 1,
            oldRevid: null,
            page: 'X',
            ns: 0,
            user: 'U',
            userKind: UserKind::Bot,
            timestamp: new DateTimeImmutable('2026-08-10 10:00:00', new DateTimeZone('UTC')),
            sizeDiff: null,
            comment: str_repeat('a', 600),
            tags: [],
        );

        $db = $this->createMock(Mysql::class);
        $db->expects($this->once())
            ->method('insert')
            ->with(
                'rc_signal',
                $this->callback(function (array $data) {
                    $this::assertSame(500, mb_strlen($data['comment']));
                    $this::assertNull($data['tags']);

                    return true;
                }),
                true
            );

        (new RecentChangeSignalAdapter($db))->record($event, 'observed');
    }
}
