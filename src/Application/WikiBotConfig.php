<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Application\InfrastructurePorts\SMSInterface;
use App\Domain\Exceptions\ConfigException;
use App\Domain\Exceptions\StopActionException;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\Monitor\NullLogger;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DomainException;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\UsageException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Define wiki configuration of the bot.
 * See also .env file for parameters.
 * Page/Edit/Summary status are defined in Application/.../PageWorkStatus or Domain/OptiStatus
 */
class WikiBotConfig
{
    public const VERSION = '2.1';
    public const WATCHPAGE_FILENAME = __DIR__ . '/resources/watch_pages.json';
    public const EXIT_ON_CHECK_WATCHPAGE = false;
    // do not stop if they play with {stop} on bot talk page
    public const BLACKLIST_EDITOR = ['OrlodrimBot'];
    // Use that timers config instead of worker config ?
    public const BOT_FLAG = false;
    public const MODE_AUTO = false;
    public const EXIT_ON_WIKIMESSAGE = true;
    public const EDIT_LAPS = 20;
    public const EDIT_LAPS_MANUAL = 20;
    public const EDIT_LAPS_AUTOBOT = 60;
    public const EDIT_LAPS_FLAGBOT = 8;
    public const TALK_STOP_CHECK_INTERVAL = 'PT2M';
    public const TALK_PAGE_PREFIX = 'Discussion_utilisateur:';
    public const SLEEP_BEFORE_STOP_TALKPAGE = 30;

    protected string $taskName = 'Amélioration';
    /**
     * @var LoggerInterface
     */
    protected $log;
    /**
     * @var DateTimeImmutable
     */
    protected $lastCheckStopDate;
    protected SMSInterface|null $SMSClient;
    protected $mediawikiFactory;
    protected ?string $gitCommitHash = null;

    public function __construct(MediawikiFactory $mediawikiFactory, ?LoggerInterface $logger = null, ?SMSInterface $SMSClient = null)
    {
        $this->log = $logger ?? new NullLogger();
        ini_set('user_agent', getenv('USER_AGENT'));
        $this->SMSClient = $SMSClient;
        $this->mediawikiFactory = $mediawikiFactory;
    }

    /**
     * Detect wiki-templates restricting the edition on a frwiki page.
     */
    public static function isEditionTemporaryRestrictedOnWiki(?string $text, ?string $botName = null): bool
    {
        return empty($text)
            || preg_match('#{{Formation#i', $text) > 0
            || preg_match('#{{En travaux#i', $text) > 0
            || preg_match('#{{En cours#i', $text) > 0
            || preg_match('#{{Protection#i', $text) > 0
            || preg_match('#\{\{(R3R|Règle des 3 révocations|travaux|en travaux|en cours|formation)#i', $text) > 0
            || self::isNoBotTag($text, $botName);
    }

    /**
     * Detect {{nobots}}, {{bots|deny=all}}, {{bots|deny=MyBot,BobBot}}.
     * Relevant out of the "main" wiki-namespace (talk pages, etc).
     */
    protected static function isNoBotTag(string $text, ?string $botName = null): bool
    {
        $text = WikiTextUtil::removeHTMLcomments($text);
        $botName = $botName ?: self::getBotName();
        $denyReg = (empty($botName)) ? '' :
            '|\{\{bots ?\| ?(optout|deny)\=[^\}]*' . preg_quote($botName, '#') . '[^\}]*\}\}';
        return preg_match('#({{nobots}}|{{bots ?\| ?(optout|deny) ?= ?all ?}}' . $denyReg . ')#i', $text) > 0;
    }

    /**
     * @throws ConfigException
     */
    public static function getBotName(): string
    {
        if (empty(getenv('BOT_NAME'))) {
            throw new ConfigException('BOT_NAME is not defined.');
        }
        return getenv('BOT_NAME') ?? '';
    }

    protected static function getBotOwner()
    {
        return getenv('BOT_OWNER');
    }

    public function getTaskName(): string
    {
        return $this->taskName;
    }

    public function setTaskName(string $taskName): WikiBotConfig
    {
        $this->taskName = $taskName;
        return $this;
    }

