<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki\Tests;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Publisher\GoogleBooksUtil;
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
            GoogleBooksUtil::isGoogleBookURL($url)
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
     *
     * @throws Exception
     */
    public function testSimplifyGoogleBookUrl($url, $expected)
    {
        $this::assertEquals(
            $expected,
            GoogleBooksUtil::simplifyGoogleUrl($url)
        );
    }

    /**
     * @dataProvider provideExceptionURL
     */
    public function testSimplifyGoogleBookUrlException(string $url)
    {
        $this::expectExceptionMessage("no GoogleBook 'id' or 'isbn' in URL");
        GoogleBooksUtil::simplifyGoogleUrl($url);
    }

    public static function provideExceptionURL(): array
    {
        return [
            ['https://www.google.fr/books/edition/_/sfd'],
        ];
    }

    public static function provideSimplify(): array
    {
        return [
            [
                // new Google Book format (nov 2019)
                'https://www.google.fr/books/edition/_/U4NmPwAACAAJ?hl=en',
                'https://www.google.fr/books/edition/_/U4NmPwAACAAJ?hl=en',
            ],
            [
                // new Google Book format (nov 2019)
                'https://www.google.com/books/edition/A_Wrinkle_in_Time/r119-dYq0mwC',
                'https://www.google.com/books/edition/A_Wrinkle_in_Time/r119-dYq0mwC',
            ],
            [
              // no 'id' but 'isbn' parameter
              'https://books.google.fr/books?isbn=0403099501&ots=aZ3hKg3uDr',
              'https://books.google.fr/books?isbn=0403099501'
            ],
            [
                // 'id' not first parameter
                'https://books.google.com/books?ots=aZ3hKg3uDr&id=QHrQoDLNBUIC&pg=PT19&lpg=PT19&sig=Y_zdZhNP-qNZE6WIDNivPPm-Urg&hl=en&sa=X&oi=book_result&resnum=8&ct=result',
                'https://books.google.com/books?id=QHrQoDLNBUIC&pg=PT19'
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
                // 3 : strange format
                'https://books.google.fr/books/about/Kate_Bush.html?id=YL0EDgAAQBAJ&printsec=frontcover&source=kp_read_button&redir_esc=y#v=onepage&q&f=false',
                'https://books.google.fr/books?id=YL0EDgAAQBAJ&printsec=frontcover',
            ],
            [
                // Maroc : sous-domaine .co.ma
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
                // pattern 'http://' and '/books/reader'
                'http://books.google.com/books/reader?id=WH4rAAAAYAAJ',
                'https://books.google.com/books?id=WH4rAAAAYAAJ',
            ],
            [
                // pattern rare : https://books.google.com/?id=-0h134NR1s0C
                'https://books.google.com/?id=-0h134NR1s0C&pg=PA167&lpg=PA167&dq=Prairie+Shores+apartments+Michael+Reese#v=onepage&q=Prairie%20Shores%20apartments%20Michael%20Reese&f=false',
                'https://books.google.com/books?id=-0h134NR1s0C&pg=PA167&dq=Prairie+Shores+apartments+Michael+Reese',
            ],
            [
                // frontcover
                'https://books.google.fr/books?id=lcHcXrVhRUUC&printsec=frontcover&hl=fr&source=gbs_ge_summary_r&cad=0#v=onepage&q&f=false',
                'https://books.google.fr/books?id=lcHcXrVhRUUC&printsec=frontcover',
            ],
            [
                // play.google.com (rare)
                'https://play.google.com/books/reader?id=1dtkAAAAMAAJ&printsec=frontcover&output=reader&hl=fr&pg=GBS.PR7',
                'https://books.google.com/books?id=1dtkAAAAMAAJ&pg=GBS.PR7&printsec=frontcover',
            ],
        ];
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
