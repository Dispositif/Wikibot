<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\Publisher\GoogleBooksUtil;
use DomainException;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * URL parsing/cleaning, extracted from GoogleLivresTemplateTest : those cases exercise
 * GoogleBooksUtil, not the {{Google Livres}} template.
 *
 * Two URL shapes coexist in the wild, and the fixtures below are split accordingly :
 *
 *  - CLASSIC format : the volume is a query parameter, on a "books."/"play." host.
 *      https://books.google.fr/books?id=<id>&pg=PA56
 *    Output stays classic : the modern shape needs a title slug, which "?id=" links don't carry.
 *
 *  - NEW format (nov 2019) : the volume ID is the last path segment, behind a title slug,
 *    on a "www.google.<tld>" host.
 *      https://www.google.fr/books/edition/<slug>/<id>?pg=PT23&gbpv=1
 *    Output is normalized to that canonical host, slug and view params preserved.
 */
class GoogleBooksUtilTest extends TestCase
{
    /**
     * @dataProvider provideSimplify
     *
     * @throws Exception
     */
    public function testSimplifyGoogleUrl(string $url, string $expected)
    {
        $this::assertSame(
            $expected,
            GoogleBooksUtil::simplifyGoogleUrl($url)
        );
    }

    /**
     * Classic format only (volume in the query string) — see provideSimplifyNewFormat()
     * for the nov. 2019 shape.
     */
    public static function provideSimplify(): array
    {
        return [
            [
                // classic, bare : nothing to clean. Before fix nov 2023
                'https://books.google.com.br/books?id=tERyAAAAMAAJ',
                'https://books.google.com.br/books?id=tERyAAAAMAAJ',
            ],
            [
                // no 'id' but 'isbn' parameter
                'https://books.google.fr/books?isbn=0403099501&ots=aZ3hKg3uDr',
                'https://books.google.fr/books?isbn=0403099501',
            ],
            [
                // empty 'id' next to a valid 'isbn' : the ISBN wins, no "malformed id" exception
                'https://books.google.fr/books?id=&isbn=0403099501',
                'https://books.google.fr/books?isbn=0403099501',
            ],
            [
                // 'id' not first parameter
                'https://books.google.com/books?ots=aZ3hKg3uDr&id=QHrQoDLNBUIC&pg=PT19&lpg=PT19&sig=Y_zdZhNP-qNZE6WIDNivPPm-Urg&hl=en&sa=X&oi=book_result&resnum=8&ct=result',
                'https://books.google.com/books?id=QHrQoDLNBUIC&pg=PT19',
            ],
            [
                // OK : dq=full, q=null
                // https://fr.wikipedia.org/w/index.php?title=Famille_de_Pontev%C3%A8s&diff=prev&oldid=168342034&diffmode=source
                'https://books.google.fr/books?id=LkQoAAAAYAAJ&pg=PA341&dq=%22La+descendance+des+d\'Agoult+doit+%C3%AAtre+rejet%C3%A9e+comme+insuffisamment+%C3%A9tablie+;+celle+des+anciens+Pontev%C3%A8s+n\'est+que+vraisemblable,+mais+non+prouv%C3%A9e%22&hl=fr&sa=X&ved=0ahUKEwjVxaqCiv7ZAhXIt1kKHb1kD88Q6AEIJzAA#v=onepage&q=%22La%20descendance%20des%20d\'Agoult%20doit%20%C3%AAtre%20rejet%C3%A9e%20comme%20insuffisamment%20%C3%A9tablie%20%3B%20celle%20des%20anciens%20Pontev%C3%A8s%20n\'est%20que%20vraisemblable%2C%20mais%20non%20prouv%C3%A9e%22&f=false',
                'https://books.google.fr/books?id=LkQoAAAAYAAJ&pg=PA341&dq=%22La+descendance+des+d%27Agoult+doit+%C3%AAtre+rejet%C3%A9e+comme+insuffisamment+%C3%A9tablie+%3B+celle+des+anciens+Pontev%C3%A8s+n%27est+que+vraisemblable%2C+mais+non+prouv%C3%A9e%22',
            ],
            [
                // q= empty , dq= not empty => delete q and dq
                'https://books.google.fr/books?id=cAUvWtW7x7kC&printsec=frontcover&dq=Joanne+environs+Paris&hl=fr&ei=0Fl6TeqeIsek8QOnpeioBA&sa=X&oi=book_result&ct=result&resnum=1&ved=0CC8Q6AEwAA#v=onepage&q&f=false',
                'https://books.google.fr/books?id=cAUvWtW7x7kC&printsec=frontcover',
            ],
            [
                // .com.au
                'https://books.google.com.au/books?id=QHrQoDLNBUIC&pg=PT19&lpg=PT19&dq=Iotape+of+Commagene&source=web&ots=aZ3hKg3uDr&sig=Y_zdZhNP-qNZE6WIDNivPPm-Urg&hl=en&sa=X&oi=book_result&resnum=8&ct=result',
                'https://books.google.com.au/books?id=QHrQoDLNBUIC&pg=PT19&dq=Iotape+of+Commagene',
            ],
            [
                // 'id' in the middle
                'https://books.google.fr/books?hl=fr&id=CWkrAQAAMAAJ&dq=La+dur%C3%A9e+d%27ensoleillement+n%27est+pas+suffisante+en+Afrique&focus=searchwithinvolume&q=ceintures',
                'https://books.google.fr/books?id=CWkrAQAAMAAJ&q=ceintures',
            ],
            [
                // classic, variant host path '/books/about/<Titre>.html?id='
                'https://books.google.fr/books/about/Kate_Bush.html?id=YL0EDgAAQBAJ&printsec=frontcover&source=kp_read_button&redir_esc=y#v=onepage&q&f=false',
                'https://books.google.fr/books?id=YL0EDgAAQBAJ&printsec=frontcover',
            ],
            [
                // classic, two-level TLD .co.ma (preserved) + uppercase 'PG='
                'https://books.google.co.ma/books?id=26gcP_Yz-i8C&PG=PA56',
                'https://books.google.co.ma/books?id=26gcP_Yz-i8C&pg=PA56',
            ],
            [
                // uppercase "ID="
                'https://books.google.fr/books?ID=26gcP_Yz-i8C&PG=PA56',
                'https://books.google.fr/books?id=26gcP_Yz-i8C&pg=PA56',
            ],
            [
                // common pattern
                'https://books.google.fr/books?id=26gcP_Yz-i8C&pg=PA56&lpg=PA56&dq=André+Poznanski&source=bl&ots=tuFKKbkpUS&sig=ACfU3U058ij4qQHFsXX_KX01YK81SLCCBw&hl=fr&sa=X&ved=2ahUKEwiB6tHVtKbkAhULNRoKHbOeDXU4ChDoATAAegQICBAB#v=onepage&q=André%20Poznanski&f=false',
                'https://books.google.fr/books?id=26gcP_Yz-i8C&pg=PA56&dq=Andr%C3%A9+Poznanski',
            ],
            [
                // classic, '/books/reader' + http:// : forced back to https and to '/books'
                'http://books.google.com/books/reader?id=WH4rAAAAYAAJ',
                'https://books.google.com/books?id=WH4rAAAAYAAJ',
            ],
            [
                // classic, rare : no '/books' path at all, just '/?id='
                'https://books.google.com/?id=-0h134NR1s0C&pg=PA167&lpg=PA167&dq=Prairie+Shores+apartments+Michael+Reese#v=onepage&q=Prairie%20Shores%20apartments%20Michael%20Reese&f=false',
                'https://books.google.com/books?id=-0h134NR1s0C&pg=PA167&dq=Prairie+Shores+apartments+Michael+Reese',
            ],
            [
                // frontcover
                'https://books.google.fr/books?id=lcHcXrVhRUUC&printsec=frontcover&hl=fr&source=gbs_ge_summary_r&cad=0#v=onepage&q&f=false',
                'https://books.google.fr/books?id=lcHcXrVhRUUC&printsec=frontcover',
            ],
            [
                // classic on play.google.com (rare) : host rewritten to books.google.com
                'https://play.google.com/books/reader?id=1dtkAAAAMAAJ&printsec=frontcover&output=reader&hl=fr&pg=GBS.PR7',
                'https://books.google.com/books?id=1dtkAAAAMAAJ&pg=GBS.PR7&printsec=frontcover',
            ],
        ];
    }

