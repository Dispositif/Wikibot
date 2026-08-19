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
use App\Domain\ExternLink\ExternRefTransformer;
use App\Domain\ExternLink\WikiwixContentResolver;
use App\Domain\InfrastructurePorts\BotEditJournalInterface;
use App\Domain\InfrastructurePorts\ExternLinkCheckRepositoryInterface;
use App\Domain\Publisher\ExternMapper;
use Exception;
use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;
use Psr\Log\LoggerInterface;
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
    public const BOT_EDIT_JOURNAL_ENABLED = true;

    private static ?MediawikiFactory $wikiApi = null;

    private static ?Mysql $mysqlConnection = null;
    private static ?MediawikiApi $api = null;

    private function __construct()
    {
    }

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
     * Shared wiring for the extern-link crawl/transform pipeline (Tor client for the crawl,
     * direct client for the webarchivers + optional anti-bot direct-retry fallback) — used by
     * every extern-ref/raw-extern-ref/existing-ref/last-extern-ref CLI entrypoint plus
     * testExternLink.php, so the constructor wiring can't silently drift between them the way
     * it did before 2026-08 (see audits/synthese-anti-bot-crawling-tor-2026-08.md).
     *
     * @param string[] $argv the CLI script's own $argv, forwarded to getExternLinkCheckRepository()
     */
    public static function getExternRefTransformer(
        LoggerInterface $log,
        array $argv = [],
        bool $torEnabled = true,
        bool $directRetryEnabled = true,
        bool $respectRobotsTxt = true
    ): ExternRefTransformer {
        $directClient = self::getHttpClient();
        $mainClient = $torEnabled ? self::getHttpClient(true) : $directClient;

        return new ExternRefTransformer(
            new ExternMapper($log),
            $mainClient,
            new InternetDomainParser(),
            $log,
            [new InternetArchiveAdapter($directClient, $log), new WikiwixAdapter($directClient, $log)],
            self::getExternLinkCheckRepository($argv),
            $directRetryEnabled ? $directClient : null,
            $respectRobotsTxt,
            // never over Tor, like the archivers above : the handshake's token is tied to
            // the requesting IP, and there is nothing to anonymize towards an archive
            // service crawled under agreement
            new WikiwixContentResolver($directClient, $log)
        );
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
