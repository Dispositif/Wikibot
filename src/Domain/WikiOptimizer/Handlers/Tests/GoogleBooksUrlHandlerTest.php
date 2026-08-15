<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers\Tests;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptiStatus;
use App\Domain\WikiOptimizer\Handlers\GoogleBooksUrlHandler;
use PHPUnit\Framework\TestCase;

class GoogleBooksUrlHandlerTest extends TestCase
{
    /**
     * @return array{0: OuvrageTemplate, 1: OptiStatus}
     */
    private function handle(string $wikitext): array
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrateFromText($wikitext);
        $optiStatus = new OptiStatus();
        (new GoogleBooksUrlHandler($ouvrage, $optiStatus))->handle();

        return [$ouvrage, $optiStatus];
    }

    /**
     * Upgrading http:// to https:// is a real improvement for the reader, so it is worth an
     * edit on its own — unlike the other URL normalizations, which ride along silently.
     */
    public function testHttpToHttpsIsNotCosmetic()
    {
        [$ouvrage, $optiStatus] = $this->handle(
            '{{Ouvrage |titre=bla |lire en ligne=http://books.google.com/books/reader?id=WH4rAAAAYAAJ}}'
        );

        $this::assertSame(
            'https://books.google.com/books?id=WH4rAAAAYAAJ',
            $ouvrage->getParam('lire en ligne')
        );
        $this::assertTrue($optiStatus->isNotCosmetic());
        $this::assertContains('https', $optiStatus->getSummary());
    }

    public function testHttpToHttpsOnNewFormatUrlIsNotCosmetic()
    {
        [$ouvrage, $optiStatus] = $this->handle(
            '{{Ouvrage |titre=bla |lire en ligne=http://www.google.fr/books/edition/Titre/mF3u6D8w--cC}}'
        );

        $this::assertSame(
            'https://www.google.fr/books/edition/Titre/mF3u6D8w--cC',
            $ouvrage->getParam('lire en ligne')
        );
        $this::assertTrue($optiStatus->isNotCosmetic());
    }

    /**
     * An https URL merely reformatted (host canonicalized, 'hl' dropped, 'gbpv' added) stays
     * cosmetic : it doesn't justify an edit by itself.
     */
    public function testHttpsUrlNormalizationStaysCosmetic()
    {
        [$ouvrage, $optiStatus] = $this->handle(
            '{{Ouvrage |titre=bla |lire en ligne=https://books.google.fr/books/edition/Titre/mF3u6D8w--cC?hl=fr&pg=PA5}}'
        );

        $this::assertSame(
            'https://www.google.fr/books/edition/Titre/mF3u6D8w--cC?pg=PA5&gbpv=1',
            $ouvrage->getParam('lire en ligne')
        );
        $this::assertFalse($optiStatus->isNotCosmetic());
    }

    /**
     * Tracking removal already had its own flag : keep both signals when they coincide.
     */
    public function testTrackingRemovalOnHttpUrlFlagsBoth()
    {
        [, $optiStatus] = $this->handle(
            '{{Ouvrage |titre=bla |lire en ligne=http://books.google.com/books?id=WH4rAAAAYAAJ&ots=aZ3hKg3uDr}}'
        );

        $this::assertTrue($optiStatus->isNotCosmetic());
        $this::assertContains('tracking', $optiStatus->getSummary());
        $this::assertContains('https', $optiStatus->getSummary());
    }

    public function testNonGoogleUrlUntouched()
    {
        [$ouvrage, $optiStatus] = $this->handle(
            '{{Ouvrage |titre=bla |lire en ligne=http://www.lemonde.fr/article}}'
        );

        $this::assertSame('http://www.lemonde.fr/article', $ouvrage->getParam('lire en ligne'));
        $this::assertFalse($optiStatus->isNotCosmetic());
    }
}
