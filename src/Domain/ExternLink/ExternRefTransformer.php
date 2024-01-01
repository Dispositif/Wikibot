<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\ExternLink\Validators\RobotNoIndexValidator;
use App\Domain\InfrastructurePorts\DeadlinkArchiverInterface;
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

    /**
     * @param DeadlinkArchiverInterface[] $deadlinkArchivers
     */
    public function __construct(
        protected ExternMapper                  $mapper,
        protected HttpClientInterface           $httpClient,
        protected InternetDomainParserInterface $domainParser,
        protected LoggerInterface               $log = new NullLogger(),
        protected array                         $deadlinkArchivers = []
    )
    {
        $this->importConfigAndData();
        $this->externHttpErrorLogic = new ExternHttpErrorLogic(
            new DeadLinkTransformer($deadlinkArchivers, $domainParser, null, $log),
            $log
        );
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
        $this->options = $options; // used only to pass RegistrableDomain of archived deadlink

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

        try {
            $url = WikiTextUtil::normalizeUrlForTemplate($url);
            $pageData = $this->extractPageDataFromUrl($url); // ['JSON-LD'] & ['meta'] !!
        } catch (Exception $exception) {
            return $this->externHttpErrorLogic->manageByHttpErrorMessage($exception->getMessage(), $url);
        }
        if ($this->emptyPageData($pageData, $url)) {
            $this->log->debug('Empty page data', ['stats' => 'externref.skip.emptyPageData']);
            return $url;
        }
        $noIndexValidator = new RobotNoIndexValidator($pageData, $url, $this->log); // todo inject
        if ($noIndexValidator->validate() && $this->skipRobotNoIndex) {
            $this->log->debug('NOINDEX detected', ['stats' => 'externref.skip.robotNoIndex']);
            // TODO ? return {lien web| titre=Titre inconnu... |note=noindex }
            // http://www.nydailynews.com/entertainment/jessica-barth-details-alleged-harvey-weinstein-encounter-article-1.3557986
            return $url;
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

    /**
     * Stay
     * @throws Exception
     */
    protected function extractPageDataFromUrl(string $url): array
    {
        sleep(self::HTTP_REQUEST_LOOP_DELAY);
        $externPageFactory = new ExternPageFactory($this->httpClient, $this->log);
        $this->externalPage = $externPageFactory->fromURL($url, $this->domainParser);
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
