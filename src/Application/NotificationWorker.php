<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\ServiceFactory;
use Exception;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;

/**
 * todo internationalize (enwiki, etc)
 * Parsing last notifications to the bot (and set them read).
 * Doc API : https://www.mediawiki.org/wiki/Notifications/API
 * TYPES :
 * edit-user-talk
 * mention-summary (alerte)
 * mention (Discussion utilisateur:Codaxbot)
 * flowusertalk-post-reply (alerte,   "title": {
 * "full": "Discussion utilisateur:Ir\u00f8nie",
 * "namespace": "Discussion_utilisateur")
 * Class NotificationWorker
 */
class NotificationWorker
{
    const DEFAULT_WIKIS             = 'frwiki';
    const DIFF_URL                  = 'https://fr.wikipedia.org/w/index.php?diff=';
    const SUMMARY                   = '⚙ mise à jour notifications';
    const SKIP_BOTPAGES
                                    = [
            'Utilisateur:CodexBot',
            'Discussion utilisateur:CodexBot',
            'Utilisateur:ZiziBot',
            'Discussion utilisateur:ZiziBot',
        ];

    /**
     * @var MediawikiApi
     */
    protected $api;
    /**
     * @var string
     */
    protected $notifPage;
    /**
     * @var array
     */
    protected $option;

    /**
     * NotificationWorker constructor.
     *
     * @param MediawikiApi $api
     * @param string       $notifPage
     * @param array|null   $option
     */
    public function __construct(MediawikiApi $api, string $notifPage, ?array $option = null)
    {
        $this->api = $api;
        $this->notifPage = $notifPage;
        $this->option = $option ?? [];

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
                self::SKIP_BOTPAGES
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

            $this->processSpecialActions($notif);
        }

        if(empty($wikilog)) {
            echo "Nothing.";
            return;
        }

        dump($wikilog);

        echo "Stop the script if you want to cancel the log edition on Wikipedia ! Waiting 30 seconds...\n";
        sleep(30);
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
                    'notsections' => 'alert', // alert|message ?? (minimum:alert)
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
     * @throws Exception
     */
    private function editWikilog(array $wikilog): bool
    {
        if (empty($wikilog)) {
            return false;
        }
        $text = implode("\n", $wikilog)."\n";

        $wiki = ServiceFactory::wikiApi();
        $pageAction = new WikiPageAction($wiki, $this->notifPage);

        $success = $pageAction->addToTopOfThePage(
            $text,
            new EditInfo(self::SUMMARY, false, false)
        );

        //        dump($success);

        return $success;
    }

    /**
     * Put there the special action to execute with each notification.
     *
     * @param $notif
     */
    protected function processSpecialActions($notif)
    {
        // optional for children
    }

}
