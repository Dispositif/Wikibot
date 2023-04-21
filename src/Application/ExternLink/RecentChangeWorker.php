<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\ExternLink;

use App\Application\WikiBotConfig;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * https://www.mediawiki.org/wiki/API:RecentChanges
 */
class RecentChangeWorker
{
    const USER_RC_LIMIT = 100;
    /**
     * @var MediawikiApi
     */
    private $api;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(MediawikiApi $api, ?LoggerInterface $logger = null)
    {
        $this->api = $api;
        $this->logger = $logger ?? new NullLogger();
    }

    public function trackUser(string $user): void
    {
        echo "**** TRACK " . $user . "*****\n";

        $titles = $this->getLastEditsbyUser($user);

        // filter titles already in edited.txt
        $edited = file(__DIR__.'/resources/article_externRef_edited.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $filtered = array_diff($titles, $edited);
        $list = new PageList( $filtered ); // TODO PageList factory in App ?
        echo ">" . $list->count() . " dans liste\n";

        $this->consumeList($list);
    }

    // https://www.mediawiki.org/wiki/API:RecentChanges
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
        $botConfig = new WikiBotConfig($this->logger);
        $botConfig->taskName = "🦊 Amélioration de références : URL ⇒ ";


        new ExternRefWorker($botConfig, $wiki, $list, null, new InternetDomainParser());
    }

    private function getLastEditsbyUser(string $user): array
    {
        $recentchanges = $this->requestLastEditsbyUser($user);
        $titles = [];
        foreach ($recentchanges as $rc) {
            $titles[] = $rc['title'];
        }

        return array_unique($titles);
    }
}