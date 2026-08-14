<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Tests;

use App\Application\AbstractBotTaskWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\PageList;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The politeness throttle used to fire after every title, including the ones skipped as
 * already analyzed — which cost one local journal lookup and no wiki request at all. At
 * srlimit=max (5000 titles per run) that was hours of pure sleep per run, so the shortcut
 * is worth pinning down.
 *
 * Timing-based on purpose : sleep() is a global function, there is no seam to count calls,
 * and the property under test really is wall-clock.
 */
class AbstractBotTaskWorkerThrottleTest extends TestCase
{
    private string|false $previousBotName = false;

    protected function setUp(): void
    {
        // run() logs the bot name, which reads the environment
        $this->previousBotName = getenv('BOT_NAME');
        putenv('BOT_NAME=phpunit-bot');
    }

    protected function tearDown(): void
    {
        // restore, don't unset : WikiBotConfigTest relies on whatever the environment
        // already provided, and a bare putenv('BOT_NAME') wipes it for the whole run
        putenv($this->previousBotName === false ? 'BOT_NAME' : 'BOT_NAME=' . $this->previousBotName);
    }

    private function workerOverTitles(array $titles, bool $alreadyAnalyzed): AbstractBotTaskWorker
    {
        // hand-written stub, not createMock() : run() calls the static getBotName(),
        // which PHPUnit mock objects cannot serve
        $bot = new class extends WikiBotConfig {
            public function __construct()
            {
            }

            public function getLogger(): LoggerInterface
            {
                return new NullLogger();
            }

            public function getCurrentGitCommitHash(): ?string
            {
                return 'deadbeef';
            }
        };

        // bypasses the parent constructor, which both hits the DB (analyzed-titles journal)
        // and calls run() itself
        return new class($bot, new PageList($titles), $alreadyAnalyzed) extends AbstractBotTaskWorker {
            public int $processedCount = 0;

            public function __construct(
                WikiBotConfig $bot,
                PageList $pageList,
                private readonly bool $alreadyAnalyzed
            ) {
                $this->bot = $bot;
                $this->log = $bot->getLogger();
                $this->defaultTaskname = 'phpunit';
                $this->pageListGenerator = $pageList;
            }

            protected function checkAlreadyAnalyzed(string $title): bool
            {
                return $this->alreadyAnalyzed;
            }

            protected function getTextFromWikiAction(string $title): ?string
            {
                $this->processedCount++;

                return 'wikitext, stands in for the API response';
            }

            protected function canProcessTitleArticle(string $title, ?string $text): bool
            {
                return false; // stop right after the (faked) wiki fetch
            }

            protected function processWithDomainWorker(string $title, string $text): ?string
            {
                return $text;
            }

            public function runNow(): void
            {
                $this->run();
            }
        };
    }

    public function testSkippedTitlesAreNotThrottled()
    {
        $worker = $this->workerOverTitles(['A', 'B', 'C'], alreadyAnalyzed: true);

        $start = microtime(true);
        $worker->runNow();
        $elapsed = microtime(true) - $start;

        // 3 × THROTTLE_DELAY_AFTER_EACH_TITLE = 6s before the shortcut
        $this::assertLessThan(1.0, $elapsed);
        $this::assertSame(0, $worker->processedCount);
    }

    public function testTitlesReachingTheWikiAreStillThrottled()
    {
        $worker = $this->workerOverTitles(['A'], alreadyAnalyzed: false);

        $start = microtime(true);
        $worker->runNow();
        $elapsed = microtime(true) - $start;

        $this::assertGreaterThanOrEqual(
            AbstractBotTaskWorker::THROTTLE_DELAY_AFTER_EACH_TITLE,
            $elapsed
        );
        $this::assertSame(1, $worker->processedCount);
    }
}
