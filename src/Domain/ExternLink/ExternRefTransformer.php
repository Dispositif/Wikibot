<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\ExternLink\Validators\InterstitialPageValidator;
use App\Domain\ExternLink\Validators\LinkGateInterface;
use App\Domain\ExternLink\Validators\RobotNoIndexValidator;
use App\Domain\InfrastructurePorts\DeadlinkArchiverInterface;
use App\Domain\InfrastructurePorts\ExternLinkCheckRepositoryInterface;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Domain\Models\Summary;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Publisher\ExternMapper;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\WikiOptimizer\OptimizerFactory;
use App\Domain\WikiTemplateFactory;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\NullExternLinkCheckRepository;
use Exception;
use Normalizer;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * TODO refac too big (responsibility)
 */
class ExternRefTransformer implements ExternRefTransformerInterface
{
    use SummaryExternTrait, PublisherLogicTrait;

    final public const HTTP_REQUEST_LOOP_DELAY = 10;
    final public const SKIP_DOMAIN_FILENAME = __DIR__ . '/../resources/config_skip_domain.txt';
    final public const REPLACE_404 = true;
    final public const REPLACE_410 = true;
    final public const CONFIG_PRESSE = __DIR__ . '/../resources/config_presse.yaml';
    final public const CONFIG_NEWSPAPER_JSON = __DIR__ . '/../resources/data_newspapers.json';
    final public const CONFIG_SCIENTIFIC_JSON = __DIR__ . '/../resources/data_scientific_domain.json';
    final public const CONFIG_SCIENTIFIC_WIKI_JSON = __DIR__ . '/../resources/data_scientific_wiki.json';

    /** HTTP statuses that might mean "blocked by anti-bot", not "content is gone" — see audits/synthese-anti-bot-crawling-tor-2026-08.md */
    private const BLOCK_SUSPECT_STATUSES = [403, 429, 503];

    public bool $skipSiteBlacklisted = true;
    public bool $skipRobotNoIndex = true;
    public array $summaryLog = [];

    protected $config;
    protected ?string $registrableDomain = null;
    protected string $url;
    protected array $publisherData = [];
    protected array $skip_domain = [];
    protected ExternPage $externalPage;
    protected ?Summary $summary = null;
    protected ?string $originDomain = null;
    protected array $options = [];
    private readonly ExternHttpErrorLogic $externHttpErrorLogic;
    private readonly CheckURL $urlChecker;
    private readonly DeadLinkTransformer $deadLinkTransformer;

    /**
     * @param DeadlinkArchiverInterface[] $deadlinkArchivers
     */
    public function __construct(
        protected ExternMapper                  $mapper,
        protected HttpClientInterface           $httpClient,
        protected InternetDomainParserInterface $domainParser,
        protected LoggerInterface               $log = new NullLogger(),
        protected array                         $deadlinkArchivers = [],
        private readonly ExternLinkCheckRepositoryInterface $linkCheckRepository = new NullExternLinkCheckRepository(),
        // "2nd pass without Tor" fallback, only used when looksBlocked() fires (not on
        // every request) and self-identifying honestly (not the Tor pass's browser UA)
        // when it does — see audits/synthese-anti-bot-crawling-tor-2026-08.md. Workers
        // enable this by default and expose a --no-direct-retry opt-out; null here just
        // means "no client was wired up", not "feature disabled" per se.
        private readonly ?HttpClientInterface $directRetryClient = null,
    )
    {
        $this->importConfigAndData();
        $this->deadLinkTransformer = new DeadLinkTransformer($deadlinkArchivers, $domainParser, null, $log);
        $this->externHttpErrorLogic = new ExternHttpErrorLogic($this->deadLinkTransformer, $log, $this->linkCheckRepository);
        $this->urlChecker = new CheckURL($domainParser, $log);
    }

