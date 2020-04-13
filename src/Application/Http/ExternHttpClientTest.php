<?php

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

        $this::assertFalse(ExternHttpClient::isWebURL('ftp://test.com:88'));
        $this::assertfalse(ExternHttpClient::isWebURL('http://test.com bla'));
        $this::assertFalse(ExternHttpClient::isWebURL('bla'));
    }

}
