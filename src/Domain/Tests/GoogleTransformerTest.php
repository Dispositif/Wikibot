<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\GoogleTransformer;
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

        $googleBooksAdapterMock = $this->createMock(GoogleBooksAdapter::class);
        $trans = new GoogleTransformer(new GoogleApiQuota(), $googleBooksAdapterMock);
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

}