    /**
     * New URL format (nov 2019) : id in the path, after a title slug.
     *
     * @dataProvider provideSimplifyNewFormat
     *
     * @throws Exception
     */
    public function testSimplifyGoogleUrlNewFormat(string $url, string $expected)
    {
        $this::assertSame(
            $expected,
            GoogleBooksUtil::simplifyGoogleUrl($url)
        );
    }

    public static function provideSimplifyNewFormat(): array
    {
        return [
            [
                // nothing worth keeping besides the path : 'hl' is the interface language
                'https://www.google.fr/books/edition/_/U4NmPwAACAAJ?hl=en',
                'https://www.google.fr/books/edition/_/U4NmPwAACAAJ',
            ],
            [
                // no query string at all
                'https://www.google.com/books/edition/A_Wrinkle_in_Time/r119-dYq0mwC',
                'https://www.google.com/books/edition/A_Wrinkle_in_Time/r119-dYq0mwC',
            ],
            [
                // keep the params selecting the preview page, drop 'hl'. Without them Google
                // serves the book presentation page, not the cited page (regression 2026-08-14).
                'https://www.google.fr/books/edition/Le_Bouquin_de_la_bande_dessin%C3%A9e/hAkCEAAAQBAJ?hl=fr&gbpv=1&dq=%22gwendal+rannou%22&pg=PT23&printsec=frontcover',
                'https://www.google.fr/books/edition/Le_Bouquin_de_la_bande_dessin%C3%A9e/hAkCEAAAQBAJ?pg=PT23&printsec=frontcover&dq=%22gwendal+rannou%22&gbpv=1',
            ],
            [
                // http:// upgraded to https://
                'http://www.google.fr/books/edition/Titre/mF3u6D8w--cC?pg=PA5',
                'https://www.google.fr/books/edition/Titre/mF3u6D8w--cC?pg=PA5&gbpv=1',
            ],
            [
                // "books." host normalized to the canonical "www." one
                'https://books.google.fr/books/edition/Titre/mF3u6D8w--cC?pg=PA5',
                'https://www.google.fr/books/edition/Titre/mF3u6D8w--cC?pg=PA5&gbpv=1',
            ],
            [
                // 'pg' without 'gbpv' lands on the book presentation page, not on the cited
                // page : this format needs gbpv=1, so it is added back
                'https://www.google.fr/books/edition/_/26gcP_Yz-i8C?pg=PA56',
                'https://www.google.fr/books/edition/_/26gcP_Yz-i8C?pg=PA56&gbpv=1',
            ],
            [
                // no 'pg' : nothing to preview, gbpv is not invented
                'https://www.google.fr/books/edition/_/26gcP_Yz-i8C?printsec=frontcover',
                'https://www.google.fr/books/edition/_/26gcP_Yz-i8C?printsec=frontcover',
            ],
            [
                // missing "www." added back
                'https://google.fr/books/edition/Titre/mF3u6D8w--cC',
                'https://www.google.fr/books/edition/Titre/mF3u6D8w--cC',
            ],
            [
                // country TLD preserved
                'https://www.google.co.ma/books/edition/Titre/mF3u6D8w--cC',
                'https://www.google.co.ma/books/edition/Titre/mF3u6D8w--cC',
            ],
            [
                // "_" is the neutral slug Google itself uses : kept as is, it resolves to the
                // same page as the real title slug
                'https://www.google.fr/books/edition/_/mF3u6D8w--cC?pg=PA5&gbpv=1',
                'https://www.google.fr/books/edition/_/mF3u6D8w--cC?pg=PA5&gbpv=1',
            ],
            [
                // empty slug segment : normalized to the neutral one rather than rejected
                'https://www.google.fr/books/edition//mF3u6D8w--cC?pg=PA5',
                'https://www.google.fr/books/edition/_/mF3u6D8w--cC?pg=PA5&gbpv=1',
            ],
            [
                // slug holding a '?' : unreadable, falls back on the neutral slug instead of
                // throwing — the volume ID is what identifies the book
                'https://www.google.fr/books/edition/Ti?tre/mF3u6D8w--cC',
                'https://www.google.fr/books/edition/_/mF3u6D8w--cC',
            ],
        ];
    }