    /**
     * Transform "http://bla" => "{lien web|...}}", "{article}" or "{lien brisé}".
     *
     * TODO Refac : chain of responsibility
     * todo refac : return data DTO ? to much responsability!
     *
     * @throws Exception
     */
    public function process(string $url, Summary $summary = new Summary(), array $options = []): string
    {
        $this->url = $url;
        $this->options = $options; // used only to pass RegistrableDomain of archived deadlink, or pageTitle
        // pageTitle absent (e.g. the recursive call DeadLinkTransformer makes on an archive
        // URL) => no ExternLinkCheckRepository persistence : a failure without a citing
        // page to go back to isn't actionable, see docs/audit-gestion-erreurs-crawl-2026-08.md §9.6.
        $pageTitle = $options['pageTitle'] ?? null;

        if (!$this->urlChecker->isURLAuthorized($url)) {
            return $url;
        }
        $this->registrableDomain = $this->urlChecker->getRegistrableDomain($url); // hack
        if ($this->isSiteBlackListed()) {
            $this->log->debug('Site blacklisted : ' . $this->registrableDomain, ['stats' => 'externref.skip.blacklisted']);
            return $url;
        }

        if ($this->registrableDomain && !$this->validateConfigWebDomain($this->registrableDomain)) {
            $this->log->debug(
                'Domain blocked by config : ' . $this->registrableDomain,
                ['stats' => 'externref.skip.domainDisabledByConfig']
            );
            return $url;
        }

        $url = WikiTextUtil::normalizeUrlForTemplate($url);
        sleep(self::HTTP_REQUEST_LOOP_DELAY);
        $fetch = $this->fetchWithOptionalDirectRetry($url);
        if (!$fetch->isSuccess()) {
            return $this->externHttpErrorLogic->manageByFetchResult($fetch, $this->registrableDomain, $pageTitle, $summary);
        }

        $this->externalPage = (new ExternPageFactory($this->httpClient, $this->log))->fromFetchResult($url, $fetch, $this->domainParser);
        $pageData = $this->externalPage->getData();
        $this->log->debug('metaData', $pageData);

        if ($this->emptyPageData($pageData, $url)) {
            $this->log->debug('Empty page data', ['stats' => 'externref.skip.emptyPageData']);
            return $url;
        }

        if ($this->skipRobotNoIndex
            && $this->runGate(new RobotNoIndexValidator($pageData, $url, $this->log)) === LinkVerdict::KeepUrlAsIs
        ) {
            // TODO ? return {lien web| titre=Titre inconnu... |note=noindex }
            // http://www.nydailynews.com/entertainment/jessica-barth-details-alleged-harvey-weinstein-encounter-article-1.3557986
            return $url;
        }
        if ($this->runGate(new InterstitialPageValidator($pageData, $url, $fetch->body, $fetch->cfMitigated, $this->log)) === LinkVerdict::KeepUrlAsIs) {
            return $url;
        }
        $softFailureVerdict = $this->runGate(
            new SoftFailureDetector($fetch, $pageData['meta']['html-title'] ?? null, $this->log)
        );
        if ($softFailureVerdict === LinkVerdict::TreatAsDead) {
            return $this->deadLinkTransformer->formatFromUrl($url, summary: $summary);
        }

        $mappedData = $this->mapper->process($pageData); // only json-ld or only meta, after postprocess
        if ($this->emptyMapData($mappedData, $url)) {
            $this->log->stats->increment('externref.skip.emptyMapData');
            // TODO ? return {lien web| titre=Titre inconnu... site=prettydomain ...
            return $url;
        }
        $mappedData = $this->unsetAccesLibre($mappedData);

        $this->addSummaryLog($mappedData, $summary);
        $this->tagAndLog($mappedData);

        $template = $this->instanciateTemplate(
            $this->chooseTemplateNameByData($this->registrableDomain, $mappedData)
        );

        $mappedData = $this->replaceSomeData($mappedData, $template); // template specif + data + url

        $serialized = $this->optimizeAndSerialize($template, $mappedData);

        $normalized = Normalizer::normalize($serialized); // sometimes :bool
        $result = (!empty($normalized) && is_string($normalized)) ? $normalized : (!empty($serialized) ? $serialized : null);

        if ($result !== null) {
            if ($pageTitle !== null) {
                $this->linkCheckRepository->recordSuccess($pageTitle, $url); // clears any stale transient-error record
            }
            return $result;
        }

        return $url; // error fallback
    }

    protected function isSiteBlackListed(): bool
    {
        if ($this->registrableDomain && $this->skipSiteBlacklisted && in_array($this->registrableDomain, $this->skip_domain)) {
            $this->log->notice("Skip web site " . $this->registrableDomain);
            return true;
        }
        return false;
    }

