<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\Transformers\GoogleTransformer;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\GoogleBooksAdapter;
use PHPUnit\Framework\TestCase;
use Scriptotek\GoogleBooks\Volume;

class GoogleTransformerTest extends TestCase
{
    public function testExtractGoogleExternal()
    {
        $text = <<<EOF
            == Liens externes ==
            * https://www.britannica.com/topic/cohong
            *https://books.google.fr/books?id=VkwuTyHu60YC&pg=PA289&dq=9780674036123&hl=fr&sa=X&ved=0ahUKEwj85ObkwOPeAhUFaBoKHZ93BHUQ6AEIKTAA#v=onepage&q=Cohong&f=false
            * https://books.google.fr/books?id=MAFiCwAAQBAJ&pg=PR4&dq=9781442222922&hl=fr&sa=X&ved=0ahUKEwix0vifwePeAhVLPBoKHbe_D2QQ6AEIKTAA#v=onepage&q=Cohong&f=false
            
            {{Portail|monde chinois|République populaire de Chine}}
            EOF;

        $apiQuotaMock = $this->createMock(GoogleApiQuota::class);
        $googleBooksAdapterMock = $this->createMock(GoogleBooksAdapter::class);
        $trans = new GoogleTransformer($apiQuotaMock, $googleBooksAdapterMock);
        $this::assertSame(
            [
                [
                    '*https://books.google.fr/books?id=VkwuTyHu60YC&pg=PA289&dq=9780674036123&hl=fr&sa=X&ved=0ahUKEwj85ObkwOPeAhUFaBoKHZ93BHUQ6AEIKTAA#v=onepage&q=Cohong&f=false',
                    'https://books.google.fr/books?id=VkwuTyHu60YC&pg=PA289&dq=9780674036123&hl=fr&sa=X&ved=0ahUKEwj85ObkwOPeAhUFaBoKHZ93BHUQ6AEIKTAA#v=onepage&q=Cohong&f=false',
                ],
                [
                    '* https://books.google.fr/books?id=MAFiCwAAQBAJ&pg=PR4&dq=9781442222922&hl=fr&sa=X&ved=0ahUKEwix0vifwePeAhVLPBoKHbe_D2QQ6AEIKTAA#v=onepage&q=Cohong&f=false',
                    'https://books.google.fr/books?id=MAFiCwAAQBAJ&pg=PR4&dq=9781442222922&hl=fr&sa=X&ved=0ahUKEwix0vifwePeAhVLPBoKHbe_D2QQ6AEIKTAA#v=onepage&q=Cohong&f=false',
                ],

            ],
            $trans->extractGoogleExternalBullets($text)
        );
    }

    public function testExtractAllGoogleRefs()
    {
        $googleApiQuota = $this->createMock(GoogleApiQuota::class);
        $googleBooksAdapter = $this->createMock(GoogleBooksAdapter::class);
        $trans = new GoogleTransformer($googleApiQuota, $googleBooksAdapter);

        $wikiText = <<<EOF
            bla<ref>https://books.google.fr/books?hl=fr&lr=&id=QDO8cAyRFl4C&oi=fnd&pg=PA127&dq=stalingrad+Vilsmaier+critique&ots=UmJhhHJ5SM&sig=akUkKl3RfmQv83rdLF2q9ez3-A8&redir_esc=y#v=onepage&q=stalingrad%20Vilsmaier%20critique&f=false</ref> 
            qui menaçait ruine<ref>https://books.google.nl/books?id=VXAmAQAAIAAJ</ref>.
        EOF;
        $this::assertSame(
            [
                [
                    '<ref>https://books.google.fr/books?hl=fr&lr=&id=QDO8cAyRFl4C&oi=fnd&pg=PA127&dq=stalingrad+Vilsmaier+critique&ots=UmJhhHJ5SM&sig=akUkKl3RfmQv83rdLF2q9ez3-A8&redir_esc=y#v=onepage&q=stalingrad%20Vilsmaier%20critique&f=false</ref>',
                    'https://books.google.fr/books?hl=fr&lr=&id=QDO8cAyRFl4C&oi=fnd&pg=PA127&dq=stalingrad+Vilsmaier+critique&ots=UmJhhHJ5SM&sig=akUkKl3RfmQv83rdLF2q9ez3-A8&redir_esc=y#v=onepage&q=stalingrad%20Vilsmaier%20critique&f=false',
                ],
                [
                    '<ref>https://books.google.nl/books?id=VXAmAQAAIAAJ</ref>',
                    'https://books.google.nl/books?id=VXAmAQAAIAAJ',
                ],
            ],
            $trans->extractAllGoogleRefs($wikiText)
        );
    }