    /**
     * @dataProvider provideExceptionURL
     */
    public function testSimplifyGoogleUrlException(string $url, string $expectedMessage)
    {
        $this::expectException(DomainException::class);
        $this::expectExceptionMessage($expectedMessage);
        GoogleBooksUtil::simplifyGoogleUrl($url);
    }

    public static function provideExceptionURL(): array
    {
        return [
            'id too short' => [
                'https://www.google.fr/books/edition/_/sfd',
                "no GoogleBook 'id' or 'isbn' in URL",
            ],
            'id longer than 12 chars : rejected, never truncated' => [
                'https://www.google.fr/books/edition/Titre/ABCDEFGHIJKLMNOP',
                "no GoogleBook 'id' or 'isbn' in URL",
            ],
            'classic format without id nor isbn' => [
                'https://books.google.fr/books?id=&isbn=',
                "no GoogleBook 'id' or 'isbn' in URL",
            ],
        ];
    }

    /**
     * The bot re-reads its own output : a second pass must not drift.
     *
     * @dataProvider provideSimplify
     * @dataProvider provideSimplifyNewFormat
     *
     * @throws Exception
     */
    public function testSimplifyGoogleUrlIsIdempotent(string $url, string $expected)
    {
        $this::assertSame(
            $expected,
            GoogleBooksUtil::simplifyGoogleUrl($expected)
        );
    }

