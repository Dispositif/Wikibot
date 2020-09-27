<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\WikiPageAction;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;
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

    //    private static $dbConnection;

    /**
     * @var MediawikiApi
     */
    private static $api;

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
     * @param bool|null $forceLogin
     *
     * @return MediawikiApi
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
     * todo rename
     * todo? replace that singleton pattern ??? (multi-lang wiki?).
     *
     * @param bool|null $forceLogin
     *
     * @return MediawikiFactory
     * @throws UsageException
     */
    public static function wikiApi(?bool $forceLogin = false): MediawikiFactory
    {
        if (isset(self::$wikiApi) && !$forceLogin) {
            return self::$wikiApi;
        }

        $api = self::getMediawikiApi($forceLogin);

        self::$wikiApi = new MediawikiFactory($api);

        return self::$wikiApi;
    }

    /**
     * @param string $title
     * @param bool   $forceLogin
     *
     * @return WikiPageAction
     * @throws UsageException
     * @throws Exception
     */
    public static function wikiPageAction(string $title, $forceLogin = false): WikiPageAction
    {
        $wiki = self::wikiApi($forceLogin);

        return new WikiPageAction($wiki, $title);
    }

    public static function editInfo($summary = '', $minor = false, $bot = false, $maxLag = 5)
    {
        return new EditInfo($summary, $minor, $bot, $maxLag);
    }

    public static function httpClient(?array $option = null): ClientInterface
    {
        $option = $option ?? [];
        $defaultOption = [
            'timeout' => 60,
            'allow_redirects' => true,
            'headers' => ['User-Agent' => getenv('USER_AGENT')],
            'verify' => false, // CURLOPT_SSL_VERIFYHOST
            //                'proxy'           => '192.168.16.1:10',
        ];
        $option = array_merge($defaultOption, $option);

        return new Client($option);
    }

}