    /**
     * Lock in behavior for the new-format Google Books URL (id in the path, e.g.
     * .../edition/<titre>/<id>) : it must be recognized instead of throwing
     * "Pas de ISBN ou ID Google Books".
     */
    public function testConvertGBurl2OuvrageCitationWithNewFormatUrl()
    {
        $text = file_get_contents(__DIR__ . '/../Publisher/Tests/googleBook.json');
        $json = json_decode($text, null, 512, JSON_THROW_ON_ERROR);
        $volume = new Volume('KApgwgEACAAJ', $json->items[0]->volumeInfo);

        $apiQuotaMock = $this->createMock(GoogleApiQuota::class);
        $googleBooksAdapterMock = $this->createMock(GoogleBooksAdapter::class);
        $googleBooksAdapterMock->expects($this->once())
            ->method('getDataByGoogleId')
            ->with('KApgwgEACAAJ')
            ->willReturn($volume);

        $trans = new GoogleTransformer($apiQuotaMock, $googleBooksAdapterMock);

        $url = 'https://www.google.com/books/edition/Histoire_de_la_Provence/KApgwgEACAAJ?hl=fr';
        $citation = $trans->convertGBurl2OuvrageCitation($url);

        $this::assertStringContainsString('{{Ouvrage', $citation);
        $this::assertStringContainsString(
            'lire en ligne=https://www.google.com/books/edition/Histoire_de_la_Provence/KApgwgEACAAJ',
            $citation
        );
        // tracking/query params stripped from the cleaned URL
        $this::assertStringNotContainsString('hl=fr', $citation);
    }

    /**
     * 'pg' => 'passage' mapping. Only PA (arabic) and PR (roman) carry a printed page number :
     * PT is a scan-position locator, which {{Ouvrage}} cannot express (see {{Google Livres}}
     * doc, where it maps to 'page autre'). Pages 1-2 are the default Google landing view, not
     * a deliberate citation target.
     *
     * @dataProvider providePageParameter
     */
    public function testConvertGBurl2OuvrageCitationPassage(string $pg, ?string $expectedPassage)
    {
        $text = file_get_contents(__DIR__ . '/../Publisher/Tests/googleBook.json');
        $json = json_decode($text, null, 512, JSON_THROW_ON_ERROR);
        $volume = new Volume('KApgwgEACAAJ', $json->items[0]->volumeInfo);

        $apiQuotaMock = $this->createMock(GoogleApiQuota::class);
        $googleBooksAdapterMock = $this->createMock(GoogleBooksAdapter::class);
        $googleBooksAdapterMock->method('getDataByGoogleId')->willReturn($volume);

        $trans = new GoogleTransformer($apiQuotaMock, $googleBooksAdapterMock);
        $citation = $trans->convertGBurl2OuvrageCitation(
            'https://books.google.fr/books?id=KApgwgEACAAJ&pg=' . $pg
        );

        if ($expectedPassage === null) {
            $this::assertStringNotContainsString('passage=', $citation);

            return;
        }
        $this::assertStringContainsString('passage=' . $expectedPassage, $citation);
    }

    public static function providePageParameter(): array
    {
        return [
            'PA : printed page' => ['PA333', '333'],
            'PR : roman numbering' => ['PR7', 'vii'],
            'RA1-PAx : printed page of a section' => ['RA1-PA184', '184'],
            'PT : scan locator, no {{Ouvrage}} equivalent' => ['PT23', null],
            'PA1 : default view, not a citation target' => ['PA1', null],
            'PA2 : default view, not a citation target' => ['PA2', null],
        ];
    }
}
