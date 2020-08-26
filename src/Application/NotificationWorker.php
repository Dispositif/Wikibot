<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\DbAdapter;
use App\Infrastructure\Logger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;

/**
 * Doc API : https://www.mediawiki.org/wiki/Notifications/API
 * TYPES :
 * edit-user-talk
 * mention-summary (alerte)
 * mention (Discussion utilisateur:Codaxbot)
 * flowusertalk-post-reply (alerte,   "title": {
 * "full": "Discussion utilisateur:Ir\u00f8nie",
 * "namespace": "Discussion_utilisateur")
 * Parsing last unread notifications to the bot.
 * Class NotificationWorker
 *
 * @package App\Application
 */
class NotificationWorker
{
    const DEFAULT_WIKIS             = 'frwiki';
    const DIFF_URL                  = 'https://fr.wikipedia.org/w/index.php?diff=';
    const ARTICLE_ANALYZED_FILENAME = __DIR__.'/resources/article_externRef_edited.txt';

    /**
     * @var MediawikiApi
     */
    private $api;
    /**
     * @var array
     */
    private $option;

    /**
     * NotificationWorker constructor.
     *
     * @throws UsageException
     */
    public function __construct(?array $option = null)
    {
        $this->api = ServiceFactory::getMediawikiApi();
        $this->option = ($option) ?? [];

        $this->process();
    }

    private function process()
    {
        $notifications = $this->requestNotifications();
        if (empty($notifications)) {
            return;
        }

        krsort($notifications);

        $wikilog = [];
        foreach ($notifications as $notif) {
            $title = $notif['title']['full'];

            // Skip bot pages
            if (in_array(
                $title,
                [
                    'Utilisateur:CodexBot',
                    'Discussion utilisateur:CodexBot',
                    'Utilisateur:ZiziBot',
                    'Discussion utilisateur:ZiziBot',
                ]
            )
            ) {
                continue;
            }

            $date = new \DateTime($notif['timestamp']['utciso8601']);

            if (isset($notif['title']) && in_array($notif['title']['namespace'], ['', 'Discussion'])) {
                $icon = '🌼 '; // Article + Discussion
            }

            $wikilog[] = sprintf(
                '* %s %s[[%s]] ([%s%s diff]) par %s',
                $date->format('d-m-Y H\hi'),
                $icon ?? '',
                $title,
                self::DIFF_URL,
                $notif['revid'] ?? '',
                $notif['agent']['name'] ?? '???'
            );
//            dump($notif);

            if (!isset($notif['read'])) {
                $this->postNotifAsRead($notif['id']);
            }

            if (isset($notif['title']) && in_array($notif['title']['namespace'], ['', 'Discussion'])) {
                // PROCESS ARTICLES
                $article = $notif['title']['text'];

                // wikiScan for {ouvrage} completion
                $this->processWikiscanForOuvrage($article);

                // URL => wiki-template completion
                $this->deleteEditedArticleFile($article);
                $this->processExternLinks($article);
            }
        }

        dump($wikilog);

        echo "sleep 20";
        sleep(20);
        $this->editWikilog($wikilog);
    }

    private function requestNotifications(): ?array
    {
        $result = $this->api->getRequest(
            new SimpleRequest(
                'query', [
                           'meta' => 'notifications',
                           'notwikis' => self::DEFAULT_WIKIS,
                           'notfilter' => '!read', // default: read|!read
                           'notlimit' => '30', // max 50
                           //                   'notunreadfirst' => '1', // comment for false
                           //                   'notgroupbysection' => '1',
                           'notsections' => 'alert', // alert|message
                           'format' => 'php',
                       ]
            )
        );

        if (empty($result)) {
            return [];
        }

        return $result['query']['notifications']['list'];
    }

    private function postNotifAsRead(int $id): bool
    {
        sleep(2);
        try {
            $this->api->postRequest(
                new SimpleRequest(
                    'echomarkread', [
                                      'list' => $id,
                                      'token' => $this->api->getToken(),
                                  ]
                )
            );
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Write wikilog of notifications on a dedicated page.
     *
     * @param array $wikilog
     *
     * @return bool
     * @throws UsageException
     */
    private function editWikilog(array $wikilog): bool
    {
        if (empty($wikilog)) {
            return false;
        }
        $text = implode("\n", $wikilog)."\n";

        $wiki = ServiceFactory::wikiApi();
        $pageAction = new WikiPageAction($wiki, 'Utilisateur:CodexBot/Notifications');

        $success = $pageAction->addToTopOfThePage(
            $text,
            new EditInfo('⚙ mise à jour notifications', false, false)
        );
//        dump($success);

        return $success;
    }

    /**
     * Delete article title in a log text file.
     *
     * @param $title
     */
    private function deleteEditedArticleFile(string $title): void
    {
        $text = file_get_contents(self::ARTICLE_ANALYZED_FILENAME);
        $newtext = str_replace($title."\n", '', $text);
        if (!empty($text) && $text !== $newtext) {
            @file_put_contents(self::ARTICLE_ANALYZED_FILENAME, $newtext);
        }
    }

    /**
     * Process external URL completion to wiki-template.
     *
     * @param string      $article
     * @param string|null $username
     */
    private function processExternLinks(string $article, ?string $username = '')
    {
        try {
            $wiki = ServiceFactory::wikiApi();
            $logger = new Logger();
            //$logger->debug = true;
            $botConfig = new WikiBotConfig($logger);
            $botConfig->taskName = sprintf(
                "🔔🌐 Complètement de références (@[[User:%s|%s]]) : URL ⇒ ",
                $username,
                $username
            );
            new ExternRefWorker($botConfig, $wiki, new PageList([$article]));
            sleep(10);
        } catch (\Throwable $e) {
            unset($e);
        }
    }

    /**
     * Process wikiSan for future {ouvrage} completion
     *
     * @param string $article
     */
    private function processWikiscanForOuvrage(string $article): void
    {
        try {
            $wiki = ServiceFactory::wikiApi();
            $list = new PageList([$article]);
            new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig(), $list, 15);
        } catch (\Throwable $e) {
            echo $e->getMessage();
        }
    }
}
