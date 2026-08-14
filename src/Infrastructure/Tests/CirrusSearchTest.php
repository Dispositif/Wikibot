<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\CirrusSearch;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for stream() : a readonly $params (couldn't paginate — caught
 * live on matou, see git history) and a yield-from-per-page key collision (silently
 * dropped titles when consumed via iterator_to_array(), only ever caught by hand here)
 * both shipped with zero test coverage. Overrides httpRequest() to avoid a real HTTP call.
 */
class CirrusSearchTest extends TestCase
{
    private function withResponses(array $responses): CirrusSearch
    {
        return new class($responses) extends CirrusSearch {
            private int $call = 0;

            public function __construct(private readonly array $responses)
            {
                parent::__construct(['srsearch' => 'irrelevant for this test']);
            }

            protected function httpRequest(): array
            {
                return $this->responses[$this->call++] ?? [];
            }
        };
    }

    public function testStreamYieldsAllTitlesAcrossPagesInOrder()
    {
        $cirrusSearch = $this->withResponses([
            ['query' => ['search' => [['title' => 'A'], ['title' => 'B']]], 'continue' => ['sroffset' => 500]],
            ['query' => ['search' => [['title' => 'C']]], 'continue' => ['sroffset' => 1000]],
            ['query' => ['search' => [['title' => 'D']]]], // no "continue" : last page
        ]);

        $titles = iterator_to_array($cirrusSearch->stream(maxPages: 10));

        // iterator_to_array() with default preserve_keys=true is exactly what would have
        // silently dropped "A" and "C" under the yield-from-per-page bug (each page's
        // titles restarting at array key 0, later pages overwriting earlier ones).
        $this::assertSame(['A', 'B', 'C', 'D'], array_values($titles));
    }

    public function testStreamStopsAtMaxPages()
    {
        $cirrusSearch = $this->withResponses([
            ['query' => ['search' => [['title' => 'A']]], 'continue' => ['sroffset' => 500]],
            ['query' => ['search' => [['title' => 'B']]], 'continue' => ['sroffset' => 1000]],
            ['query' => ['search' => [['title' => 'C']]]],
        ]);

        $titles = iterator_to_array($cirrusSearch->stream(maxPages: 2));

        $this::assertSame(['A', 'B'], array_values($titles));
    }

    public function testStreamStopsWhenNoContinueOffset()
    {
        $cirrusSearch = $this->withResponses([
            ['query' => ['search' => [['title' => 'A']]]], // no "continue" : single page
        ]);

        $titles = iterator_to_array($cirrusSearch->stream(maxPages: 10));

        $this::assertSame(['A'], array_values($titles));
    }

    public function testStreamOnEmptyResultIsEmpty()
    {
        $cirrusSearch = $this->withResponses([[]]);

        $titles = iterator_to_array($cirrusSearch->stream(maxPages: 10));

        $this::assertSame([], $titles);
    }

    /**
     * The URL is what actually carries srsort/srlimit to the API : a param silently
     * dropped by the defaults merge would degrade the search without failing anything.
     */
    public function testGetURLCarriesCallerParamsOverDefaults()
    {
        $cirrusSearch = new class(
            [
                'srsearch' => 'insource:/foo/',
                'srlimit' => CirrusSearch::SRLIMIT_MAX,
                'srsort' => CirrusSearch::SRSORT_RANDOM,
            ]
        ) extends CirrusSearch {
            public function exposedURL(): string
            {
                return $this->getURL();
            }
        };

        $url = $cirrusSearch->exposedURL();

        $this::assertStringContainsString('srlimit=max', $url);
        $this::assertStringContainsString('srsort=random', $url);
        $this::assertStringContainsString('srsearch=insource%3A%2Ffoo%2F', $url);
        $this::assertStringContainsString('srprop=size%7Cwordcount%7Ctimestamp', $url); // default kept
    }

    public function testGetURLRejectsMissingSrsearch()
    {
        $cirrusSearch = new class([]) extends CirrusSearch {
            public function exposedURL(): string
            {
                return $this->getURL();
            }
        };

        $this::expectException(\InvalidArgumentException::class);
        $cirrusSearch->exposedURL();
    }

    /**
     * srsort=random reshuffles per request, so stream() over several pages returns
     * duplicates and gaps instead of more coverage — callers must be told.
     */
    public function testStreamWarnsWhenPaginatingRandomSort()
    {
        $cirrusSearch = new class extends CirrusSearch {
            public function __construct()
            {
                parent::__construct(['srsearch' => 'irrelevant', 'srsort' => self::SRSORT_RANDOM]);
            }

            protected function httpRequest(): array
            {
                return ['query' => ['search' => [['title' => 'A']]]];
            }
        };

        ob_start();
        iterator_to_array($cirrusSearch->stream(maxPages: 3));
        $output = (string)ob_get_clean();

        $this::assertStringContainsString('srsort=random is meaningless', $output);
    }

    /**
     * Overrides anonymousRequest() (not httpRequest(), which is the retry wrapper itself)
     * and shortens the backoff, so the pool-counter path is exercised in milliseconds.
     */
    private function withRawResponses(array $responses): CirrusSearch
    {
        return new class($responses) extends CirrusSearch {
            protected const RETRY_SLEEP_SECONDS = 0;

            private int $call = 0;

            public function __construct(private readonly array $responses)
            {
                parent::__construct(['srsearch' => 'insource:/foo/']);
            }

            protected function anonymousRequest(): array
            {
                return $this->responses[$this->call++] ?? [];
            }
        };
    }

    public function testRegexPoolSaturationIsRetriedThenSucceeds()
    {
        $cirrusSearch = $this->withRawResponses([
            ['error' => ['code' => 'cirrussearch-regex-too-busy-error', 'info' => 'Too many...']],
            ['query' => ['search' => [['title' => 'A']]]],
        ]);

        ob_start();
        $titles = $cirrusSearch->getPageTitles();
        $output = (string)ob_get_clean();

        $this::assertSame(['A'], $titles);
        $this::assertStringContainsString('CirrusSearch busy', $output);
    }

    public function testRegexPoolSaturationThrowsAfterMaxRetry()
    {
        $busy = ['error' => ['code' => 'cirrussearch-regex-too-busy-error', 'info' => 'Too many...']];
        $cirrusSearch = $this->withRawResponses([$busy, $busy, $busy, $busy]);

        $this::expectExceptionMessageMatches('/cirrussearch-regex-too-busy-error/');

        ob_start();
        try {
            $cirrusSearch->getPageTitles();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * A malformed query must fail loudly and immediately. Before the API-error check,
     * the anonymous path handed the error body to extractTitles(), which found no
     * 'query' key and returned [] — an empty worklist indistinguishable from "nothing
     * left to do", so the worker exited happily.
     */
    public function testNonTransientApiErrorThrowsWithoutRetry()
    {
        $cirrusSearch = $this->withRawResponses([
            ['error' => ['code' => 'cirrussearch-offset-too-large', 'info' => 'Up to 10000...']],
            ['query' => ['search' => [['title' => 'A']]]], // never reached
        ]);

        $this::expectExceptionMessageMatches('/cirrussearch-offset-too-large/');
        $cirrusSearch->getPageTitles();
    }
}
