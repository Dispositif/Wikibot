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
}
