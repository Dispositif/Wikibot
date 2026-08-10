<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\RecentChange;

use App\Domain\InfrastructurePorts\RecentChangeSourceInterface;
use App\Domain\RecentChange\RecentChangeCursor;
use App\Domain\RecentChange\RecentChangeEvent;
use App\Domain\RecentChange\UserKind;
use DateTimeImmutable;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;

/**
 * https://www.mediawiki.org/wiki/API:RecentChanges — ns0 only (2026-08 decision, see
 * Lot 3 discussion : no case seen yet for tracking other namespaces), rcdir=newer so a
 * resumed cursor walks forward instead of backward, rctype=edit|new to exclude log
 * entries (moves, protections...) which aren't edits.
 *
 * ASSUMPTION TO VERIFY during the Lot 3 dry-run : rcprop=flags is assumed to add
 * boolean-presence keys ("bot", "anon"...) directly on each row, per MediaWiki API docs.
 * Not exercised against live data yet — if wrong, userKind mapping below silently
 * misclassifies everything as Registered rather than erroring, so check byUserKind
 * counts from the dry-run script against known bot activity before trusting them.
 */
class MediawikiRecentChangeSource implements RecentChangeSourceInterface
{
    private const NAMESPACE = 0;
    private const LIMIT_PER_PAGE = 500;

    public function __construct(
        private readonly MediawikiApi $api,
        private readonly int $maxPagesPerStream = 20,
    ) {
    }

    public function stream(RecentChangeCursor $cursor): iterable
    {
        $params = [
            'list' => 'recentchanges',
            'rcnamespace' => self::NAMESPACE,
            'rcdir' => 'newer',
            'rcstart' => $cursor->since->format('c'),
            'rctype' => 'edit|new',
            'rcprop' => 'ids|title|timestamp|user|comment|sizes|flags|tags',
            'rclimit' => self::LIMIT_PER_PAGE,
            'format' => 'php',
        ];

        $pages = 0;
        while (true) {
            $result = $this->api->getRequest(new SimpleRequest('query', $params));
            $rows = $result['query']['recentchanges'] ?? [];

            foreach ($rows as $row) {
                yield $this->toEvent($row);
            }

            $pages++;
            $continue = $result['continue']['rccontinue'] ?? null;
            if ($continue === null || $pages >= $this->maxPagesPerStream) {
                return;
            }
            $params['rccontinue'] = $continue;
        }
    }

    private function toEvent(array $row): RecentChangeEvent
    {
        $isBot = array_key_exists('bot', $row);
        $isAnon = array_key_exists('anon', $row);

        $sizeDiff = (isset($row['newlen'], $row['oldlen']))
            ? ((int)$row['newlen'] - (int)$row['oldlen'])
            : null;

        return new RecentChangeEvent(
            revid: (int)($row['revid'] ?? 0),
            oldRevid: isset($row['old_revid']) ? (int)$row['old_revid'] : null,
            page: (string)($row['title'] ?? ''),
            ns: (int)($row['ns'] ?? 0),
            user: (string)($row['user'] ?? ''),
            userKind: $isBot ? UserKind::Bot : ($isAnon ? UserKind::Ip : UserKind::Registered),
            timestamp: new DateTimeImmutable((string)($row['timestamp'] ?? 'now')),
            sizeDiff: $sizeDiff,
            comment: $row['comment'] ?? null,
            tags: $row['tags'] ?? [],
        );
    }
}
