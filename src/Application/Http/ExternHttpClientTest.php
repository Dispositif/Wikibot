<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Application\Http\ExternHttpClient;
use PHPUnit\Framework\TestCase;

class ExternHttpClientTest extends TestCase
{
    public function testIsWebUrl()
    {
        $this::assertTrue(ExternHttpClient::isWebURL('https://fr.wikipedia.fr/wiki/WP:BOT'));
        $this::assertTrue(ExternHttpClient::isWebURL('http://test.com'));
        $this::assertTrue(ExternHttpClient::isWebURL('https://www.youtube.com/watch?v=2zIW8qDPhos'));
        $this::assertTrue(ExternHttpClient::isWebURL('https://www.larousse.fr/dictionnaires/francais/île/41521'));

        $this::assertTrue(
            ExternHttpClient::isWebURL(
                'https://fr.wikisource.org/wiki/Dictionnaire_raisonné_de_l’architecture_française_du_XIe_au_XVIe_siècle/Baée,_Bée'
            )
        );
        $this::assertFalse(ExternHttpClient::isWebURL('ftp://test.com:88'));
        $this::assertfalse(ExternHttpClient::isWebURL('http://test.com bla'));
        $this::assertFalse(ExternHttpClient::isWebURL('bla'));
    }

}
