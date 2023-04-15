<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Application\Http\ExternHttpClient;
use App\Domain\ExternPage;
use App\Domain\ExternPageFactory;
use App\Domain\Models\Summary;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptimizerFactory;
use App\Domain\Publisher\ExternMapper;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\WikiTemplateFactory;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\Logger;
use Exception;
use Normalizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * todo move Domain ?
 */
class ExternRefTransformer implements TransformerInterface
{
    public const HTTP_REQUEST_LOOP_DELAY = 10;
    public const LOG_REQUEST_ERROR = __DIR__ . '/resources/external_request_error.log';
    public const SKIP_DOMAIN_FILENAME = __DIR__ . '/resources/config_skip_domain.txt';
    public const REPLACE_404 = true;

    public $skipUnauthorised = true;
    /**
     * @var array
     */
    public $summaryLog = [];
    /**
     * @var LoggerInterface
     */
    protected $log;
    private $config;
    /**
     * @var string
     */
    private $domain;
    /**
     * @var string
     */
    private $url;
    /**
     * @var ExternMapper
     */
    private $mapper;
    /**
     * @var array
     */
    private $data = [];
    /**
     * @var array
     */
    private $skip_domain;
    /**
     * @var ExternPage
     */
    private $externalPage;
    /**
     * @var Summary|null
     */
    private $summary;

    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;

        $this->importConfigAndData();

