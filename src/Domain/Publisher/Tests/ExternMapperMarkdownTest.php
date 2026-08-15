<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\ExternLink\ExternPage;
use App\Domain\Publisher\ExternMapper;
use App\Domain\Utils\TextUtil;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\TagParser;
use PHPUnit\Framework\TestCase;

/**
 * Markdown reaching a wiki template parameter is literal text, not markup. Caught in prod
 * on 2026-08-15 : "Kata Shotokan" was published with
 * |site=[www.karate-tourny27.fr](https://www.karate-tourny27.fr), the value coming
 * straight from the crawled page's own og:site_name.
 */
class ExternMapperMarkdownTest extends TestCase
{
    /**
     * @dataProvider provideMarkdownLinks
     */
    public function testUnwrapMarkdownLinksKeepsTheLabel(string $input, string $expected)
    {
        $this::assertSame($expected, TextUtil::unwrapMarkdownLinks($input));
    }

    public static function provideMarkdownLinks(): array
    {
        return [
            'the prod case' => [
                '[www.karate-tourny27.fr](https://www.karate-tourny27.fr)',
                'www.karate-tourny27.fr',
            ],
            'inside a sentence' => ['Lire [notre dossier](https://x.fr/a) ici', 'Lire notre dossier ici'],
            'several links' => ['[A](https://a.fr) et [B](http://b.fr)', 'A et B'],
            'relative url' => ['[Accueil](/home)', 'Accueil'],
            'anchor' => ['[Haut](#top)', 'Haut'],
            'empty label' => ['[](https://x.fr)', ''],
        ];
    }

    /**
     * @dataProvider provideUntouched
     */
    public function testLeavesWikitextAndPlainTextAlone(string $input)
    {
        $this::assertSame($input, TextUtil::unwrapMarkdownLinks($input));
    }

    public static function provideUntouched(): array
    {
        return [
            // no "](" pair, so the pattern cannot fire on wikitext links
            'wikilink' => ['[[Radio-Canada]]'],
            'piped wikilink with parens' => ['[[Le Nouvelliste (Trois-Rivières)|Le Nouvelliste]]'],
            'wikilink then parenthesis' => ['[[Foo]] (bar)'],
            'bracketed wiki extern link' => ['[https://x.fr Libellé]'],
            'plain brackets' => ['Titre [sic] (1980)'],
            // a label followed by a non-URL parenthesis is ordinary prose
            'not a link' => ['[Note] (voir plus bas)'],
        ];
    }

    /**
     * The unwrapping has to happen for every field, not just |site= : the next site may
     * well put its Markdown in the title instead. Note the title fixture avoids a
     * "Titre - Nom du site" shape, which postProcess trims for unrelated SEO reasons.
     */
    public function testMapperUnwrapsMarkdownComingFromCrawledMetadata()
    {
        $html = <<<'HTML'
<html><head>
<meta property="og:site_name" content="[www.karate-tourny27.fr](https://www.karate-tourny27.fr)" />
<meta property="og:title" content="Le kata [Empi](https://www.karate-tourny27.fr/?module=KEMPI) expliqué" />
<meta property="og:url" content="http://www.karate-tourny27.fr/?module=KEMPI" />
</head><body></body></html>
HTML;

        $page = new ExternPage('http://www.karate-tourny27.fr/?module=KEMPI', $html, new TagParser(), null);
        $data = (new ExternMapper(new NullLogger()))->process($page->getData());

        $this::assertSame('www.karate-tourny27.fr', $data['site'] ?? null);
        $this::assertSame('Le kata Empi expliqué', $data['titre'] ?? null);
    }
}