    /**
     * @dataProvider provideIsGoogleBookURL
     */
    public function testIsGoogleBookURL(string $url, bool $expected)
    {
        $this::assertSame($expected, GoogleBooksUtil::isGoogleBookURL($url));
    }

    public static function provideIsGoogleBookURL(): array
    {
        return [
            // classic
            ['https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926&hl=fr#v=onepage&f=false', true],
            // new format, and its three accepted host variants
            ['https://www.google.fr/books/edition/Titre/mF3u6D8w--cC', true],
            ['http://www.google.fr/books/edition/Titre/mF3u6D8w--cC', true],
            ['https://books.google.fr/books/edition/Titre/mF3u6D8w--cC', true],
            ['https://google.fr/books/edition/Titre/mF3u6D8w--cC', true],
            // not Google Books at all, then classic host without a volume parameter
            ['https://www.lemonde.fr/article', false],
            ['https://books.google.fr/books?q=test', false],
        ];
    }

    /**
     * @dataProvider provideNewGoogleBookUrl
     */
    public function testIsNewGoogleBookUrlAndId(string $url, bool $isNew, ?string $expectedId)
    {
        $this::assertSame($isNew, GoogleBooksUtil::isNewGoogleBookUrl($url));
        $this::assertSame($expectedId, GoogleBooksUtil::getIDFromNewGBurl($url));
    }

