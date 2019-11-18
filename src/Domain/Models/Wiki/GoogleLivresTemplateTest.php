<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

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
    }

    /**
     * @dataProvider provideGoogleUrl
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
                '{{Google Livres|pbspjvZst5UC}}'
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
}
