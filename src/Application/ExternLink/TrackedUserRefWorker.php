<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\ExternLink;

use App\Application\WikiBotConfig;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Psr\Log\LoggerInterface;

/**
 * Fetches recent contributions of a known/trusted user (via list=recentchanges,
 * rcuser=...) and feeds the extern-ref pipeline with their edited pages — not a
 * general recent-changes scanner. Renamed from RecentChangeWorker (2026-08) to avoid
 * confusion with the future generic RC-filtering module (App\*\RecentChange).
 * refactored 2023-10 not tested
 */
class TrackedUserRefWorker
{
    protected const USER_RC_LIMIT = 100;
    protected const TASK_NAME = '🦊 Amélioration de références : URL ⇒ ';
    protected const ALREADY_EDITED_PATH = __DIR__ . '/../resources/article_externRef_edited.txt';

    public function __construct(
        private readonly MediawikiApi $api,
        private readonly LoggerInterface $logger = new NullLogger(),

        private readonly string $taskName = self::TASK_NAME,
        // 2nd pass without Tor when the Tor fetch looks blocked — on by default, see
        // audits/synthese-anti-bot-crawling-tor-2026-08.md
        private readonly bool $directRetryEnabled = true,
        private readonly bool $respectRobotsTxt = true,
    )
    {
    }

    public function trackUser(string $user): void
    {
        echo "**** TRACK " . $user . "*****\n";

        $titles = $this->getLastEditsbyUser($user);

        // filter titles already in edited.txt
        $edited = file(self::ALREADY_EDITED_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $filtered = array_diff($titles, $edited);
        $list = new PageList($filtered); // TODO PageList factory in App ?
        echo ">" . $list->count() . " dans liste\n";

        $this->consumeList($list);
    }

    // https://www.mediawiki.org/wiki/API:RecentChanges

    private function getLastEditsbyUser(string $user): array
    {
        $recentchanges = $this->requestLastEditsbyUser($user);
        $titles = [];
        foreach ($recentchanges as $rc) {
            $titles[] = $rc['title'];
        }

        return array_unique($titles);
    }

    private function requestLastEditsbyUser(string $user): array
    {
        $result = $this->api->getRequest(
            new SimpleRequest(
                'query', [
                    'list' => 'recentchanges',
                    'rcnamespace' => 0,
                    'rcprop' => 'title|timestamp|user|redirect',
                    'rcuser' => $user,
                    'rclimit' => self::USER_RC_LIMIT,
//                    'rcdir' => 'newer', // = older to newer
                    'rctype' => 'edit|new',
//                    'rcshow' => '!bot',
                    'format' => 'php',
                ]
            )
        );

        if (empty($result)) {
            return [];
        }

        return $result['query']['recentchanges'] ?? [];
    }

    private function consumeList(PageList $list): void
    {
        $wiki = ServiceFactory::getMediawikiFactory();
        $botConfig = new WikiBotConfig($wiki, $this->logger);
        $botConfig->setTaskName($this->taskName);

        // refactored not tested :
        // '--no-db' : this class has no CLI $argv of its own, and never wired the
        // ExternLinkCheckRepository before -- kept off to preserve that behavior.
        $transformer = ServiceFactory::getExternRefTransformer(
            $this->logger,
            ['--no-db'],
            true,
            $this->directRetryEnabled,
            $this->respectRobotsTxt
        );

        new ExternRefWorker($botConfig, $wiki, $list, $transformer);
    }
}
