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
}
