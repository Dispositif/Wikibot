<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Simplon\Mysql\Mysql;
use Simplon\Mysql\PDOConnector;

/**
 * Class ServiceFactory.
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

    /**
     * @var Mysql
     */
    private static $dbConnection;

    private function __construct(){}

    /**
     * AMQP queue (actual RabbitMQ)
     * todo $param
     * todo $channel->close(); $AMQPConnection->close();.
     *
     * @param string $queueName
     *
     * @return AMQPChannel
     */
    public static function queueChannel(string $queueName): AMQPChannel
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
            $queueName,
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
     * todo? replace that singleton pattern ??? (multi-lang wiki?).
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

    /**
     * @return Mysql
     * @throws \Exception
     */
    public static function sqlConnection(): Mysql
    {
//        if (isset(self::$dbConnection)) {
//            return self::$dbConnection;
//        }
        $pdo = new PDOConnector(
            getenv('MYSQL_HOST'),
            getenv('MYSQL_USER'),
            getenv('MYSQL_PASSWORD'),
            getenv('MYSQL_DATABASE')
        );

        $pdoConn = $pdo->connect('utf8', ['port'=>getenv('MYSQL_PORT')]);
        self::$dbConnection = new Mysql($pdoConn);

        return self::$dbConnection;
    }
}
