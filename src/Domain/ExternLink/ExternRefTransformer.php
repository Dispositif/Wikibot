<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\Models\Summary;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\OptimizerFactory;
use App\Domain\Publisher\ExternMapper;
use App\Domain\ServiceInterfaces\ExternHttpClientInterface;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\WikiTemplateFactory;
use Exception;
use Normalizer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * TODO refac too big (responsibility)
 */
class ExternRefTransformer implements ExternRefTransformerInterface
{
    use SummaryExternTrait, RobotsRulesTrait, PublisherLogicTrait;

    public const HTTP_REQUEST_LOOP_DELAY = 10;
    public const SKIP_DOMAIN_FILENAME = __DIR__ . '/../resources/config_skip_domain.txt';
    public const REPLACE_404 = true;
    public const CONFIG_PRESSE = __DIR__ . '/../resources/config_presse.yaml';
    public const CONFIG_NEWSPAPER_JSON = __DIR__ . '/../resources/data_newspapers.json';
    public const CONFIG_SCIENTIFIC_JSON = __DIR__ . '/../resources/data_scientific_domain.json';
    public const CONFIG_SCIENTIFIC_WIKI_JSON = __DIR__ . '/../resources/data_scientific_wiki.json';

    public $skipSiteBlacklisted = true;
    public $skipRobotNoIndex = true;
    /**
     * @var array
     */
    public $summaryLog = [];
    /**
     * @var LoggerInterface
     */
    protected $log;
    protected $config;
    /**
     * @var string
     */
    protected $registrableDomain;
    /**
     * @var string
     */
    protected $url;
    /**
     * @var ExternMapper
     */
    protected $mapper;
    /**
     * @var array
     */
    protected $publisherData = [];
    /**
     * @var array
     */
    protected $skip_domain;
    /**
     * @var ExternPage
     */
    protected $externalPage;
    /**
     * @var Summary|null
     */
    protected $summary;
    /**
     * @var ExternHttpClientInterface
     */
    protected $httpClient;
    private $externHttpErrorLogic;
    /**
     * @var CheckURL
     */
    private $urlChecker;

    public function __construct(ExternMapper $externMapper, ExternHttpClientInterface $httpClient, ?LoggerInterface $logger)
    {
        $this->log = $logger ?? new NullLogger();
        $this->importConfigAndData();
        $this->mapper = $externMapper;
        $this->httpClient = $httpClient;
        $this->externHttpErrorLogic = new ExternHttpErrorLogic($this->log);
        $this->urlChecker = new CheckURL($logger);
    }

    /**
     * TODO Refac : chain of responsibility or composite pattern
     * @throws Exception
     */
    public function process(string $url, Summary $summary): string
    {
        $this->url = $url;
        if (!$this->urlChecker->isURLAuthorized($url)) {
            return $url;
        }
        $this->registrableDomain = $this->urlChecker->getRegistrableDomain($url); // hack
        if ($this->isSiteBlackListed()) {
            return $url;
        }

        if (!$this->validateConfigWebDomain($this->registrableDomain)) {
            return $url;
        }

        try {
            $url = WikiTextUtil::normalizeUrlForTemplate($url);
            $pageData = $this->extractPageDataFromUrl($url); // ['JSON-LD'] & ['meta'] !!
        } catch (Exception $exception) {
            return $this->externHttpErrorLogic->manageHttpErrors($exception->getMessage(), $url);
        }
        if ($this->emptyPageData($pageData, $url)) {
            return $url;
        }
        if ($this->isRobotNoIndex($pageData, $url) && $this->skipRobotNoIndex) {
            // TODO ? return {lien web| titre=Titre inconnu...
            // http://www.nydailynews.com/entertainment/jessica-barth-details-alleged-harvey-weinstein-encounter-article-1.3557986
            return $url;
        }

        $mappedData = $this->mapper->process($pageData); // only json-ld or only meta, after postprocess
        if ($this->emptyMapData($mappedData, $url)) {
            // TODO ? return {lien web| titre=Titre inconnu... site=prettydomain ...
            return $url;
        }
        $mappedData = $this->unsetAccesLibre($mappedData);

        $this->addSummaryLog($mappedData, $summary);
        $this->tagAndLog($mappedData);

        $template = $this->chooseTemplateByData($this->registrableDomain, $mappedData);

        $mappedData = $this->replaceSomeData($mappedData, $template); // template specif + data + url
        $serialized = $this->optimizeAndSerialize($template, $mappedData);

        $normalized = Normalizer::normalize($serialized); // sometimes :bool
        if (!empty($normalized) && is_string($normalized)) {
            return $normalized;
        }
        if (!empty($serialized)) {
            return $serialized;
        }

        return $url; // error fallback
    }

    protected function isSiteBlackListed(): bool
    {
        if ($this->skipSiteBlacklisted && in_array($this->registrableDomain, $this->skip_domain)) {
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
        $this->config[$domain] = $this->config[$domain] ?? [];
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

    /**
     * Stay
     * @throws Exception
     */
    protected function extractPageDataFromUrl(string $url): array
    {
        sleep(self::HTTP_REQUEST_LOOP_DELAY);
        $this->externalPage = ExternPageFactory::fromURL($url, $this->httpClient, $this->log);
        $pageData = $this->externalPage->getData();
        $this->log->debug('metaData', $pageData);

        return $pageData;
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
    protected function chooseTemplateByData(string $domain, array $mapData): AbstractWikiTemplate
    {
        // Logique : choix template
        $this->config[$domain]['template'] = $this->config[$domain]['template'] ?? [];
        $mapData['DATA-ARTICLE'] = $mapData['DATA-ARTICLE'] ?? false;

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

        $template = WikiTemplateFactory::create($templateName);
        $template->userSeparator = " |";
        $this->summary->memo['count ' . $templateName] = 1 + ($this->summary->memo['count ' . $templateName] ?? 0);

        return $template;
    }

    protected function replaceSomeData(array $mapData, AbstractWikiTemplate $template): array
    {
        $mapData = $this->replaceSitenameByConfig($mapData, $template);
        $mapData = $this->fallbackIfSitenameNull($mapData, $template);

        $mapData = $this->replaceURLbyOriginal($mapData);

        if ($template instanceof ArticleTemplate) {
            unset($mapData['site']);
        }
        unset($mapData['DATA-TYPE']); // ugly
        unset($mapData['DATA-ARTICLE']); // ugly
        unset($mapData['url-access']);

        return $mapData;
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

    protected function replaceURLbyOriginal(array $mapData): array
    {
        $mapData['url'] = $this->url;

        return $mapData;
    }

    /**
     * @param AbstractWikiTemplate $template
     * @param array $mapData
     *
     * @return string
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
}
