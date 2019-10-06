<?php


namespace App\Infrastructure;

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
    private static $wikiApi;

    private function __construct() { }

    /**
     * AMQP queue (actual RabbitMQ)
     * todo $param
     *
     * @param string $taskName
     *
     * @return AMQPChannel
     */
    public static function queueChannel(string $taskName): AMQPChannel
    {
        if (!isset(self::$AMQPConnection)) {
            self::$AMQPConnection = new AMQPStreamConnection(
                getenv('AMQP_HOST'),
                getenv('AMQP_PORT'),
                getenv('AMQP_USER'),
                getenv('AMQP_PASSWORD'),
                getenv('AMQP_VHOST')
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
    public static function wikiApi(): MediawikiFactory
    {
        if (isset(self::$wikiApi)) {
            return self::$wikiApi;
        }

        $api = new MediawikiApi(getenv('API_URL'));
        $api->login(
            new ApiUser(getenv('API_USERNAME'), getenv('API_PASSWORD'))
        );

        self::$wikiApi = new MediawikiFactory($api);

        return self::$wikiApi;
    }

}
