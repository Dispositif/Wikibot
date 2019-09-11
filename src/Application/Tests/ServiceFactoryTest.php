<?php

namespace App\Application;

use PhpAmqpLib\Channel\AMQPChannel;
use PHPUnit\Framework\TestCase;

class ServiceFactoryTest extends TestCase
{

    public function testCreateQueueChannel()
    {
        $channel = ServiceFactory::createQueueChannel('foo');
        $this::assertInstanceOf(AMQPChannel::class, $channel);
    }

    public function testCloseAMQPconnection()
    {
        $this::markTestSkipped();
    }
}
