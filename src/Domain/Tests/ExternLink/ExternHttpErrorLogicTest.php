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
use App\Domain\ExternLink\ExternLinkCheckVerdict;
use App\Domain\ExternLink\FetchErrorKind;
use App\Domain\ExternLink\FetchResult;
use App\Domain\InfrastructurePorts\ExternLinkCheckRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ExternHttpErrorLogicTest extends TestCase
{
    private function fetch(?int $httpStatus, ?FetchErrorKind $errorKind = null): FetchResult
    {
        return new FetchResult('https://example.com/page', null, $httpStatus, null, 0, null, $errorKind);
    }

    public function testDeadLinkStatusesGoThroughDeadLinkTransformer()
    {
        foreach ([404, 410] as $status) {
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

    /**
     * §9.5/§9.6 : 429/500/502/503 are no longer converted to a dead link on a single
     * observation — they're recorded (for a later recheck) and the URL stays unchanged.
     */
    public function testTransientErrorStatusesAreRecordedNotConvertedToDeadLink()
    {
        foreach ([429, 500, 502, 503] as $status) {
            $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
            $deadLinkTransformer->expects($this->never())->method('formatFromUrl');

            $repository = $this->createMock(ExternLinkCheckRepositoryInterface::class);
            $repository->expects($this->once())
                ->method('recordFailure')
                ->with('Some Page', 'https://example.com/page', 'example.com', $status, null, ExternLinkCheckVerdict::TransientError);

            $logic = new ExternHttpErrorLogic($deadLinkTransformer, linkCheckRepository: $repository);

            $this::assertSame(
                'https://example.com/page',
                $logic->manageByFetchResult($this->fetch($status), 'example.com', 'Some Page'),
                "status $status must not be turned into a dead link on a single observation"
            );
        }
    }

    /**
     * Regression test : without a page to go back to (e.g. the recursive check
     * DeadLinkTransformer runs on an archive URL), recording a failure would be
     * unactionable dead weight in the DB — must be skipped entirely, not stored with
     * an empty/placeholder page.
     */
    public function testTransientErrorWithoutPageTitleIsNotRecorded()
    {
        $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
        $repository = $this->createMock(ExternLinkCheckRepositoryInterface::class);
        $repository->expects($this->never())->method('recordFailure');

        $logic = new ExternHttpErrorLogic($deadLinkTransformer, linkCheckRepository: $repository);

        $this::assertSame(
            'https://example.com/page',
            $logic->manageByFetchResult($this->fetch(500), 'example.com')
        );
    }

    public function testLooseNetworkFailuresGoThroughDeadLinkTransformer()
    {
        foreach ([FetchErrorKind::EmptyReply, FetchErrorKind::DnsResolutionFailed] as $kind) {
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

    /**
     * Regression test for §9.5 : a SOCKS5/Tor tunnel failure is a failure of the bot's
     * own network path, not evidence the target site is dead — it must not produce a
     * {{lien brisé}} or an archive lookup on an otherwise perfectly live link.
     */
    public function testProxyFailureIsNotTreatedAsDeadLink()
    {
        $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
        $deadLinkTransformer->expects($this->never())->method('formatFromUrl');

        $logic = new ExternHttpErrorLogic($deadLinkTransformer);

        $this::assertSame(
            'https://example.com/page',
            $logic->manageByFetchResult($this->fetch(null, FetchErrorKind::ProxyFailure))
        );
    }

    /**
     * 451 is a legal takedown, not a transitory failure : it must not be recorded for
     * a recheck in ExternLinkCheckRepository (the content isn't coming back).
     */
    public function test451IsNotRecordedForRecheck()
    {
        $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
        $deadLinkTransformer->expects($this->never())->method('formatFromUrl');

        $repository = $this->createMock(ExternLinkCheckRepositoryInterface::class);
        $repository->expects($this->never())->method('recordFailure');

        $logic = new ExternHttpErrorLogic($deadLinkTransformer, linkCheckRepository: $repository);

        $this::assertSame(
            'https://example.com/page',
            $logic->manageByFetchResult($this->fetch(451))
        );
    }

    public function testUnhandledFailuresLeaveUrlUnchanged()
    {
        $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
        $deadLinkTransformer->expects($this->never())->method('formatFromUrl');
        $logic = new ExternHttpErrorLogic($deadLinkTransformer);
        $this::assertSame(
            'https://example.com/page',
            $logic->manageByFetchResult($this->fetch(null, FetchErrorKind::ConnectionTimeout))
        );
    }
}
