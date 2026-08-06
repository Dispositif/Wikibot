<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Domain\ExternLink\FetchResult;
use App\Domain\ExternLink\LinkVerdict;
use App\Domain\ExternLink\SoftFailureDetector;
use PHPUnit\Framework\TestCase;

class SoftFailureDetectorTest extends TestCase
{
    private function successFetch(string $url, string $body, ?string $finalUrl = null): FetchResult
    {
        return new FetchResult($url, $finalUrl, 200, 'text/html; charset=utf-8', strlen($body), $body);
    }

    public function testAcceptsNormalPage()
    {
        $fetch = $this->successFetch(
            'https://example.com/article-2018',
            str_repeat('<p>Contenu normal de la page.</p>', 100)
        );
        $detector = new SoftFailureDetector($fetch, 'Titre normal de l\'article');

        $this::assertSame(LinkVerdict::Accept, $detector->check());
    }

    public function testDetectsParkedDomain()
    {
        $fetch = $this->successFetch('https://freedomunderground.org/oldskin/view.php', '<html>parking</html>');
        $detector = new SoftFailureDetector($fetch, 'freedomunderground.org is for sale | HugeDomains');

        $this::assertSame(LinkVerdict::TreatAsDead, $detector->check());
    }

    public function testDetectsSoft404Title()
    {
        $fetch = $this->successFetch('https://example.com/deep/path', str_repeat('x', 2000));
        $detector = new SoftFailureDetector($fetch, 'Page introuvable - 404');

        $this::assertSame(LinkVerdict::TreatAsDead, $detector->check());
    }

    public function testWeakSignalsAloneAreNotEnough()
    {
        // redirected to root only : single weak signal, must not trigger alone
        $fetch = $this->successFetch(
            'https://example.com/deep/path/article',
            str_repeat('<p>contenu</p>', 200),
            'https://example.com/'
        );
        $detector = new SoftFailureDetector($fetch, 'Accueil - Example');

        $this::assertSame(LinkVerdict::Accept, $detector->check());
    }

    public function testCombinedWeakSignalsTrigger()
    {
        // redirected to root AND abnormally short body : two weak signals combined
        $fetch = $this->successFetch(
            'https://example.com/deep/path/article',
            '<html><body>short</body></html>',
            'https://example.com/'
        );
        $detector = new SoftFailureDetector($fetch, 'Accueil - Example');

        $this::assertSame(LinkVerdict::TreatAsDead, $detector->check());
    }

    public function testTitleEqualsBareDomain()
    {
        // body kept long enough to avoid also triggering the short-body signal,
        // isolating title==domain as the single signal under test
        $fetch = $this->successFetch('https://www.example.com/article', str_repeat('x', 2000));
        $detector = new SoftFailureDetector($fetch, 'example.com');

        // single weak signal (title==domain) : not enough alone
        $this::assertSame(LinkVerdict::Accept, $detector->check());
    }

    public function testSkipsCheckWhenFetchWasNotSuccessful()
    {
        $fetch = new FetchResult('https://example.com/', null, 404, null, 0, null);
        $detector = new SoftFailureDetector($fetch, 'buy this domain');

        // not this gate's job : ExternHttpErrorLogic already handles non-2xx results
        $this::assertSame(LinkVerdict::Accept, $detector->check());
    }
}
