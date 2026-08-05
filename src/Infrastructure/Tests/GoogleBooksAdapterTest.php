<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Domain\Exceptions\QuotaExceededException;
use App\Domain\InfrastructurePorts\GoogleApiQuotaInterface;
use App\Infrastructure\GoogleBooksAdapter;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Scriptotek\GoogleBooks\GoogleBooks as GoogleAPI;
use Scriptotek\GoogleBooks\Volume;

class GoogleBooksAdapterTest extends TestCase
{
    public function testGetDataByGoogleIdThrowsWhenQuotaReached()
    {
        $quotaMock = $this->createMock(GoogleApiQuotaInterface::class);
        $quotaMock->method('isQuotaReached')->willReturn(true);
        $quotaMock->expects($this->never())->method('increment');

        // No API client injected either : checkGoogleQuota() must short-circuit
        // before any HTTP call is attempted.
        $adapter = new GoogleBooksAdapter($quotaMock);

        $this->expectException(QuotaExceededException::class);
        $adapter->getDataByGoogleId('KApgwgEACAAJ');
    }

    /**
     * Locks in the fix : a real (mocked) API call increments the *injected*
     * quota counter, so it stays in sync with whoever else shares that instance
     * (e.g. GoogleTransformer's pre-checks).
     */
    public function testGetDataByGoogleIdIncrementsInjectedQuotaOnSuccess()
    {
        $text = file_get_contents(__DIR__ . '/../../Domain/Publisher/Tests/googleBook.json');
        $json = json_decode($text, null, 512, JSON_THROW_ON_ERROR);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($json->items[0])),
        ]);
        $api = new GoogleAPI(['handler' => HandlerStack::create($mockHandler)]);

        $quotaMock = $this->createMock(GoogleApiQuotaInterface::class);
        $quotaMock->method('isQuotaReached')->willReturn(false);
        $quotaMock->expects($this->once())->method('increment');

        $adapter = new GoogleBooksAdapter($quotaMock, $api);
        $volume = $adapter->getDataByGoogleId('KApgwgEACAAJ');

        $this::assertInstanceOf(Volume::class, $volume);
        $this::assertSame('Histoire de la Provence....', $volume->title);
    }
}
