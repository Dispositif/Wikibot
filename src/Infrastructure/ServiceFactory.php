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
use App\Domain\InfrastructurePorts\BotEditJournalInterface;
use App\Domain\InfrastructurePorts\ExternLinkCheckRepositoryInterface;
use Exception;
use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Simplon\Mysql\Mysql;
use Simplon\Mysql\PDOConnector;

// TODO move into /Application
class ServiceFactory
{
    /**
     * Hard kill-switch for the extern_link_check persistence (§9.6) : flip to false to
     * make every extern-ref CLI script run fully SQL-free (no connection attempted at
     * all), independent of the per-run --no-db flag. Useful for a worker fleet where
     * some hosts (other VPS) never have DB access at all.
     */
    public const EXTERN_LINK_CHECK_ENABLED = true;

    /**
     * Opt-in on purpose, unlike EXTERN_LINK_CHECK_ENABLED : bot_page_analyzed is the
     * skip-reprocessing guard (was this page already handled ?), not just advisory
     * state. Flipping this to true before running the one-shot import of the existing
     * article_edited.txt / article_externRef_edited.txt / gooBot_edited.txt makes every
     * worker start believing no page was ever analyzed — mass re-processing/re-editing
     * of ~28k already-handled articles. See database_schema.sql (bot_page_analyzed,
     * bot_edit) and BotEditJournalInterface.
     */
    public const BOT_EDIT_JOURNAL_ENABLED = false;

    private static ?AMQPStreamConnection $AMQPConnection = null;

    private static ?MediawikiFactory $wikiApi = null;

    private static ?Mysql $mysqlConnection = null;
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

    /**
     * Shared MySQL connection (same credentials as DbAdapter's own, currently opened
     * separately there — not consolidated to avoid touching the ISBN pipeline here).
     */
    public static function getMysqlConnection(): Mysql
    {
        if (!isset(self::$mysqlConnection)) {
            $pdo = new PDOConnector(
                getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE')
            );
            self::$mysqlConnection = new Mysql($pdo->connect('utf8', ['port' => getenv('MYSQL_PORT')]));
        }

        return self::$mysqlConnection;
    }

    /**
     * Skips any MySQL connection attempt entirely — not just DB writes — when disabled
     * via EXTERN_LINK_CHECK_ENABLED or the CLI's own $argv containing --no-db. Lets a
     * worker run on a host with no DB access at all (e.g. a crawling-only VPS).
     *
     * @param string[] $argv the CLI script's own $argv
     */
    public static function getExternLinkCheckRepository(array $argv = []): ExternLinkCheckRepositoryInterface
    {
        if (!self::EXTERN_LINK_CHECK_ENABLED || in_array('--no-db', $argv, true)) {
            return new NullExternLinkCheckRepository();
        }

        return new ExternLinkCheckAdapter(self::getMysqlConnection());
    }

    /**
     * @param string $analyzedFilePath Per-task file (e.g. ARTICLE_ANALYZED_FILENAME) —
     * only consulted by the file fallback, when BOT_EDIT_JOURNAL_ENABLED is off.
     */
    public static function getBotEditJournal(string $analyzedFilePath): BotEditJournalInterface
    {
        if (!self::BOT_EDIT_JOURNAL_ENABLED) {
            $editionsFilePath = preg_replace('/\.txt$/', '_editions.txt', $analyzedFilePath)
                ?? ($analyzedFilePath . '_editions.txt');

            return new FileBotEditJournal($analyzedFilePath, $editionsFilePath);
        }

        return new BotEditJournalAdapter(self::getMysqlConnection());
    }
}
