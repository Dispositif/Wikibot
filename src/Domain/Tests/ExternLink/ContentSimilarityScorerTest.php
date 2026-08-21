<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Domain\ExternLink\ContentSimilarityScorer;
use PHPUnit\Framework\TestCase;

class ContentSimilarityScorerTest extends TestCase
{
    private ContentSimilarityScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new ContentSimilarityScorer();
    }

    public function testSamePageWithBoilerplateDifferencesScoresHigh(): void
    {
        $article = str_repeat(
            'Cet article traite en détail du sujet annoncé par le titre, avec plusieurs paragraphes de contenu. ',
            20
        );
        $live = '<html><head><title>Un grand article de presse</title></head><body>' . $article . '</body></html>';
        $archived = '<html><head><title>Un grand article de presse</title></head><body>'
            . '<div class="cookie-banner">Accepter les cookies</div>'
            . $article
            . '<div class="ads">Publicité</div></body></html>';

        $score = $this->scorer->score($live, $archived);

        $this->assertGreaterThanOrEqual(85.0, $score);
    }

    public function testUnrelatedPagesScoreLow(): void
    {
        $live = '<html><head><title>Article sur la politique française</title></head><body>'
            . str_repeat('Contenu politique très détaillé sur les élections et les débats parlementaires. ', 20)
            . '</body></html>';
        $archived = '<html><head><title>Wikiwix Archives</title></head><body>'
            . 'You need to enable JavaScript to run this app.<div id="root"></div>'
            . '</body></html>';

        $score = $this->scorer->score($live, $archived);

        $this->assertLessThan(55.0, $score);
    }

    public function testSoft404WithSimilarTitleScoresLowOnLengthRatio(): void
    {
        $article = str_repeat(
            'Cet article traite en détail du sujet annoncé par le titre, avec plusieurs paragraphes de contenu. ',
            20
        );
        $live = '<html><head><title>Article indisponible</title></head><body>' . $article . '</body></html>';
        // same title, but the archive only has a short "page not found" style body
        $archived = '<html><head><title>Article indisponible</title></head><body>Page introuvable.</body></html>';

        $score = $this->scorer->score($live, $archived);

        $this->assertSame(0.0, $score, 'length-ratio floor should short-circuit to 0 regardless of title match');
    }
}
