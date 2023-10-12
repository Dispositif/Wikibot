<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Notification;

use App\Application\WikiPageAction;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\ServiceFactory;
use DateTime;
use Exception;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * TODO internationalize
 *
 * Parsing last notifications to the bot (and set them read).
 * Doc API : https://www.mediawiki.org/wiki/Notifications/API
 * TYPES :
 * edit-user-talk
 * mention-summary (alerte)
 * mention (Discussion utilisateur:Codaxbot)
 * flowusertalk-post-reply (alerte,   "title": {
 * "full": "Discussion utilisateur:Ir\u00f8nie",
 * "namespace": "Discussion_utilisateur")
 */
class NotificationWorker
{
    public const DEFAULT_WIKIS             = 'frwiki';
    public const DIFF_URL                  = 'https://fr.wikipedia.org/w/index.php?diff=';
    public const SUMMARY                   = '⚙ mise à jour notifications';
    public const SKIP_BOTPAGES
                                    = [
            'Utilisateur:CodexBot',
            'Discussion utilisateur:CodexBot',
            'Utilisateur:ZiziBot',
            'Discussion utilisateur:ZiziBot',
        ];
    public const PUBLISH_LOG_ON_WIKI = true;

    /**
     * @var array
     */
    protected $option;

    /**
     * NotificationWorker constructor.
     */
    public function __construct(
        protected readonly MediawikiApi $api,
        protected string $notifPage,
        ?array $option = null,
        protected LoggerInterface $logger = new NullLogger())
    {
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

            $date = new DateTime($notif['timestamp']['utciso8601']);

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

            if (!isset($notif['read'])) {
                $this->postNotifAsRead($notif['id']);
            }

            $this->processSpecialActions($notif);
        }

        if ($wikilog === []) {
            echo "Nothing.";
            return;
        }

        dump($wikilog);

        if (self::PUBLISH_LOG_ON_WIKI) {
            echo "Stop the script if you want to cancel the log edition on Wikipedia ! Waiting 30 seconds...\n";
            sleep(30);
            $this->editWikilog($wikilog);
        }
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
        } catch (Throwable) {
            return false;
        }

        return true;
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

    /**
     * Write wikilog of notifications on a dedicated page.
     *
     * @throws UsageException
     * @throws Exception
     */
    private function editWikilog(array $wikilog): bool
    {
        if ($wikilog === []) {
            return false;
        }
        $text = implode("\n", $wikilog)."\n";

        $wiki = ServiceFactory::getMediawikiFactory();
        $pageAction = new WikiPageAction($wiki, $this->notifPage);

        return $pageAction->addToTopOfThePage(
            $text,
            new EditInfo(self::SUMMARY, false, false)
        );
    }

}
