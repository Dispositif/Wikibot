<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Application\WikiPageAction;
use Exception;
use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

// TODO move into /Application
class ServiceFactory
{
    private static ?AMQPStreamConnection $AMQPConnection = null;

    private static ?MediawikiFactory $wikiApi = null;

    //    private static $dbConnection;
    private static ?MediawikiApi $api = null;

    private function __construct()
    {
    }

    /**
     * AMQP queue (actual RabbitMQ)
     * todo $param
     * todo $channel->close(); $AMQPConnection->close();.
     *
     *
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

    // --Commented out by Inspection START (21/04/2020 02:45):
    //    /**
    //     * @throws Exception
    //     */
    //    public static function closeAMQPconnection()
    //    {
    //        if (isset(self::$AMQPConnection)) {
    //            self::$AMQPConnection->close();
    //            self::$AMQPConnection = null;
    //        }
    //    }
    // --Commented out by Inspection STOP (21/04/2020 02:45)
    /**
     * @throws UsageException
     */
    public static function getMediawikiApi(?bool $forceLogin = false): MediawikiApi
    {
        if (isset(self::$api) && $forceLogin !== true) {
            return self::$api;
        }
        self::$api = new MediawikiApi(getenv('WIKI_API_URL'));
        self::$api->login(
            new ApiUser(getenv('WIKI_API_USERNAME'), getenv('WIKI_API_PASSWORD'))
        );

        return self::$api;
    }

    /**
     * todo rename getMediawikiFactory
     * todo? replace that singleton pattern ??? (multi-lang wiki?).
     *
     *
     * @throws UsageException
     */
    public static function getMediawikiFactory(?bool $forceLogin = false): MediawikiFactory
    {
        if (isset(self::$wikiApi) && !$forceLogin) {
            return self::$wikiApi;
        }

        $api = self::getMediawikiApi($forceLogin);

        self::$wikiApi = new MediawikiFactory($api);

        return self::$wikiApi;
    }

    /**
     * @param bool $forceLogin
     *
     * @throws UsageException
     * @throws Exception
     */
    public static function wikiPageAction(string $title, $forceLogin = false): WikiPageAction
    {
        $wiki = self::getMediawikiFactory($forceLogin);

        return new WikiPageAction($wiki, $title);
    }

    public static function editInfo($summary = '', $minor = false, $bot = false, $maxLag = 5)
    {
        return new EditInfo($summary, $minor, $bot, $maxLag);
    }

    public static function getHttpClient(bool $torEnabled = false): HttpClientInterface
    {
        return HttpClientFactory::create($torEnabled);
    }
}
