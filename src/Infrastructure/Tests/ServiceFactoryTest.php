<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Mediawiki\Api\MediawikiFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PHPUnit\Framework\TestCase;

class ServiceFactoryTest extends TestCase
{
    public function testCreateQueueChannel()
    {
        $this::markTestSkipped('integration webservice desactived');
//        $channel = ServiceFactory::queueChannel('foo');
//        $this::assertInstanceOf(AMQPChannel::class, $channel);
    }

    public function testCloseAMQPconnection()
    {
        $this::markTestIncomplete();
    }

    public function testWikiApi()
    {
        $this::markTestSkipped('integration webservice desactived');
//        $wiki = ServiceFactory::wikiApi();
//        $this::assertInstanceOf(MediawikiFactory::CLASS, $wiki);
    }
}
