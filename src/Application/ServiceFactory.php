<?php


namespace App\Application;

use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

require_once 'myBootstrap.php'; // todo temp

/**
 * Class ServiceFactory
 */
class ServiceFactory
{
    /**
     * @var AMQPStreamConnection
     */
    private static $AMQPConnection;

    /**
     * @var MediawikiFactory
     */
    private static $WikiApi;

    private function __construct() { }

    /**
     * AMQP queue (actual RabbitMQ)
     * todo $param
     *
     * @param string $taskName
     *
     * @return AMQPChannel
     */
    public static function QueueChannel(string $taskName): AMQPChannel
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
            self::$AMQPConnection = null;
        }
    }

    /**
     * todo? replace that singleton pattern ??? (multi-lang wiki?)
     *
     * @return MediawikiFactory
     * @throws \Mediawiki\Api\UsageException
     */
    public static function WikiApi(): MediawikiFactory
    {
        if (isset(self::$WikiApi)) {
            return self::$WikiApi;
        }

        $api = new MediawikiApi($_ENV['API_URL']);
        $api->login(
            new ApiUser($_ENV['API_USERNAME'], $_ENV['API_PASSWORD'])
        );

        self::$WikiApi = new MediawikiFactory($api);

        return self::$WikiApi;
    }

}