    /**
     * todo move transformer
     */
    protected function validateConfigWebDomain(string $domain): bool
    {
        $this->logDebugConfigWebDomain($domain);

        // todo move to config
        $this->config[$domain] ??= [];
        $this->config[$domain] = is_array($this->config[$domain]) ? $this->config[$domain] : [];

        if ($this->config[$domain] === 'deactivated' || isset($this->config[$domain]['deactivated'])) {
            $this->log->info("Domain " . $domain . " disabled\n");

            return false;
        }

        return true;
    }

    protected function logDebugConfigWebDomain(string $domain): void
    {
        if (!isset($this->config[$domain])) {
            $this->log->debug("Domain " . $domain . " non configuré");
        } else {
            $this->log->debug("Domain " . $domain . " configuré");
        }
    }

    private function runGate(LinkGateInterface $gate): LinkVerdict
    {
        return $gate->check();
    }

    /**
     * Fetch via Tor (the normal path), then — only if directRetryClient was provided
     * AND the Tor fetch shows a blocking signal — retry once directly. Whichever
     * FetchResult is returned goes through the normal pipeline unchanged afterwards
     * (ExternHttpErrorLogic / InterstitialPageValidator still apply, so a retry that's
     * also blocked ends up handled exactly like a non-retried block would).
     *
     * Tor pass sends FAKE_USER_AGENT (a browser UA, blends in for anonymat) ; the
     * direct-retry pass deliberately does NOT override it, falling back to
     * ExternPageFactory::fetch()'s own default (USER_AGENT, the bot's honest identity)
     * — masking identity wouldn't help against the IP-reputation blocks this fallback
     * targets anyway, and self-identifying is the safer position on the bot's real IP.
     * See audits/synthese-anti-bot-crawling-tor-2026-08.md
     */
    private function fetchWithOptionalDirectRetry(string $url): FetchResult
    {
        $fetch = (new ExternPageFactory($this->httpClient, $this->log))->fetch($url, getenv('FAKE_USER_AGENT') ?: null);

        if ($this->directRetryClient === null || !$this->looksBlocked($fetch)) {
            return $fetch;
        }

        $this->log->notice('Blocked via Tor, retrying without Tor : ' . $url, ['stats' => 'externref.retry.directFallback']);
        $directFetch = (new ExternPageFactory($this->directRetryClient, $this->log))->fetch($url);

        if ($this->looksBlocked($directFetch)) {
            $this->log->notice('Still blocked without Tor : ' . $url, ['stats' => 'externref.retry.directFallbackFailed']);
        }

        return $directFetch;
    }

    /**
     * Cheap pre-check, deliberately not the full InterstitialPageValidator pass done
     * later in process() : title-based detection needs the parsed pageData that only
     * exists after this fetch is chosen, so it's skipped here (pageData: []). Header
     * (cf-mitigated) and body-marker detection need no parsing and are checked as-is.
     */
    private function looksBlocked(FetchResult $fetch): bool
    {
        if (in_array($fetch->httpStatus, self::BLOCK_SUSPECT_STATUSES, true)) {
            return true;
        }

        if (!$fetch->isSuccess()) {
            return false;
        }

        $precheck = new InterstitialPageValidator([], $fetch->requestedUrl, $fetch->body, $fetch->cfMitigated, $this->log);

        return $precheck->check() === LinkVerdict::KeepUrlAsIs;
    }

    // stay

    protected function emptyPageData(array $pageData, string $url): bool
    {
        if ($pageData === []
            || (empty($pageData['JSON-LD']) && empty($pageData['meta']))
        ) {
            $this->log->notice('No metadata : ' . $url);

            return true;
        }

        return false;
    }

    /**
     * check dataValide
     * Pas de skip domaine car s'agit peut-être d'un 404 ou erreur juste sur cette URL
     */
    protected function emptyMapData(array $mapData, string $url): bool
    {
        if ($mapData === [] || empty($mapData['url']) || empty($mapData['titre'])) {
            $this->log->info('Mapping incomplet : ' . $url);

            return true;
        }
        return false;
    }

    // stay

