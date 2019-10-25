<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\PredictLienAuteur;
use Exception;
use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\UsageException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

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

    private static $dbConnection;

    private function __construct()
    {
    }

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
     * @throws Exception
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
     *
     * @throws UsageException
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
     * @return DbAdapter
     */
    public static function sqlConnection(): DbAdapter
    {
        if (isset(self::$dbConnection)) {
            return self::$dbConnection;
        }
        self::$dbConnection = new DbAdapter();

        return self::$dbConnection;
    }

    public static function PredictAuthor(): PredictLienAuteur
    {
        return new PredictLienAuteur(self::wikiApi());
    }
}