    public static function provideNewGoogleBookUrl(): array
    {
        return [
            'exactly 12 chars' => [
                'https://www.google.fr/books/edition/Titre/mF3u6D8w--cC',
                true,
                'mF3u6D8w--cC',
            ],
            '12 chars then query string' => [
                'https://www.google.fr/books/edition/Titre/mF3u6D8w--cC?pg=PA5',
                true,
                'mF3u6D8w--cC',
            ],
            '12 chars then fragment' => [
                'https://www.google.fr/books/edition/Titre/mF3u6D8w--cC#v=onepage',
                true,
                'mF3u6D8w--cC',
            ],
            '11 chars : too short' => [
                'https://www.google.fr/books/edition/Titre/mF3u6D8w--c',
                false,
                null,
            ],
            '16 chars : never truncated to the first 12' => [
                'https://www.google.fr/books/edition/Titre/ABCDEFGHIJKLMNOP',
                false,
                null,
            ],
            'classic format : no path ID to extract' => [
                'https://books.google.fr/books?id=mF3u6D8w--cC',
                false,
                null,
            ],
            'neutral "_" slug' => [
                'https://www.google.fr/books/edition/_/mF3u6D8w--cC',
                true,
                'mF3u6D8w--cC',
            ],
        ];
    }

    /**
     * @dataProvider provideExtractGoogleBookData
     */
    public function testExtractGoogleBookData(string $url, array $expectedSubset)
    {
        $data = GoogleBooksUtil::extractGoogleBookData($url);
        foreach ($expectedSubset as $key => $value) {
            $this::assertSame($value, $data[$key] ?? null, sprintf("key '%s'", $key));
        }
    }

    public static function provideExtractGoogleBookData(): array
    {
        return [
            'classic : id from the query string' => [
                'https://books.google.fr/books?id=mF3u6D8w--cC&pg=PA56',
                ['id' => 'mF3u6D8w--cC', 'pg' => 'PA56'],
            ],
            'new format : id from the path, other params from the query string' => [
                'https://www.google.fr/books/edition/Titre/mF3u6D8w--cC?pg=PT23&gbpv=1',
                ['id' => 'mF3u6D8w--cC', 'pg' => 'PT23', 'gbpv' => '1'],
            ],
            'fragment parsed too' => [
                'https://books.google.fr/books?id=mF3u6D8w--cC#v=onepage&q=hello',
                ['id' => 'mF3u6D8w--cC', 'q' => 'hello'],
            ],
        ];
    }

    /**
     * @dataProvider provideValidateGoogleBooksId
     */
    public function testValidateGoogleBooksId(string $id, bool $expected)
    {
        $this::assertSame($expected, GoogleBooksUtil::validateGoogleBooksId($id));
    }

    public static function provideValidateGoogleBooksId(): array
    {
        return [
            ['mF3u6D8w--cC', true],
            ['26gcP_Yz-i8C', true],
            ['tooshort', false],
            ['ABCDEFGHIJKLMNOP', false],
            ['mF3u6D8w--c!', false],
            ['', false],
        ];
    }

    /**
     * @dataProvider provideTitleToSlug
     */
    public function testTitleToSlug(string $title, string $expected)
    {
        $this::assertSame($expected, GoogleBooksUtil::titleToSlug($title));
    }

    public static function provideTitleToSlug(): array
    {
        return [
            'spaces become underscores' => ['Les oracles de Shirataka', 'Les_oracles_de_Shirataka'],
            'accents percent-encoded, like Google does' => [
                'Le Bouquin de la bande dessinée',
                'Le_Bouquin_de_la_bande_dessin%C3%A9e',
            ],
            // punctuation dropped, not encoded : the slug is decorative, readability is all
            // that is left. "''" would also be italic markup on MediaWiki.
            'apostrophe and colon dropped' => ["L'Île d'Élise : récits", 'L_%C3%8Ele_d_%C3%89lise_r%C3%A9cits'],
            // a '/' would create an extra path segment, '|' would break a template parameter
            'slash, pipe and ampersand dropped' => ['Rock/Pop | Jazz & Blues', 'Rock_Pop_Jazz_Blues'],
            // hyphen and underscore survive : readable and URL-safe
            'hyphen kept' => ['Jean-Marc Jancovici', 'Jean-Marc_Jancovici'],
            'multiple spaces collapse' => ["A   B\tC", 'A_B_C'],
            'surrounding underscores trimmed' => ['  Titre  ', 'Titre'],
            'empty title falls back on the neutral slug' => ['   ', '_'],
            'punctuation-only title falls back too' => ['?!…', '_'],
            // truncation happens before encoding, so an escape is never cut in half
            'long title truncated to 60 chars' => [
                str_repeat('é', 80),
                str_repeat('%C3%A9', 60),
            ],
        ];
    }

