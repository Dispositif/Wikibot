<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\ServiceFactory;

class Bot
{
    const WORKER_ID = '[test]';

    const TASK_DESC = 'Correction bibliographique';

    const BOT_FLAG = false;

    const MODE_AUTO = false;

    const EXIT_ON_WIKIMESSAGE = true;

    const EDIT_LAPS = 20;

    const EDIT_LAPS_MANUAL = 20;

    const EDIT_LAPS_AUTOBOT = 60;

    const EDIT_LAPS_FLAGBOT = 8;

    const EXIT_ON_CHECK_WATCHPAGE = true;

    private $watchPages
        = [
            'Discussion utilisateur:ZiziBot' => '2019-10-06T20:15:42Z',
            'Discussion utilisateur:Irønie' => '2019-09-18T22:12:52Z',
        ];

    public function __construct()
    {
        ini_set('user_agent', getenv('USER_AGENT'));
    }

    /**
     * Is there a new message on the discussion page of the bot (or owner) ?
     * Stop on new message ?
     */
    public function checkWatchPages()
    {
        foreach ($this->watchPages as $title => $lastTime) {
            $pageTime = $this->getTimestamp($title);

            // the page has been edited since last check ?
            if ($pageTime !== $lastTime) {
                echo sprintf(
                    "!!! WATCHPAGE '%s' has been edited since %s\n",
                    $title,
                    $lastTime
                );

                // Ask? Mettre à jour $watchPages ?
                $this->watchPages[$title] = $pageTime;
                echo "Replace with $title => '$pageTime'";

                if (self::EXIT_ON_CHECK_WATCHPAGE) {
                    echo "\nEXIT_ON_CHECK_WATCHPAGE\n";
                    exit();
                }
            }
        }
    }

    private function getTimestamp(string $title): string
    {
        $wiki = ServiceFactory::wikiApi();
        $page = new WikiPageAction($wiki, $title);

        return $page->page->getRevisions()->getLatest()->getTimestamp();
    }

    /**
     * How many minutes since last edit ? Do not to disturb human editors !
     *
     * @param string $title
     *
     * @return int
     */
    public function minutesSinceLastEdit(string $title): int
    {
        $time = $this->getTimestamp($title);  // 2011-09-02T16:31:13Z

        return (int) round((time() - strtotime($time)) / 60);
    }

    /**
     * Legacy.
     * Detect {{nobots}}, {{bots|deny=all}}, {{bots|deny=MyBot,BobBot}}
     * OK frwiki — ? enwiki.
     *
     * @param string      $text
     * @param string|null $botName
     *
     * @return bool
     */
    public static function isNoBotTag(string $text, string $botName = 'ZiziBot'): bool
    {
        $denyReg = (!is_null($botName)) ? '|\{\{bots ?\| ?deny\=[^\}]*'.preg_quote($botName, '#').'[^\}]*\}\}' : '';

        if (preg_match('#(\{\{nobots\}\}|\{\{bots ?\| ?(optout|deny) ?= ?all ?\}\}'.$denyReg.')#i', $text) > 0) {
            return true;
        }

        return false;
    }
}
