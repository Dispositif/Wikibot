<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\FileBotEditJournal;
use PHPUnit\Framework\TestCase;

class FileBotEditJournalTest extends TestCase
{
    private string $analyzedFile;
    private string $editionsFile;

    protected function setUp(): void
    {
        $this->analyzedFile = tempnam(sys_get_temp_dir(), 'analyzed_') . '.txt';
        $this->editionsFile = tempnam(sys_get_temp_dir(), 'editions_') . '.txt';
        @unlink($this->analyzedFile); // start from "file doesn't exist yet"
        @unlink($this->editionsFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->analyzedFile);
        @unlink($this->editionsFile);
    }

    private function journal(): FileBotEditJournal
    {
        return new FileBotEditJournal($this->analyzedFile, $this->editionsFile);
    }

    public function testWasAnalyzedFalseWhenFileDoesNotExistYet()
    {
        $this::assertFalse($this->journal()->wasAnalyzed('Some Page', 'extern-ref'));
    }

    public function testRecordAnalyzedThenWasAnalyzedIsTrueOnSameInstance()
    {
        $journal = $this->journal();
        $journal->recordAnalyzed('Some Page', 'extern-ref');

        $this::assertTrue($journal->wasAnalyzed('Some Page', 'extern-ref'));
    }

    public function testRecordAnalyzedPersistsAcrossInstances()
    {
        $this->journal()->recordAnalyzed('Some Page', 'extern-ref');

        $this::assertTrue($this->journal()->wasAnalyzed('Some Page', 'extern-ref'));
    }

    public function testRecordAnalyzedDoesNotDuplicateLineOnSecondCall()
    {
        $journal = $this->journal();
        $journal->recordAnalyzed('Some Page', 'extern-ref');
        $journal->recordAnalyzed('Some Page', 'extern-ref');

        $lines = file($this->analyzedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this::assertSame(['Some Page'], $lines);
    }

    public function testForgetAnalyzedRemovesOnlyThatTitle()
    {
        $journal = $this->journal();
        $journal->recordAnalyzed('Page A', 'extern-ref');
        $journal->recordAnalyzed('Page B', 'extern-ref');

        $journal->forgetAnalyzed('Page A', 'extern-ref');

        $this::assertFalse($journal->wasAnalyzed('Page A', 'extern-ref'));
        $this::assertTrue($journal->wasAnalyzed('Page B', 'extern-ref'));
    }

    public function testForgetAnalyzedIsNoOpWhenFileDoesNotExist()
    {
        // must not throw / create the file
        $this->journal()->forgetAnalyzed('Some Page', 'extern-ref');
        $this::assertFileDoesNotExist($this->analyzedFile);
    }

    public function testRecordEditAppendsLineToEditionsFile()
    {
        $this->journal()->recordEdit('Some Page', 'extern-ref', 999);

        $content = file_get_contents($this->editionsFile);
        $this::assertStringContainsString('extern-ref', $content);
        $this::assertStringContainsString('Some Page', $content);
    }

    public function testRecordEditDoesNotAffectAnalyzedState()
    {
        $journal = $this->journal();
        $journal->recordEdit('Some Page', 'extern-ref');

        $this::assertFalse($journal->wasAnalyzed('Some Page', 'extern-ref'));
    }
}