    /**
     * Modernization is written and locked in, but not wired into any pipeline yet.
     *
     * @dataProvider provideToNewFormatUrl
     *
     * @throws Exception
     */
    public function testToNewFormatUrl(string $url, ?string $title, string $expected)
    {
        $this::assertSame($expected, GoogleBooksUtil::toNewFormatUrl($url, $title));
    }

    public static function provideToNewFormatUrl(): array
    {
        return [
            'classic without title : neutral slug' => [
                'https://books.google.fr/books?id=26gcP_Yz-i8C&pg=PA56',
                null,
                'https://www.google.fr/books/edition/_/26gcP_Yz-i8C?pg=PA56&gbpv=1',
            ],
            'classic with title : readable slug, gbpv added for the preview' => [
                'https://books.google.fr/books?id=26gcP_Yz-i8C&pg=PA56&dq=Poznanski&hl=fr&sa=X',
                'André Poznanski',
                'https://www.google.fr/books/edition/Andr%C3%A9_Poznanski/26gcP_Yz-i8C?pg=PA56&dq=Poznanski&gbpv=1',
            ],
            'no page : nothing to preview, no gbpv invented' => [
                'https://books.google.fr/books?id=26gcP_Yz-i8C',
                'Titre',
                'https://www.google.fr/books/edition/Titre/26gcP_Yz-i8C',
            ],
            'TLD preserved' => [
                'https://books.google.co.ma/books?id=26gcP_Yz-i8C&pg=PA56',
                null,
                'https://www.google.co.ma/books/edition/_/26gcP_Yz-i8C?pg=PA56&gbpv=1',
            ],
            'already new format : re-slugged, otherwise unchanged' => [
                'https://www.google.fr/books/edition/Ancien_slug/26gcP_Yz-i8C?pg=PA56&gbpv=1',
                'Nouveau titre',
                'https://www.google.fr/books/edition/Nouveau_titre/26gcP_Yz-i8C?pg=PA56&gbpv=1',
            ],
        ];
    }

    /**
     * An "?isbn=" link carries no volume ID, and the new format has no ISBN equivalent.
     */
    public function testToNewFormatUrlRejectsIsbnOnlyUrl()
    {
        $this::expectException(DomainException::class);
        $this::expectExceptionMessage("no GoogleBook 'id' in URL");
        GoogleBooksUtil::toNewFormatUrl('https://books.google.fr/books?isbn=0403099501');
    }

    public function testIsTrackingUrl()
    {
        $url
            = 'https://books.google.com.au/books?id=QHrQoDLNBUIC&pg=PT19&lpg=PT19&dq=Iotape+of+Commagene&source=web&ots=aZ3hKg3uDr&sig=Y_zdZhNP-qNZE6WIDNivPPm-Urg&hl=en&sa=X&oi=book_result&resnum=8&ct=result';
        $this::assertSame(
            true,
            GoogleBooksUtil::isTrackingUrl($url)
        );
        $url = 'https://books.google.com.au/books?id=QHrQoDLNBUIC&pg=PT19';
        $this::assertSame(
            false,
            GoogleBooksUtil::isTrackingUrl($url)
        );
    }
}