    /**
     * Pas de 'accès url=libre' # débat février 2021
     */
    protected function unsetAccesLibre(array $mapData): array
    {
        if (isset($mapData['accès url']) && $mapData['accès url'] === 'libre') {
            unset($mapData['accès url']);
        }
        return $mapData;
    }

    /**
     * todo Stay ?
     * todo refac lisible
     * @throws Exception
     */
    protected function chooseTemplateNameByData(?string $domain, array $mapData): string
    {
        if (!$domain) {
            return 'lien web';
        }
        $this->config[$domain]['template'] ??= [];
        $mapData['DATA-ARTICLE'] ??= false;

        if (!empty($mapData['doi'])) {
            $templateName = 'article';
        }

        if ($this->config[$domain]['template'] === 'article'
            || ($this->config[$domain]['template'] === 'auto' && $mapData['DATA-ARTICLE'])
            || ($mapData['DATA-ARTICLE'] && !empty($this->publisherData['newspaper'][$domain]))
            || $this->isScientificDomain()
        ) {
            $templateName = 'article';
        }
        if (!isset($templateName) || $this->config[$domain]['template'] === 'lien web') {
            $templateName = 'lien web';
        }

        // date obligatoire pour {article}
        if (!isset($mapData['date'])) {
            $templateName = 'lien web';
        }

        return $templateName;
    }

    protected function replaceSomeData(array $mapData, AbstractWikiTemplate $template): array
    {
        $mapData = $this->replaceSitenameByConfig($mapData, $template);
        $mapData = $this->fallbackIfSitenameNull($mapData, $template);
        $mapData = $this->correctSiteViaWebarchiver($mapData);

        $mapData = $this->replaceURLbyOriginal($mapData);

        if ($template instanceof ArticleTemplate) {
            unset($mapData['site']);
        }
        unset($mapData['DATA-TYPE']); // ugly
        unset($mapData['DATA-ARTICLE']); // ugly
        unset($mapData['url-access']);

        return $this->stripParamsNotSupportedByTemplate($mapData, $template);
    }

    /**
     * OpenGraphMapper/JsonLDMapper produce fields shared by {article} and {lien web} (e.g. 'volume', 'numéro'),
     * but a field absent from the chosen template would be serialized as an "user error" param
     * (HTML comment "PARAMETRE N'EXISTE PAS"), which is wrong since it's bot-generated data, not user input.
     */
    protected function stripParamsNotSupportedByTemplate(array $mapData, AbstractWikiTemplate $template): array
    {
        return array_intersect_key($mapData, array_flip($template->getParamsAndAlias()));
    }

    // postprocess data

    protected function fallbackIfSitenameNull(array $mapData, AbstractWikiTemplate $template): array
    {
        if (empty($mapData['site']) && $template instanceof LienWebTemplate) {
            try {
                $mapData['site'] = $this->externalPage->getPrettyDomainName();
            } catch (Throwable $e) {
                unset($e);
            }
        }
        return $mapData;
    }

    protected function correctSiteViaWebarchiver(array $mapData): array
    {
        if (!empty($this->options['originalRegistrableDomain']) && $mapData['site']) {
            $mapData['site'] = $this->options['originalRegistrableDomain'] . ' via ' . $mapData['site'];
        }

        return $mapData;
    }

    protected function replaceURLbyOriginal(array $mapData): array
    {
        $mapData['url'] = $this->url;

        return $mapData;
    }

    /**
     * @throws Exception
     */
    protected function optimizeAndSerialize(AbstractWikiTemplate $template, array $mapData): string
    {
        $template->hydrate($mapData);
        $optimizer = OptimizerFactory::fromTemplate($template);
        $optimizer->doTasks();
        $templateOptimized = $optimizer->getOptiTemplate();

        $serialized = $templateOptimized->serialize(true);
        $this->log->info('Serialized 444: ' . $serialized . "\n");
        return $serialized;
    }

    private function instanciateTemplate(string $templateName): AbstractWikiTemplate
    {
        $template = WikiTemplateFactory::create($templateName);
        $template->userSeparator = " |";
        $this->summary->memo['count ' . $templateName] = 1 + ($this->summary->memo['count ' . $templateName] ?? 0);

        return $template;
    }
}
