<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki\Tests;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use Exception;
use PHPUnit\Framework\TestCase;

class GoogleLivresTemplateTest extends TestCase
{
    public function testIsGoogleBookURL()
    {
        $url
            = 'https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926&source=bl&ots=kiCzMrHO7b&sig=Jxt2Ybpig7Oo-Mtuzgp_sL5ipQ4&hl=fr&sa=X&ei=6SMLU_zIDarL0AX75YAI&ved=0CFEQ6AEwBA#v=onepage&q=D%C3%A9cret-Loi%2010%20septembre%201926&f=false';
        $this::assertEquals(
            true,
            GoogleLivresTemplate::isGoogleBookURL($url)
        );
        $this::assertEquals(
            true,
            GoogleLivresTemplate::isGoogleBookValue($url)
        );
    }

    /**
     * @dataProvider provideSimplify
     *
     * @param $url
     * @param $expected
     */
    public function testSimplyGoogleBookUrl($url, $expected)
    {
        $this::assertEquals(
            $expected,
            GoogleLivresTemplate::simplifyGoogleUrl($url)
        );
    }

    public function provideSimplify()
    {
        return [
            [
                // common pattern
                'https://books.google.fr/books?id=26gcP_Yz-i8C&pg=PA56&lpg=PA56&dq=André+Poznanski&source=bl&ots=tuFKKbkpUS&sig=ACfU3U058ij4qQHFsXX_KX01YK81SLCCBw&hl=fr&sa=X&ved=2ahUKEwiB6tHVtKbkAhULNRoKHbOeDXU4ChDoATAAegQICBAB#v=onepage&q=André%20Poznanski&f=false',
                'https://books.google.com/books?id=26gcP_Yz-i8C&pg=PA56&dq=Andr%C3%A9+Poznanski',
            ],
            [
                // pattern 'http://' and '/books/reader'
                'http://books.google.com/books/reader?id=WH4rAAAAYAAJ',
                'https://books.google.com/books?id=WH4rAAAAYAAJ',
            ],
            [
                // frontcover
                'https://books.google.fr/books?id=lcHcXrVhRUUC&printsec=frontcover&hl=fr&source=gbs_ge_summary_r&cad=0#v=onepage&q&f=false',
                'https://books.google.com/books?id=lcHcXrVhRUUC&printsec=frontcover',
            ],
            [
                // play.google.com (rare)
                'https://play.google.com/books/reader?id=1dtkAAAAMAAJ&printsec=frontcover&output=reader&hl=fr&pg=GBS.PR7',
                'https://books.google.com/books?id=1dtkAAAAMAAJ&pg=GBS.PR7&printsec=frontcover',
            ],
        ];
    }

    /**
     * @dataProvider provideGoogleUrl
     *
     * @param string $url
     * @param string $expected
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

    public function provideGoogleUrl(): array
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
                '{{Google Livres|pbspjvZst5UC|page=395|surligne=D%C3%A9cret-Loi+10+septembre+1926}}',
            ],
        ];
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
