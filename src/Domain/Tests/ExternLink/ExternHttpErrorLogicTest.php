<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Domain\ExternLink\DeadLinkTransformer;
use App\Domain\ExternLink\ExternHttpErrorLogic;
use App\Domain\ExternLink\FetchErrorKind;
use App\Domain\ExternLink\FetchResult;
use PHPUnit\Framework\TestCase;

class ExternHttpErrorLogicTest extends TestCase
{
    private function fetch(?int $httpStatus, ?FetchErrorKind $errorKind = null): FetchResult
    {
        return new FetchResult('https://example.com/page', null, $httpStatus, null, 0, null, $errorKind);
    }

    public function testDeadLinkStatusesGoThroughDeadLinkTransformer()
    {
        foreach ([404, 410, 500, 502] as $status) {
            $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
            $deadLinkTransformer->expects($this->once())
                ->method('formatFromUrl')
                ->with('https://example.com/page')
                ->willReturn('{{Lien brisé |url= https://example.com/page}}');

            $logic = new ExternHttpErrorLogic($deadLinkTransformer);

            $this::assertStringContainsString(
                '{{Lien brisé',
                $logic->manageByFetchResult($this->fetch($status)),
                "status $status should route through DeadLinkTransformer"
            );
        }
    }

    public function testAccessErrorsLeaveUrlUnchanged()
    {
        foreach ([400, 401, 403] as $status) {
            $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
            $deadLinkTransformer->expects($this->never())->method('formatFromUrl');

            $logic = new ExternHttpErrorLogic($deadLinkTransformer);

            $this::assertSame(
                'https://example.com/page',
                $logic->manageByFetchResult($this->fetch($status)),
                "status $status must not be turned into a dead link"
            );
        }
    }

    public function testLooseNetworkFailuresGoThroughDeadLinkTransformer()
    {
        foreach ([FetchErrorKind::EmptyReply, FetchErrorKind::DnsResolutionFailed, FetchErrorKind::ProxyFailure] as $kind) {
            $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
            $deadLinkTransformer->expects($this->once())
                ->method('formatFromUrl')
                ->willReturn('{{Lien brisé |url= https://example.com/page}}');

            $logic = new ExternHttpErrorLogic($deadLinkTransformer);

            $this::assertStringContainsString(
                '{{Lien brisé',
                $logic->manageByFetchResult($this->fetch(null, $kind))
            );
        }
    }

    public function testUnhandledFailuresLeaveUrlUnchanged()
    {
        foreach ([429, 503, 451] as $status) {
            $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
            $deadLinkTransformer->expects($this->never())->method('formatFromUrl');

            $logic = new ExternHttpErrorLogic($deadLinkTransformer);

            $this::assertSame(
                'https://example.com/page',
                $logic->manageByFetchResult($this->fetch($status)),
                "status $status is not yet a confirmed dead link : must stay unchanged"
            );
        }

        $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
        $deadLinkTransformer->expects($this->never())->method('formatFromUrl');
        $logic = new ExternHttpErrorLogic($deadLinkTransformer);
        $this::assertSame(
            'https://example.com/page',
            $logic->manageByFetchResult($this->fetch(null, FetchErrorKind::ConnectionTimeout))
        );
    }
}
