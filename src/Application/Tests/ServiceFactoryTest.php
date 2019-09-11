<?php

namespace App\Application;

use Mediawiki\Api\MediawikiFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PHPUnit\Framework\TestCase;

class ServiceFactoryTest extends TestCase
{

    public function testCreateQueueChannel()
    {
        $this::markTestSkipped();
//        $channel = ServiceFactory::queueChannel('foo');
//        $this::assertInstanceOf(AMQPChannel::class, $channel);
    }

    public function testCloseAMQPconnection()
    {
        $this::markTestIncomplete();
    }

    public function testWikiApi()
    {
        $this::markTestSkipped();
//        $wiki = ServiceFactory::wikiApi();
//        $this::assertInstanceOf(MediawikiFactory::CLASS, $wiki);
    }
}
