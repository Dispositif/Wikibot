<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exceptions\ConfigException;
use App\Infrastructure\ServiceFactory;
use Bluora\LaravelGitInfo\GitInfo;

/**
 * Define wiki configuration of the bot.
 * See also .env file for parameters.
 * Class Bot.
 */
class Bot
{
    const WIKI_STATE_FILENAME = __DIR__.'/resources/wiki_state.json';
    const WATCHPAGE_FILENAME = __DIR__.'/resources/watch_pages.json';

    const EXIT_ON_CHECK_WATCHPAGE = true;

    const BOT_FLAG = false;

    const MODE_AUTO = false;

    const EXIT_ON_WIKIMESSAGE = true;

    const EDIT_LAPS = 20;

    const EDIT_LAPS_MANUAL = 20;

    const EDIT_LAPS_AUTOBOT = 60;

    const EDIT_LAPS_FLAGBOT = 8;

    public $taskName = 'Améliorations bibliographiques';

    public function __construct()
    {
        ini_set('user_agent', getenv('USER_AGENT'));
    }

    /**
     * Return start of wiki edit commentary.
     *
     * @return string
     */
    public function getCommentary(): string
    {
        return sprintf(
            '[%s] %s',
            str_replace('v', '', self::getGitVersion()),
            $this->taskName
        );
    }

    /**
     * Return last version (tag) from Git.
     *
     * @return string|null
     */
    public static function getGitVersion(): ?string
    {
        $git = new GitInfo();
        $raw = $git->version();
        if (preg_match('#^(v[0-9.a-e]+)#', $raw, $matches) > 0) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Return start of last commit id.
     *
     * @return string|null
     */
    public static function getCommitId(): ?string
    {
        $git = new GitInfo();
        $raw = $git->version();
        if (preg_match('#g([0-9a-f]+)#', $raw, $matches) > 0) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Is there a new message on the discussion page of the bot (or owner) ?
     * Stop on new message ?
     *
     * @throws ConfigException
     */
    public function checkWatchPages()
    {
        foreach ($this->getWatchPages() as $title => $lastTime) {
            $pageTime = $this->getTimestamp($title);

            // the page has been edited since last check ?
            if ($pageTime !== $lastTime) {
                echo sprintf(
                    "WATCHPAGE '%s' has been edited since %s.\n",
                    $title,
                    $lastTime
                );

                // Ask? Mettre à jour $watchPages ?
                echo "Replace with $title => '$pageTime'";

                if (self::EXIT_ON_CHECK_WATCHPAGE) {

                    if( class_exists(ZiziBot::class)) {
                        (new ZiziBot())->botTalk();
                    }

                    echo "\nSTOP on checkWatchPages.\n";
                    exit();
                }
            }
        }
    }

    /**
     * @return array
     *
     * @throws ConfigException
     */
    protected function getWatchPages(): array
    {
        if (!file_exists(static::WATCHPAGE_FILENAME)) {
            throw new ConfigException('No watchpage file found.');
        }

        try {
            $json = file_get_contents(static::WATCHPAGE_FILENAME);
            $array = json_decode($json, true);
        } catch (\Throwable $e) {
            throw new ConfigException('Watchpage file malformed.');
        }

        return $array;
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
     * @return int minutes
     */
    public function minutesSinceLastEdit(string $title): int
    {
        $time = $this->getTimestamp($title);  // 2011-09-02T16:31:13Z

        return (int) round((time() - strtotime($time)) / 60);
    }

    /**
     * Detect {{nobots}}, {{bots|deny=all}}, {{bots|deny=MyBot,BobBot}}.
     * Relevant out of the "main" wiki-namespace (talk pages, etc).
     *
     * @param string      $text
     * @param string|null $botName
     *
     * @return bool
     */
    public static function isNoBotTag(string $text, ?string $botName = null): bool
    {
        $botName = ($botName) ? $botName : getenv('BOT_NAME');
        $denyReg = (!is_null($botName)) ? '|\{\{bots ?\| ?deny\=[^\}]*'.preg_quote($botName, '#').'[^\}]*\}\}' : '';

        if (preg_match('#({{nobots}}|{{bots ?\| ?(optout|deny) ?= ?all ?}}'.$denyReg.')#i', $text) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Detect wiki-templates restricting the edition on a frwiki page.
     *
     * @param string $text
     *
     * @return bool
     */
    public static function isEditionRestricted(string $text): bool
    {
        if (preg_match('#{{Protection#i', $text) > 0) {
            return true;
        }
    }
}
