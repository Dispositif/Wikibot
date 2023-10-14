<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\ExternLink;

use App\Application\WikiBotConfig;
use App\Domain\ExternLink\ExternRefTransformer;
use App\Domain\Publisher\ExternMapper;
use App\Infrastructure\InternetArchiveAdapter;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use App\Infrastructure\WikiwixAdapter;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Psr\Log\LoggerInterface;

/**
 * refactored 2023-10 not tested
 * https://www.mediawiki.org/wiki/API:RecentChanges
 */
class RecentChangeWorker
{
    protected const USER_RC_LIMIT = 100;
    protected const TASK_NAME = '🦊 Amélioration de références : URL ⇒ ';
    protected const ALREADY_EDITED_PATH = __DIR__ . '/../resources/article_externRef_edited.txt';

    public function __construct(private readonly MediawikiApi $api, private readonly LoggerInterface $logger = new NullLogger())
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
        $botConfig->setTaskName(self::TASK_NAME);

        // refactored not tested :
        $httpClient = ServiceFactory::getHttpClient();
        $wikiwix = new WikiwixAdapter($httpClient, $this->logger);
        $internetArchive = new InternetArchiveAdapter($httpClient, $this->logger);

        $domainParser = new InternetDomainParser();
        $transformer = new ExternRefTransformer(
            new ExternMapper($this->logger),
            ServiceFactory::getHttpClient(true),
            $domainParser,
            $this->logger,
            [$wikiwix, $internetArchive]
        );

        new ExternRefWorker($botConfig, $wiki, $list, $transformer);
    }
}