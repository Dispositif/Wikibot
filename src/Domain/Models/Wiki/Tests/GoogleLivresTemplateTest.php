<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki\Tests;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use Exception;
use PHPUnit\Framework\TestCase;

class GoogleLivresTemplateTest extends TestCase
{
    /**
     * URL parsing/cleaning itself is covered by GoogleBooksUtilTest — here only what the
     * template adds on top of it.
     *
     * @dataProvider provideIsGoogleBookValue
     */
    public function testIsGoogleBookValue(string $text, bool $expected)
    {
        $this::assertSame($expected, GoogleLivresTemplate::isGoogleBookValue($text));
    }

    public static function provideIsGoogleBookValue(): array
    {
        return [
            ['https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&hl=fr#v=onepage&f=false', true],
            ['https://www.google.fr/books/edition/Titre/mF3u6D8w--cC', true],
            ['{{Google Livres|pbspjvZst5UC|page=395}}', true],
            ['{{Google Books|pbspjvZst5UC}}', true],
            ['https://www.lemonde.fr/article', false],
        ];
    }

    /**
     * @dataProvider provideGoogleUrl
     *
     *
     * @throws Exception
     */
    public function testCreateFromURL(string $url, string $expected)
    {
        $this::assertEquals(
            $expected,
            GoogleLivresTemplate::createFromURL($url)->serialize()
        );
    }

    public static function provideGoogleUrl(): array
    {
        return [
            [
                'https://books.google.fr/books?id=pbspjvZst5UC',
                '{{Google Livres|pbspjvZst5UC}}',
            ],
            [
                // partial book and cover
                'https://books.google.com/books?id=UNgxtsjOIf4C&printsec=frontcover',
                '{{Google Livres|UNgxtsjOIf4C|couv=1}}',
            ],
            [
                // page pg=PA... (arabe)
                'https://books.google.com/books?id=UNgxtsjOIf4C&pg=PA333',
                '{{Google Livres|UNgxtsjOIf4C|page=333}}',
            ],
            [
                // page pg=PR... (romain)
                'https://books.google.com/books?id=UNgxtsjOIf4C&pg=PR333',
                '{{Google Livres|UNgxtsjOIf4C|page=333|romain=1}}',
            ],
            [
                // page autre RAz-PAx
                'https://books.google.fr/books?id=BS4HAQAAIAAJ&pg=RA1-PA184',
                '{{Google Livres|BS4HAQAAIAAJ|page autre=RA1-PA184}}',
            ],
            [
                // page autre PTx
                'https://books.google.fr/books?id=YqZDAgAAQBAJ&pg=PT77',
                '{{Google Livres|YqZDAgAAQBAJ|page autre=PT77}}',
            ],
            [
                // surlignage
                'https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926&source=bl&ots=kiCzMrHO7b&sig=Jxt2Ybpig7Oo-Mtuzgp_sL5ipQ4&hl=fr&sa=X&ei=6SMLU_zIDarL0AX75YAI&ved=0CFEQ6AEwBA#v=onepage&q=D%C3%A9cret-Loi%2010%20septembre%201926&f=false',
                '{{Google Livres|pbspjvZst5UC|page=395|surligne=Décret-Loi+10+septembre+1926}}',
            ],
            [
                // new Google Book format (nov 2019) : id in the URL path
                'https://www.google.com/books/edition/A_Wrinkle_in_Time/r119-dYq0mwC',
                '{{Google Livres|r119-dYq0mwC}}',
            ],
            [
                // new Google Book format (nov 2019) : id in the URL path, with query string
                'https://www.google.fr/books/edition/_/U4NmPwAACAAJ?hl=en',
                '{{Google Livres|U4NmPwAACAAJ}}',
            ],
        ];
    }

    /**
     * serialize() hides the 'id=' / 'titre=' parameter names, but must not touch an "id="
     * or "titre=" occurring inside a value — a search phrase can legitimately contain one.
     */
    public function testSerializeKeepsParamNamesInsideValues()
    {
        $url = 'https://books.google.fr/books?id=pbspjvZst5UC&pg=PA5&dq=le+titre=faux+et+id=bidon';

        $this::assertSame(
            '{{Google Livres|pbspjvZst5UC|page=5|surligne=le+titre=faux+et+id=bidon}}',
            GoogleLivresTemplate::createFromURL($url)->serialize()
        );
    }

    public function testCreateFromUrlWithTitle()
    {
        $url = 'https://books.google.fr/books?id=pbspjvZst5UC';
        $google = GoogleLivresTemplate::createFromURL($url);
        $google->setParam('titre', 'Hello');

        $this::assertEquals(
            '{{Google Livres|pbspjvZst5UC|Hello}}',
            $google->serialize()
        );
    }
}
