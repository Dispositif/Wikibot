<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exceptions\ConfigException;
use App\Domain\Exceptions\StopActionException;
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
    public const VERSION = '1.0';

    public const WATCHPAGE_FILENAME = __DIR__ . '/resources/watch_pages.json';

    public const EXIT_ON_CHECK_WATCHPAGE = false;

    // do not stop if they play with {stop} on bot talk page
    public const BLACKLIST_EDITOR = ['OrlodrimBot'];

    public const BOT_FLAG = false;

    public const MODE_AUTO = false;

    public const EXIT_ON_WIKIMESSAGE = true;

    public const EDIT_LAPS = 20;

    public const EDIT_LAPS_MANUAL = 20;

    public const EDIT_LAPS_AUTOBOT = 60;

    public const EDIT_LAPS_FLAGBOT = 8;

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
        $this->log = $logger ?: new Logger();
        ini_set('user_agent', getenv('USER_AGENT'));
    }

    /**
     * Throws Exception if "{{stop}}" or "STOP" on talk page.
     *
     * @throws UsageException
     */
    public function checkStopOnTalkpage(?bool $botTalk = false): void
    {
        $title = 'Discussion_utilisateur:' . $this::getBotName();

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
                    new SMS('WikiBotConfig stop by ' . $lastEditor);
                } catch (Exception $smsException) {
                    unset($smsException);
                }
            }
            if ($botTalk && class_exists(TalkBotConfig::class)) {
                try {
                    (new TalkBotConfig())->botTalk();
                } catch (Throwable $botTalkException) {
                    unset($botTalkException);
                }
            }

            throw new StopActionException();
        }
    }

    /**
     * Is there a new message on the discussion page of the bot (or owner) ?
     * Stop on new message ?
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
     * @throws ConfigException
     */
    protected function getWatchPages(): array
    {
        if (!file_exists(static::WATCHPAGE_FILENAME)) {
            throw new ConfigException('No watchpage file found.');
        }

        try {
            $json = file_get_contents(static::WATCHPAGE_FILENAME);
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
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
     */
    private static function isNoBotTag(string $text, ?string $botName = null): bool
    {
        $text = WikiTextUtil::removeHTMLcomments($text);
        $botName = $botName ?: self::getBotName();
        $denyReg = (empty($botName)) ? '' :
            '|\{\{bots ?\| ?(optout|deny)\=[^\}]*' . preg_quote($botName, '#') . '[^\}]*\}\}';
        return preg_match('#({{nobots}}|{{bots ?\| ?(optout|deny) ?= ?all ?}}' . $denyReg . ')#i', $text) > 0;
    }

    /**
     * Detect wiki-templates restricting the edition on a frwiki page.
     */
    public static function isEditionRestricted(string $text, ?string $botName = null): bool
    {
        // travaux|en travaux| ??
        return preg_match('#{{Protection#i', $text) > 0
            || preg_match('#\{\{(R3R|Règle des 3 révocations|travaux|en travaux|en cours|formation)#i', $text) > 0
            || self::isNoBotTag($text, $botName);
    }

    protected static function getBotName(): string
    {
        return getenv('BOT_NAME') ?? '';
    }

    protected static function getBotOwner()
    {
        return getenv('BOT_OWNER');
    }
}
