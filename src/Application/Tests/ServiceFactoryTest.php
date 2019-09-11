<?php

namespace App\Application;

use PhpAmqpLib\Channel\AMQPChannel;
use PHPUnit\Framework\TestCase;

class ServiceFactoryTest extends TestCase
{

    public function testCreateQueueChannel()
    {
        $this::markTestSkipped();
//        $channel = ServiceFactory::createQueueChannel('foo');
//        $this::assertInstanceOf(AMQPChannel::class, $channel);
    }

    public function testCloseAMQPconnection()
    {
        $this::markTestIncomplete();
    }
}
