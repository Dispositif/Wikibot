<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exceptions\ConfigException;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\Logger;
use App\Infrastructure\ServiceFactory;
use App\Infrastructure\SMS;
use DateInterval;
use DateTimeImmutable;
use DomainException;
use Exception;
use Mediawiki\Api\UsageException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Define wiki configuration of the bot.
 * See also .env file for parameters.
 * Class WikiBotConfig.
 */
class WikiBotConfig
{
    const VERSION = '1.0';

    const WATCHPAGE_FILENAME = __DIR__.'/resources/watch_pages.json';

    const EXIT_ON_CHECK_WATCHPAGE = false;

    // do not stop if they play with {stop} on bot talk page
    const BLACKLIST_EDITOR = ['OrlodrimBot'];

    const BOT_FLAG = false;

    const MODE_AUTO = false;

    const EXIT_ON_WIKIMESSAGE = true;

    const EDIT_LAPS = 20;

    const EDIT_LAPS_MANUAL = 20;

    const EDIT_LAPS_AUTOBOT = 60;

    const EDIT_LAPS_FLAGBOT = 8;

    public $taskName = 'Améliorations indéfinie';

    /**
     * @var DateTimeImmutable
     */
    private $lastCheckStopDate;
    /**
     * @var LoggerInterface
     */
    public $log;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->log = ($logger) ? $logger : new Logger();
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
            str_replace('v', '', self::VERSION),
            $this->taskName
        );
    }

    /**
     * Throws Exception if "{{stop}}" or "STOP" on talk page.
     *
     * @param bool|null $botTalk
     *
     * @throws UsageException
     */
    public function checkStopOnTalkpage(?bool $botTalk = false): void
    {
        $title = 'Discussion_utilisateur:'.getenv('BOT_NAME');

        if ($this->lastCheckStopDate
            && new DateTimeImmutable() < $this->lastCheckStopDate->add(
                new DateInterval('PT2M')
            )
        ) {
            return;
        }
        $this->lastCheckStopDate = new DateTimeImmutable();

        // don't catch Exception (stop process if error)
        $wiki = ServiceFactory::wikiApi();
        $pageAction = new WikiPageAction($wiki, $title);
        $text = $pageAction->getText() ?? '';
        $lastEditor = $pageAction->getLastEditor() ?? 'unknown';

        if (preg_match('#({{stop}}|{{Stop}}|STOP)#', $text) > 0) {
            echo date('Y-m-d H:i:s');
            echo sprintf(
                "\n*** STOP ON TALK PAGE BY %s ***\n\n",
                $lastEditor
            );
            if (in_array($lastEditor, static::BLACKLIST_EDITOR)) {
                return;
            }

            if (class_exists(SMS::class)) {
                try {
                    new SMS('WikiBotConfig stop by '.$lastEditor);
                } catch (Exception $e) {
                    unset($e);
                }
            }
            if ($botTalk && class_exists(TalkBotConfig::class)) {
                try {
                    (new TalkBotConfig())->botTalk();
                } catch (Throwable $e) {
                    unset($e);
                }
            }

            throw new DomainException('STOP on talk page');
        }
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
            if (!$pageTime || $pageTime !== $lastTime) {
                echo sprintf(
                    "WATCHPAGE '%s' has been edited since %s.\n",
                    $title,
                    $lastTime
                );

                // Ask? Mettre à jour $watchPages ?
                echo "Replace with $title => '$pageTime'";

                if (static::EXIT_ON_CHECK_WATCHPAGE) {
                    echo "EXIT_ON_CHECK_WATCHPAGE\n";

                    throw new DomainException('exit from check watchpages');
                }
            }
        }
    }

    /**
     * @return array
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
        } catch (Throwable $e) {
            throw new ConfigException('Watchpage file malformed.');
        }

        return $array;
    }

    private function getTimestamp(string $title): ?string
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

        return (int)round((time() - strtotime($time)) / 60);
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
    private static function isNoBotTag(string $text, ?string $botName = null): bool
    {
        $text = WikiTextUtil::removeHTMLcomments($text);
        $botName = ($botName) ? $botName : getenv('BOT_NAME');
        $denyReg = (!empty($botName)) ? '|\{\{bots ?\| ?(optout|deny)\=[^\}]*'.preg_quote($botName, '#').'[^\}]*\}\}' :
            '';

        if (preg_match('#({{nobots}}|{{bots ?\| ?(optout|deny) ?= ?all ?}}'.$denyReg.')#i', $text) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Detect wiki-templates restricting the edition on a frwiki page.
     *
     * @param string      $text
     * @param string|null $botName
     *
     * @return bool
     */
    public static function isEditionRestricted(string $text, ?string $botName = null): bool
    {
        // travaux|en travaux| ??
        if (preg_match('#{{Protection#i', $text) > 0
            || preg_match('#\{\{(R3R|Règle des 3 révocations|travaux|en travaux|en cours|formation)#i', $text) > 0
            || self::isNoBotTag($text, $botName)
        ) {
            return true;
        }

        return false;
    }
}