    public function getCurrentGitCommitHash(): ?string
    {
        if ($this->gitCommitHash) {
            return $this->gitCommitHash;
        }
        $path = __DIR__ . '/../../.git/';
        if (!file_exists($path)) {
            return null;
        }
        $head = trim(substr(file_get_contents($path . 'HEAD'), 4));
        $hash = trim(file_get_contents(sprintf($path . $head)));
        $this->gitCommitHash = $hash; // cached

        return $hash;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->log;
    }

    /**
     * Throws Exception if "{{stop}}" or "STOP" on talk page.
     * @throws StopActionException
     */
    public function checkStopOnTalkpageOrException(?bool $botTalk = false): void
    {
        if ($this->isLastCheckStopDateRecent()) {
            return;
        }

        // don't catch Exception (stop process if error)
        $pageAction = $this->getWikiBotPageAction();
        $text = $pageAction->getText() ?? '';
        $lastEditor = $pageAction->getLastEditor() ?? 'unknown';

        if (preg_match('#({{stop}}|{{Stop}}|STOP)#', $text) > 0) {
            echo date('Y-m-d H:i');
            echo sprintf("\n*** STOP ON TALK PAGE BY %s ***\n\n", $lastEditor);
            sleep(self::SLEEP_BEFORE_STOP_TALKPAGE);

            $this->sendSMSandFunnyTalk($lastEditor, $botTalk);

            throw new StopActionException();
        }

        $this->lastCheckStopDate = new DateTimeImmutable();
    }

    protected function isLastCheckStopDateRecent(): bool
    {
        $now = new DateTimeImmutable();
        $stopInterval = new DateInterval(self::TALK_STOP_CHECK_INTERVAL);

        return $this->lastCheckStopDate instanceof DateTimeImmutable
            && $now < DateTime::createFromImmutable($this->lastCheckStopDate)->add($stopInterval);
    }

    /**
     * @throws UsageException
     */
    protected function getWikiBotPageAction(): WikiPageAction
    {
        return new WikiPageAction($this->mediawikiFactory, $this->getBotTalkPageTitle());
    }

    protected function getBotTalkPageTitle(): string
    {
        return self::TALK_PAGE_PREFIX . $this::getBotName();
    }

    protected function sendSMSandFunnyTalk(string $lastEditor, ?bool $botTalk): void
    {
        $this->sendSMS($lastEditor);

        if ($botTalk) {
            $this->talkWithBot();
        }
    }

    protected function sendSMS(string $lastEditor): bool
    {
        if ($this->SMSClient instanceof SMSInterface) {
            try {
                return $this->SMSClient->send(sprintf('%s {stop} by %s', $this::getBotName(), $lastEditor));
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function talkWithBot(): bool
    {
        if ($this instanceof TalkBotConfig) {
            try {
                return $this->botTalk();
            } catch (Throwable) {
                // do nothing
            }
        }

        return false;
    }

    /**
     * Is there a new message on the discussion page of the bot (or owner) ?
     * @throws ConfigException
     */
    public function checkWatchPages()
    {
        foreach ($this->getWatchPages() as $title => $lastTime) {
            $pageTime = $this->getTimestamp($title);

            // the page has been edited since last check ?
            if (!$pageTime || $pageTime !== $lastTime) {
                echo sprintf("WATCHPAGE '%s' has been edited since %s.\n", $title, $lastTime);

                // Ask? Mettre à jour $watchPages ?
                echo "Replace with $title => '$pageTime'";

                $this->checkExitOnWatchPage();
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
        } catch (Throwable) {
            throw new ConfigException('Watchpage file malformed.');
        }

        return $array;
    }

    protected function getTimestamp(string $title): ?string
    {
        $page = new WikiPageAction($this->mediawikiFactory, $title);

        return $page->page->getRevisions()->getLatest()->getTimestamp();
    }

    protected function checkExitOnWatchPage(): void
    {
        if (static::EXIT_ON_CHECK_WATCHPAGE) {
            echo "EXIT_ON_CHECK_WATCHPAGE\n";

            throw new DomainException('exit from check watchpages');
        }
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
}
