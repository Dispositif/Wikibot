<?php


namespace App\Application;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ServiceFactory
{
    /**
     * AMQP (RabbitMQ) queue
     * todo $param
     *
     * @param string $taskName
     *
     * @return AMQPChannel
     */
    public static function createQueueChannel(string $taskName): AMQPChannel
    {
        require_once 'myBootstrap.php';

        $connection = new AMQPStreamConnection(
            $_ENV['AMQP_HOST'],
            $_ENV['AMQP_PORT'],
            $_ENV['AMQP_USER'],
            $_ENV['AMQP_PASSWORD'],
            $_ENV['AMQP_VHOST']
        );
        $channel = $connection->channel();


        $channel->queue_declare(
            $taskName,
            false,
            true, // won't be lost if MQ server restarts
            false,
            false
        );

        return $channel;
    }
}
