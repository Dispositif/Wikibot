<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 (c) Philippe M. <dispositif@gmail.com>
 * For the full copyright and license information, please view the LICENSE file
 */

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\ServiceFactory;

/**
 * Define wiki configuration of the bot
 * Class Bot.
 */
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
        $filename = __DIR__.'/resources/watch_pages.json';
        if (!file_exists($filename)) {
            dump("no file $filename");

            return;
        }
        $json = file_get_contents($filename);
        $watchPages = json_decode($json, true);

        foreach ($watchPages as $title => $lastTime) {
            $pageTime = $this->getTimestamp($title);

            // the page has been edited since last check ?
            if ($pageTime !== $lastTime) {
                echo sprintf(
                    "WATCHPAGE '%s' has been edited since %s\n",
                    $title,
                    $lastTime
                );

                // Ask? Mettre à jour $watchPages ?
                echo "Replace with $title => '$pageTime'";

                if (self::EXIT_ON_CHECK_WATCHPAGE) {
                    echo "\nSTOP on checkWatchPages\n";
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
     * Detect {{nobots}}, {{bots|deny=all}}, {{bots|deny=MyBot,BobBot}}.
     *
     * @param string      $text
     * @param string|null $botName
     *
     * @return bool
     */
    public static function isNoBotTag(string $text, string $botName = 'ZiziBot'): bool
    {
        $denyReg = (!is_null($botName)) ? '|\{\{bots ?\| ?deny\=[^\}]*'.preg_quote($botName, '#').'[^\}]*\}\}' : '';

        if (preg_match('#({{nobots}}|{{bots ?\| ?(optout|deny) ?= ?all ?}}'.$denyReg.')#i', $text) > 0) {
            return true;
        }

        return false;
    }
}
