<?php


namespace App\Application;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

require_once 'myBootstrap.php'; // temp

class ServiceFactory
{
    /**
     * @var AMQPStreamConnection|null
     */
    static $AMQPConnection;

    private function __construct() { }

    /**
     * AMQP queue (actual RabbitMQ)
     * todo $param
     *
     * @param string $taskName
     *
     * @return AMQPChannel
     */
    public static function createQueueChannel(string $taskName): AMQPChannel
    {
        if (!isset(self::$AMQPConnection)) {
            self::$AMQPConnection = new AMQPStreamConnection(
                $_ENV['AMQP_HOST'],
                $_ENV['AMQP_PORT'],
                $_ENV['AMQP_USER'],
                $_ENV['AMQP_PASSWORD'],
                $_ENV['AMQP_VHOST']
            );
        }

        $channel = self::$AMQPConnection->channel();

        $channel->queue_declare(
            $taskName,
            false,
            true, // won't be lost if MQ server restarts
            false,
            false
        );

        return $channel;
    }

    /**
     * @throws \Exception
     */
    public static function closeAMQPconnection()
    {
        if (isset(self::$AMQPConnection)) {
            self::$AMQPConnection->close();
        }
    }
}
