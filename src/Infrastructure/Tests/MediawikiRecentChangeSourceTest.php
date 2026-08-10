<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Domain\RecentChange\RecentChangeCursor;
use App\Domain\RecentChange\UserKind;
use App\Infrastructure\RecentChange\MediawikiRecentChangeSource;
use DateTimeImmutable;
use Mediawiki\Api\MediawikiApi;
use PHPUnit\Framework\TestCase;

class MediawikiRecentChangeSourceTest extends TestCase
{
    private function source(MediawikiApi $api, int $maxPages = 20): MediawikiRecentChangeSource
    {
        return new MediawikiRecentChangeSource($api, $maxPages);
    }

    public function testStreamMapsRowsAcrossPagesInOrder()
    {
        $api = $this->createMock(MediawikiApi::class);
        $api->method('getRequest')->willReturnOnConsecutiveCalls(
            [
                'query' => ['recentchanges' => [
                    ['revid' => 1, 'old_revid' => 0, 'title' => 'Page A', 'ns' => 0, 'user' => 'Alice',
                        'timestamp' => '2026-08-10T10:00:00Z', 'newlen' => 200, 'oldlen' => 100,
                        'comment' => 'first edit', 'tags' => []],
                ]],
                'continue' => ['rccontinue' => 'CONT1'],
            ],
            [
                'query' => ['recentchanges' => [
                    ['revid' => 2, 'title' => 'Page B', 'ns' => 0, 'user' => 'Bob',
                        'timestamp' => '2026-08-10T10:05:00Z'],
                ]],
                // no "continue" : last page
            ],
        );

        $events = iterator_to_array(
            $this->source($api)->stream(new RecentChangeCursor(new DateTimeImmutable('2026-08-10T09:00:00Z')))
        );

        $this::assertCount(2, $events);
        $this::assertSame(1, $events[0]->revid);
        $this::assertSame('Page A', $events[0]->page);
        $this::assertSame(100, $events[0]->sizeDiff);
        $this::assertSame(2, $events[1]->revid);
        $this::assertSame('Page B', $events[1]->page);
        $this::assertNull($events[1]->sizeDiff);
    }

    public function testStreamStopsAtMaxPagesEvenIfContinueOffered()
    {
        $api = $this->createMock(MediawikiApi::class);
        $api->method('getRequest')->willReturn([
            'query' => ['recentchanges' => [['revid' => 1, 'title' => 'X', 'ns' => 0, 'user' => 'U', 'timestamp' => '2026-08-10T10:00:00Z']]],
            'continue' => ['rccontinue' => 'ALWAYS_MORE'],
        ]);

        $events = iterator_to_array($this->source($api, maxPages: 2)->stream(new RecentChangeCursor(new DateTimeImmutable())));

        $this::assertCount(2, $events);
    }

    public function testStreamMapsBotFlagToBotUserKind()
    {
        $api = $this->createMock(MediawikiApi::class);
        $api->method('getRequest')->willReturn([
            'query' => ['recentchanges' => [
                ['revid' => 1, 'title' => 'X', 'ns' => 0, 'user' => 'CodexBot', 'timestamp' => '2026-08-10T10:00:00Z', 'bot' => ''],
            ]],
        ]);

        $events = iterator_to_array($this->source($api)->stream(new RecentChangeCursor(new DateTimeImmutable())));

        $this::assertSame(UserKind::Bot, $events[0]->userKind);
    }

    public function testStreamMapsAnonFlagToIpUserKind()
    {
        $api = $this->createMock(MediawikiApi::class);
        $api->method('getRequest')->willReturn([
            'query' => ['recentchanges' => [
                ['revid' => 1, 'title' => 'X', 'ns' => 0, 'user' => '1.2.3.4', 'timestamp' => '2026-08-10T10:00:00Z', 'anon' => ''],
            ]],
        ]);

        $events = iterator_to_array($this->source($api)->stream(new RecentChangeCursor(new DateTimeImmutable())));

        $this::assertSame(UserKind::Ip, $events[0]->userKind);
    }

    public function testStreamMapsNoFlagsToRegisteredUserKind()
    {
        $api = $this->createMock(MediawikiApi::class);
        $api->method('getRequest')->willReturn([
            'query' => ['recentchanges' => [
                ['revid' => 1, 'title' => 'X', 'ns' => 0, 'user' => 'SomeEditor', 'timestamp' => '2026-08-10T10:00:00Z'],
            ]],
        ]);

        $events = iterator_to_array($this->source($api)->stream(new RecentChangeCursor(new DateTimeImmutable())));

        $this::assertSame(UserKind::Registered, $events[0]->userKind);
    }

    public function testStreamOnEmptyResultIsEmpty()
    {
        $api = $this->createMock(MediawikiApi::class);
        $api->method('getRequest')->willReturn([]);

        $events = iterator_to_array($this->source($api)->stream(new RecentChangeCursor(new DateTimeImmutable())));

        $this::assertSame([], $events);
    }
}