        $this->mapper = new ExternMapper(new Logger());
    }

    /**
     * @throws Exception
     */
    public function process(string $url, Summary $summary): string
    {
        if (!$this->isURLAuthorized($url)) {
            return $url;
        }
        try {
            $url = WikiTextUtil::normalizeUrlForTemplate($url);
            $pageData = $this->extractPageDataFromUrl($url);
        } catch (Exception $exception) {
            return $this->manageHttpErrors($exception, $url);
        }
        if ($this->emptyPageData($pageData, $url) || $this->robotNoIndex($pageData, $url)) {
            return $url;
        }

        $mapData = $this->mapper->process($pageData);
        if ($this->emptyMapData($mapData, $url)) {
            return $url;
        }
        $mapData = $this->unsetAccesLibre($mapData);

        $this->addSummaryLog($mapData, $summary);
        $this->tagAndLog($mapData);

        $template = $this->chooseTemplateByData($mapData);

        $mapData = $this->replaceSomeData($mapData, $template);
        $serialized = $this->optimizeAndSerialize($template, $mapData);
        $normalized = Normalizer::normalize($serialized); // sometimes :bool
        if (!empty($normalized) && is_string($normalized)) {
            return $normalized;
        }
        if (!empty($serialized)) {
            return $serialized;
        }

        return $url; // error fallback
    }

    protected function isURLAuthorized(string $url): bool
    {
        $this->url = $url;
        if (!ExternHttpClient::isHttpURL($url)) {
            $this->log->debug('Skip : not a valid URL : ' . $url);
            return false;
        }

        if ($this->hasForbiddenFilenameExtension($url)) {
            return false;
        }
        if (!ExternHttpClient::isHttpURL($url)) {
            throw new Exception('string is not an URL ' . $url);
        }
        try {
            $this->domain = InternetDomainParser::getRegistrableDomainFromURL($url);
        } catch (Exception $e) {
            $this->log->warning('Skip : not a valid URL : ' . $url);
            return false;
        }

        return $this->validateConfigWebDomain();
    }

    /**
     * @param array $mapData
     *
     * @throws Exception
     */
    private function tagAndLog(array $mapData)
    {
        $this->log->debug('mapData', $mapData);
        $this->summary->citationNumber = $this->summary->citationNumber ?? 0;
        $this->summary->citationNumber++;

        if (isset($mapData['DATA-ARTICLE']) && $mapData['DATA-ARTICLE']) {
            $this->log->notice("Article OK");
        }
        if (isset($this->data['newspaper'][$this->domain])) {
            $this->log->notice('PRESSE');
            $this->summary->memo['presse'] = true;
        }
        if ($this->isScientificDomain()) {
            $this->log->notice('SCIENCE');
            $this->summary->memo['science'] = true;
        }
        if (!isset($this->summary->memo['sites'])
            || !in_array($this->externalPage->getPrettyDomainName(), $this->summary->memo['sites'])
        ) {
            $this->summary->memo['sites'][] = $this->externalPage->getPrettyDomainName();
        }
        if (isset($mapData['accès url'])) {
            $this->log->notice('accès 🔒 ' . $mapData['accès url']);
            if ($mapData['accès url'] !== 'libre') {
                $this->summary->memo['accès url non libre'] = true;
            }
        }
    }

    private function isScientificDomain(): bool
    {
        if (isset($this->data['scientific domain'][$this->domain])) {
            return true;
        }
        return strpos('.revues.org', $this->domain) > 0;
    }

    private function addSummaryLog(array $mapData, Summary $summary)
    {
        $this->summary = $summary;
        $this->summaryLog[] = $mapData['site'] ?? $mapData['périodique'] ?? '?';
    }

    /**
     * todo refac lisible
     *
     * @param array $mapData
     *
     * @return AbstractWikiTemplate
     * @throws Exception
     */
    private function chooseTemplateByData(array $mapData): AbstractWikiTemplate
    {
        // Logique : choix template
        $this->config[$this->domain]['template'] = $this->config[$this->domain]['template'] ?? [];
        $mapData['DATA-ARTICLE'] = $mapData['DATA-ARTICLE'] ?? false;

        if (!empty($mapData['doi'])) {
            $templateName = 'article';
        }

        if ($this->config[$this->domain]['template'] === 'article'
            || ($this->config[$this->domain]['template'] === 'auto' && $mapData['DATA-ARTICLE'])
            || ($mapData['DATA-ARTICLE'] && !empty($this->data['newspaper'][$this->domain]))
            || $this->isScientificDomain()
        ) {
            $templateName = 'article';
        }
        if (!isset($templateName) || $this->config[$this->domain]['template'] === 'lien web') {
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

    /**
     * Logique : remplacement titre périodique ou nom du site
     *
     * @param array $mapData
     * @param       $template
     *
     * @return array
     */
    private function replaceSitenameByConfig(array $mapData, $template): array
    {
        // from wikidata URL of newspapers
        if (!empty($this->data['newspaper'][$this->domain])) {
            $frwiki = $this->data['newspaper'][$this->domain]['frwiki'];
            $label = $this->data['newspaper'][$this->domain]['fr'];
            if (isset($mapData['site']) || $template instanceof LienWebTemplate) {
                $mapData['site'] = WikiTextUtil::wikilink($label, $frwiki);
            }
            if (isset($mapData['périodique']) || $template instanceof ArticleTemplate) {
                $mapData['périodique'] = WikiTextUtil::wikilink($label, $frwiki);
            }
        }

        // from wikidata of scientific journals
        if (isset($mapData['périodique']) && isset($this->data['scientific wiki'][$mapData['périodique']])) {
            $mapData['périodique'] = WikiTextUtil::wikilink(
                $mapData['périodique'],
                $this->data['scientific wiki'][$mapData['périodique']]
            );
        }

        // from YAML config
        if (!empty($this->config[$this->domain]['site']) && $template instanceof LienWebTemplate) {
            $mapData['site'] = $this->config[$this->domain]['site'];
        }
        if (!empty($this->config[$this->domain]['périodique'])
            && (!empty($mapData['périodique'])
                || $template instanceof OuvrageTemplate)
        ) {
            $mapData['périodique'] = $this->config[$this->domain]['périodique'];
        }

        // from logic
        if (empty($mapData['site']) && $template instanceof LienWebTemplate) {
            try {
                $mapData['site'] = $this->externalPage->getPrettyDomainName();
            } catch (Throwable $e) {
                unset($e);
            }
        }

        return $mapData;
    }

    private function replaceURLbyOriginal(array $mapData): array
    {
        $mapData['url'] = $this->url;

        return $mapData;
    }

    /**
     * todo move + prettyDomainName
     * URL => "parismatch.com/People/bla…"
     */
    public function generateTitleFromURLText(string $url): string
    {
        $text = str_replace(['https://', 'http://', 'www.'], '', $url);
        if (strlen($text) > 30) {
            $text = substr($text, 0, 30) . '…';
        }

        return $text;
    }

    /**
     * Skip PDF GIF etc
     * https://fr.wikipedia.org/wiki/Liste_d%27extensions_de_fichiers
     *
     * @param string $url
     *
     * @return bool
     */
    private function hasForbiddenFilenameExtension(string $url): bool
    {
        return (bool)preg_match(
            '#\.(pdf|jpg|jpeg|gif|png|xls|xlsx|xlr|xml|xlt|txt|csv|js|docx|exe|gz|zip|ini|movie|mp3|mp4|ogg|raw|rss|tar|tgz|wma)$#i',
            $url
        );
    }

    // todo inject
    protected function importConfigAndData(): void
    {
        // todo REFAC DataObject[]
        $this->config = Yaml::parseFile(__DIR__ . '/resources/config_presse.yaml');
        $skipFromFile = file(
            self::SKIP_DOMAIN_FILENAME,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );
        $this->skip_domain = $skipFromFile ?: [];

        $this->data['newspaper'] = json_decode(file_get_contents(__DIR__ . '/resources/data_newspapers.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->data['scientific domain'] = json_decode(
            file_get_contents(__DIR__ . '/resources/data_scientific_domain.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->data['scientific wiki'] = json_decode(
            file_get_contents(__DIR__ . '/resources/data_scientific_wiki.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    protected function extractPageDataFromUrl(string $url): array
    {
        sleep(self::HTTP_REQUEST_LOOP_DELAY);
        $this->externalPage = ExternPageFactory::fromURL($url, $this->log);
        $pageData = $this->externalPage->getData();
        $this->log->debug('metaData', $pageData);

        return $pageData;
    }

    protected function formatLienBrise(string $url): string
    {
        return sprintf(
            '{{Lien brisé |url= %s |titre=%s |brisé le=%s}}',
            $url,
            $this->generateTitleFromURLText($url),
            date('d-m-Y')
        );
    }

    protected function log403(string $url): void
    {
        $this->log->warning('403 Forbidden : ' . $url);
        file_put_contents(self::LOG_REQUEST_ERROR, '403 Forbidden : ' . $this->domain . "\n", FILE_APPEND);
    }

    protected function manageHttpErrors(Exception $e, string $url): string
    {
        // "410 gone" => {lien brisé}
        if (preg_match('#410 Gone#i', $e->getMessage())) {
            $this->log->notice('410 Gone');

            return $this->formatLienBrise($url);
        } // 403
        elseif (preg_match('#403 Forbidden#i', $e->getMessage())) {
            $this->log403($url);

            return $url;
        } elseif (preg_match('#404 Not Found#i', $e->getMessage())) {
            $this->log->notice('404 Not Found');

            if (self::REPLACE_404) {
                return $this->formatLienBrise($url);
            }
            return $url;
        }elseif (preg_match('#401 Unauthorized#i', $e->getMessage())) {
            $this->log->notice('401 Unauthorized : skip ' . $url);

            return $url;
        }else {
            //  autre : ne pas générer de {lien brisé}, car peut-être 404 temporaire
            $this->log->warning('erreur sur extractWebData ' . $e->getMessage());

            //file_put_contents(self::LOG_REQUEST_ERROR, $this->domain."\n", FILE_APPEND);

            return $url;
        }
    }

    private function emptyPageData(array $pageData, string $url): bool
    {
        if (empty($pageData)
            || (empty($pageData['JSON-LD']) && empty($pageData['meta']))
        ) {
            $this->log->notice('SKIP no metadata : ' . $url);

            return true;
        }

        return false;
    }

    private function robotNoIndex(array $pageData, string $url): bool
    {
        if (isset($pageData['robots']) && strpos($pageData['robots'], 'noindex') !== false) {
            $this->log->notice('SKIP robots: noindex : ' . $url);

            return true;
        }
        return false;
    }

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
     * check dataValide
     * Pas de skip domaine car s'agit peut-être d'un 404 ou erreur juste sur cette URL
     */
    private function emptyMapData(array $mapData, string $url): bool
    {
        if ($mapData === [] || empty($mapData['url']) || empty($mapData['titre'])) {
            $this->log->info('Mapping incomplet : ' . $url);

            return true;
        }
        return false;
    }

    protected function replaceSomeData(array $mapData, AbstractWikiTemplate $template): array
    {
        $mapData = $this->replaceSitenameByConfig($mapData, $template);
        $mapData = $this->replaceURLbyOriginal($mapData);

        if ($template instanceof ArticleTemplate) {
            unset($mapData['site']);
        }
        unset($mapData['DATA-TYPE']); // ugly
        unset($mapData['DATA-ARTICLE']); // ugly
        unset($mapData['url-access']);

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

    /**
     * @return bool
     */
    protected function validateConfigWebDomain(): bool
    {
        if ($this->isSiteBlackListed()) {
            return false;
        }
        $this->logDebugConfigWebDomain();

        $this->config[$this->domain] = $this->config[$this->domain] ?? [];
        $this->config[$this->domain] = is_array($this->config[$this->domain]) ? $this->config[$this->domain] : [];

        if ($this->config[$this->domain] === 'deactivated' || isset($this->config[$this->domain]['deactivated'])) {
            $this->log->info("Domain " . $this->domain . " disabled\n");

            return false;
        }

        return true;
    }

    /**
     * @return void
     */
    protected function logDebugConfigWebDomain(): void
    {
        if (!isset($this->config[$this->domain])) {
            $this->log->debug("Domain " . $this->domain . " non configuré");
        } else {
            $this->log->debug("Domain " . $this->domain . " configuré");
        }
    }

    protected function isSiteBlackListed(): bool
    {
        if ($this->skipUnauthorised && in_array($this->domain, $this->skip_domain)) {
            $this->log->notice("Skip web site " . $this->domain);
            return true;
        }
        return false;
    }
}
