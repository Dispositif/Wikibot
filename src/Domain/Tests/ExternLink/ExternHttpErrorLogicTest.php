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
use App\Domain\Models\Summary;
use PHPUnit\Framework\TestCase;

class ExternHttpErrorLogicTest extends TestCase
{
    private function fetch(?int $httpStatus, ?FetchErrorKind $errorKind = null): FetchResult
    {
        return new FetchResult('https://example.com/page', null, $httpStatus, null, 0, null, $errorKind);
    }

    public function testDeadLinkStatusesGoThroughDeadLinkTransformer()
    {
        foreach ([404, 410, 400, 500, 502, 451] as $status) {
            $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
            $deadLinkTransformer->expects($this->once())
                ->method('formatFromUrl')
                ->with('https://example.com/page', $this->anything(), $this->anything(), $status)
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
        foreach ([401, 402, 403] as $status) {
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
     * §9.5/§9.6 : 429/503 are not converted to a dead link on a single observation —
     * they're recorded (for a later recheck) and the URL stays unchanged. Unlike
     * 500/502, these signal a server-wide, likely-temporary condition rather than
     * the specific requested resource being gone.
     */
    public function testTransientErrorStatusesAreRecordedNotConvertedToDeadLink()
    {
        foreach ([429, 503] as $status) {
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
            $logic->manageByFetchResult($this->fetch(503), 'example.com')
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
     * a recheck in ExternLinkCheckRepository (the content isn't coming back), and goes
     * straight through DeadLinkTransformer like 400/500/502 rather than being left
     * untouched.
     */
    public function test451IsNotRecordedForRecheck()
    {
        $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
        $deadLinkTransformer->expects($this->once())
            ->method('formatFromUrl')
            ->with('https://example.com/page', $this->anything(), $this->anything(), 451)
            ->willReturn('{{Lien brisé |url= https://example.com/page}}');

        $repository = $this->createMock(ExternLinkCheckRepositoryInterface::class);
        $repository->expects($this->never())->method('recordFailure');

        $logic = new ExternHttpErrorLogic($deadLinkTransformer, linkCheckRepository: $repository);

        $this::assertStringContainsString(
            '{{Lien brisé',
            $logic->manageByFetchResult($this->fetch(451))
        );
    }

    /**
     * §9.8 : the caller's Summary must reach DeadLinkTransformer, so it can record
     * which archiver it used directly instead of ExternRefWorker re-deriving it later
     * by string-matching the result.
     */
    public function testSummaryIsForwardedToDeadLinkTransformer()
    {
        $summary = new Summary();
        $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
        $deadLinkTransformer->expects($this->once())
            ->method('formatFromUrl')
            ->with('https://example.com/page', $this->anything(), $this->identicalTo($summary), 404)
            ->willReturn('{{Lien brisé |url= https://example.com/page}}');

        $logic = new ExternHttpErrorLogic($deadLinkTransformer);
        $logic->manageByFetchResult($this->fetch(404), null, null, $summary);
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

    /**
     * A 3xx only reaches this class when the redirect chain could not be resolved —
     * observed in prod on 2026-08-15 with cagematch.net and areditions.com, both serving
     * a bare 307 with no Location header. The resource is most likely alive behind a
     * redirect the bot cannot follow, so turning it into a dead link would be wrong.
     *
     * @dataProvider provideUnresolvedRedirectStatuses
     */
    public function testUnresolvedRedirectIsNeverADeadLink(int $status)
    {
        $deadLinkTransformer = $this->createMock(DeadLinkTransformer::class);
        $deadLinkTransformer->expects($this->never())->method('formatFromUrl');

        $logic = new ExternHttpErrorLogic($deadLinkTransformer);

        $this::assertSame(
            'https://example.com/page',
            $logic->manageByFetchResult($this->fetch($status)),
            "status $status must not be turned into a dead link"
        );
    }

    public static function provideUnresolvedRedirectStatuses(): array
    {
        return [[300], [301], [302], [307], [308], [399]];
    }

    /** Recorded like 429/503, so findDueForRecheck() can offer it up again later. */
    public function testUnresolvedRedirectIsRecordedAsTransient()
    {
        $repository = $this->createMock(ExternLinkCheckRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('recordFailure')
            ->with(
                'Page citante',
                'https://example.com/page',
                'example.com',
                307,
                null,
                ExternLinkCheckVerdict::TransientError
            );

        $logic = new ExternHttpErrorLogic($this->createMock(DeadLinkTransformer::class), linkCheckRepository: $repository);
        $logic->manageByFetchResult($this->fetch(307), 'example.com', 'Page citante');
    }

    /** No citing page => nothing actionable to persist later, so no write at all. */
    public function testUnresolvedRedirectWithoutPageTitleRecordsNothing()
    {
        $repository = $this->createMock(ExternLinkCheckRepositoryInterface::class);
        $repository->expects($this->never())->method('recordFailure');

        $logic = new ExternHttpErrorLogic($this->createMock(DeadLinkTransformer::class), linkCheckRepository: $repository);
        $logic->manageByFetchResult($this->fetch(307));
    }
}
