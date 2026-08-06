<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Domain\ExternLink\ExternLinkCheckVerdict;
use App\Infrastructure\ExternLinkCheckAdapter;
use DateInterval;
use PHPUnit\Framework\TestCase;
use Simplon\Mysql\Mysql;

class ExternLinkCheckAdapterTest extends TestCase
{
    public function testRecordFailureInsertsNewCheckAndLinksPage()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchRow')->willReturn(null); // no existing check row for this URL
        $db->method('fetchColumn')->willReturn(null); // page not yet linked to it
        $db->expects($this->never())->method('update');

        $db->expects($this->exactly(2))
            ->method('insert')
            ->willReturnCallback(function (string $table, array $data) {
                if ($table === 'extern_link_check') {
                    $this::assertSame('https://example.com/page', $data['url']);
                    $this::assertSame(md5('https://example.com/page'), $data['url_hash']);
                    $this::assertSame('example.com', $data['registrable_domain']);
                    $this::assertSame(500, $data['http_status']);
                    $this::assertSame('transient_error', $data['verdict']);
                    $this::assertSame(1, $data['attempt_count']);

                    return 42;
                }
                $this::assertSame('extern_link_check_page', $table);
                $this::assertSame(['check_id' => 42, 'page' => 'Some Page'], $data);

                return true;
            });

        (new ExternLinkCheckAdapter($db))->recordFailure(
            'Some Page',
            'https://example.com/page',
            'example.com',
            500,
            null,
            ExternLinkCheckVerdict::TransientError
        );
    }

    public function testRecordFailureOnExistingCheckIncrementsAttemptCountAndLinksNewPage()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchRow')->willReturn(['id' => '7', 'attempt_count' => '2']);
        $db->method('fetchColumn')->willReturn(null); // this particular page not yet linked

        $db->expects($this->once())
            ->method('update')
            ->with(
                'extern_link_check',
                ['id' => 7],
                $this->callback(fn(array $data) => $data['attempt_count'] === 3 && $data['http_status'] === 502)
            );
        $db->expects($this->once())
            ->method('insert')
            ->with('extern_link_check_page', ['check_id' => 7, 'page' => 'Another Page']);

        (new ExternLinkCheckAdapter($db))->recordFailure(
            'Another Page',
            'https://example.com/page',
            'example.com',
            502,
            null,
            ExternLinkCheckVerdict::TransientError
        );
    }

    public function testRecordFailureDoesNotDuplicateAnAlreadyLinkedPage()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchRow')->willReturn(['id' => '7', 'attempt_count' => '1']);
        $db->method('fetchColumn')->willReturn('1'); // page already linked
        $db->expects($this->never())->method('insert');

        (new ExternLinkCheckAdapter($db))->recordFailure(
            'Some Page',
            'https://example.com/page',
            'example.com',
            500,
            null,
            ExternLinkCheckVerdict::TransientError
        );
    }

    public function testRecordSuccessDeletesOnlyThisPageWhenOthersRemain()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchColumn')->willReturnOnConsecutiveCalls('7', '1'); // check id, then "still linked elsewhere"
        $db->expects($this->once())->method('delete')->with('extern_link_check_page', ['check_id' => 7, 'page' => 'Some Page']);

        (new ExternLinkCheckAdapter($db))->recordSuccess('Some Page', 'https://example.com/page');
    }

    public function testRecordSuccessDeletesTheCheckTooWhenNoPageRemains()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchColumn')->willReturnOnConsecutiveCalls('7', null); // check id, then "no more pages"

        $db->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (string $table, array $conds) {
                static $call = 0;
                $call++;
                if ($call === 1) {
                    $this::assertSame('extern_link_check_page', $table);
                    $this::assertSame(['check_id' => 7, 'page' => 'Some Page'], $conds);
                } else {
                    $this::assertSame('extern_link_check', $table);
                    $this::assertSame(['id' => 7], $conds);
                }

                return true;
            });

        (new ExternLinkCheckAdapter($db))->recordSuccess('Some Page', 'https://example.com/page');
    }

    public function testRecordSuccessIsNoOpWhenUrlIsUnknown()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchColumn')->willReturn(null);
        $db->expects($this->never())->method('delete');

        (new ExternLinkCheckAdapter($db))->recordSuccess('Some Page', 'https://example.com/never-failed');
    }

    public function testFindDueForRecheckReturnsPageUrlPairs()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchRowMany')->willReturn([
            ['url' => 'https://example.com/a', 'page' => 'Page A'],
            ['url' => 'https://example.com/b', 'page' => 'Page B'],
        ]);

        $rows = (new ExternLinkCheckAdapter($db))->findDueForRecheck(
            ExternLinkCheckVerdict::TransientError,
            new DateInterval('P2M')
        );

        $this::assertSame(
            [
                ['url' => 'https://example.com/a', 'page' => 'Page A'],
                ['url' => 'https://example.com/b', 'page' => 'Page B'],
            ],
            $rows
        );
    }

    public function testFindDueForRecheckReturnsEmptyArrayWhenNoRows()
    {
        $db = $this->createMock(Mysql::class);
        $db->method('fetchRowMany')->willReturn(null);

        $rows = (new ExternLinkCheckAdapter($db))->findDueForRecheck(
            ExternLinkCheckVerdict::TransientError,
            new DateInterval('P2M')
        );

        $this::assertSame([], $rows);
    }
}
