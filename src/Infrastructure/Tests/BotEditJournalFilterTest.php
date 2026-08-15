<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\BotEditJournalAdapter;
use App\Infrastructure\FileBotEditJournal;
use PHPUnit\Framework\TestCase;
use Simplon\Mysql\Mysql;

/**
 * filterNotAnalyzed() sieves a whole CirrusSearch draw before the worker loop, so the
 * count a CLI prints is the real workload. Both implementations must agree on the
 * contract : input order preserved, duplicates collapsed, analyzed titles gone.
 */
class BotEditJournalFilterTest extends TestCase
{
    private function adapterOverAnalyzed(array $analyzedPages, ?array &$capturedConds = null): BotEditJournalAdapter
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchRowMany')->willReturnCallback(
            function (string $query, array $conds) use ($analyzedPages, &$capturedConds): ?array {
                $capturedConds[] = $conds;
                $hits = array_values(array_intersect($analyzedPages, $conds['pages']));

                return $hits === [] ? null : array_map(static fn($p) => ['page' => $p], $hits);
            }
        );

        return new BotEditJournalAdapter($db);
    }

    public function testAdapterDropsAnalyzedKeepsOrderAndDedupes()
    {
        $journal = $this->adapterOverAnalyzed(['B', 'D']);

        $result = $journal->filterNotAnalyzed(['A', 'B', 'C', 'A', 'D', 'E'], 'extern-ref');

        $this::assertSame(['A', 'C', 'E'], $result);
    }

    public function testAdapterOnEmptyInputIssuesNoQuery()
    {
        $conds = [];
        $journal = $this->adapterOverAnalyzed(['B'], $conds);

        $this::assertSame([], $journal->filterNotAnalyzed([], 'extern-ref'));
        $this::assertSame([], $conds);
    }

    /**
     * MySQL expands IN(:pages) to one placeholder per value, so a 5000-title draw has to
     * be chunked rather than sent as a single statement.
     */
    public function testAdapterChunksLargeInputAndKeepsEverythingUnanalyzed()
    {
        $conds = [];
        $journal = $this->adapterOverAnalyzed([], $conds);
        $pages = array_map(static fn(int $i): string => 'Page ' . $i, range(1, 2500));

        $result = $journal->filterNotAnalyzed($pages, 'extern-ref');

        $this::assertSame($pages, $result);
        $this::assertCount(3, $conds); // 1000 + 1000 + 500
        $this::assertSame('extern-ref', $conds[0]['task']);
        $this::assertCount(1000, $conds[0]['pages']);
        $this::assertCount(500, $conds[2]['pages']);
    }

    /**
     * "0" is a real fr.wikipedia article title, and it is the reason the adapter uses
     * fetchRowMany() : fetchColumnMany() loops on `while ($v = fetchColumn())` and would
     * silently truncate the analyzed set at the first falsy value.
     */
    public function testAdapterHandlesFalsyTitles()
    {
        $journal = $this->adapterOverAnalyzed(['0']);

        $this::assertSame(['1'], $journal->filterNotAnalyzed(['0', '1'], 'extern-ref'));
    }

    public function testFileJournalAppliesTheSameContract()
    {
        $analyzedFile = tempnam(sys_get_temp_dir(), 'analyzed');
        file_put_contents($analyzedFile, "B\nD\n");
        $journal = new FileBotEditJournal($analyzedFile, $analyzedFile . '_editions');

        $result = $journal->filterNotAnalyzed(['A', 'B', 'C', 'A', 'D', 'E'], 'extern-ref');

        unlink($analyzedFile);
        $this::assertSame(['A', 'C', 'E'], $result);
    }
}
