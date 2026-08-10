<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\BotEditJournalAdapter;
use PHPUnit\Framework\TestCase;
use Simplon\Mysql\Mysql;

class BotEditJournalAdapterTest extends TestCase
{
    public function testWasAnalyzedReturnsTrueWhenRowExists()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchColumn')->willReturn('1');

        $this::assertTrue((new BotEditJournalAdapter($db))->wasAnalyzed('Some Page', 'extern-ref'));
    }

    public function testWasAnalyzedReturnsFalseWhenNoRow()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchColumn')->willReturn(null);

        $this::assertFalse((new BotEditJournalAdapter($db))->wasAnalyzed('Some Page', 'extern-ref'));
    }

    public function testRecordAnalyzedInsertsWithInsertIgnore()
    {
        $db = $this->createMock(Mysql::class);
        $db->expects($this->once())
            ->method('insert')
            ->with(
                'bot_page_analyzed',
                $this->callback(fn(array $data) => $data['page'] === 'Some Page' && $data['task'] === 'extern-ref'),
                true
            );

        (new BotEditJournalAdapter($db))->recordAnalyzed('Some Page', 'extern-ref');
    }

    public function testForgetAnalyzedDeletesByPageAndTask()
    {
        $db = $this->createMock(Mysql::class);
        $db->expects($this->once())
            ->method('delete')
            ->with('bot_page_analyzed', ['page' => 'Some Page', 'task' => 'extern-ref']);

        (new BotEditJournalAdapter($db))->forgetAnalyzed('Some Page', 'extern-ref');
    }

    public function testRecordEditInsertsIntoBotEditWithRevid()
    {
        $db = $this->createMock(Mysql::class);
        $db->expects($this->once())
            ->method('insert')
            ->with(
                'bot_edit',
                $this->callback(
                    fn(array $data) => $data['page'] === 'Some Page'
                        && $data['task'] === 'extern-ref'
                        && $data['revid'] === 12345
                )
            );

        (new BotEditJournalAdapter($db))->recordEdit('Some Page', 'extern-ref', 12345);
    }

    public function testRecordEditAllowsNullRevid()
    {
        $db = $this->createMock(Mysql::class);
        $db->expects($this->once())
            ->method('insert')
            ->with('bot_edit', $this->callback(fn(array $data) => $data['revid'] === null));

        (new BotEditJournalAdapter($db))->recordEdit('Some Page', 'extern-ref');
    }
}
