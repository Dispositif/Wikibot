<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Application\Http\ExternHttpClient;
use App\Domain\ExternDomains;
use App\Domain\ExternPage;
use App\Domain\ExternPageFactory;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptimizerFactory;
use App\Domain\Publisher\ExternMapper;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\WikiTemplateFactory;
use App\Infrastructure\Logger;
use Exception;
use Normalizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * todo move Domain
 * Class ExternRefTransformer
 *
 * @package App\Application
 */
class ExternRefTransformer implements TransformerInterface
{
    const HTTP_REQUEST_LOOP_DELAY = 10;

    const LOG_REQUEST_ERROR = __DIR__.'/resources/external_request_error.log';
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
     * @var string|string[]
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
     * ExternalRefTransformer constructor.
     *
     * @param LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;

        // todo REFAC DataObject[]
        $this->config = Yaml::parseFile(__DIR__.'/resources/config_presse.yaml');
        $skipFromFile = file(
            __DIR__.'/resources/config_skip_domain.txt',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );
        $this->skip_domain = ($skipFromFile) ? $skipFromFile : [];

        $this->data['newspaper'] = json_decode(file_get_contents(__DIR__.'/resources/data_newspapers.json'), true);
        $this->data['scientific domain'] = json_decode(
            file_get_contents(__DIR__.'/resources/data_scientific_domain.json'),
            true
        );
        $this->data['scientific wiki'] = json_decode(
            file_get_contents(__DIR__.'/resources/data_scientific_wiki.json'),
            true
        );

        $this->mapper = new ExternMapper(new Logger());
    }

    /**
     * @param string $url
     *
     * @return string
     * @throws Exception
     */
    public function process(string $url): string
    {
        if (!$this->isURLAuthorized($url)) {
            return $url;
        }
        try {
            sleep(self::HTTP_REQUEST_LOOP_DELAY);
            $this->externalPage = ExternPageFactory::fromURL($url, $this->log);
            $pageData = $this->externalPage->getData();
            $this->log->debug('metaData', $this->externalPage->getData());
        } catch (Exception $e) {
            // "410 gone" => {lien brisé}
            if (preg_match('#410 Gone#i', $e->getMessage())) {
                $this->log->notice('410 page disparue : '.$url);

                return sprintf(
                    '{{Lien brisé |url= %s |titre=%s |brisé le=%s}}',
                    $url,
                    $this->url2TextStyleTitle($url),
                    date('d-m-Y')
                );
            } // 403
            elseif (preg_match('#403 Forbidden#i', $e->getMessage())) {
                $this->log->warning('403 Forbidden : '.$url);
                file_put_contents(self::LOG_REQUEST_ERROR, '403 Forbidden : '.$this->domain."\n", FILE_APPEND);
            } elseif (preg_match('#404 Not Found#i', $e->getMessage())) {
                $this->log->notice('404 Not Found : '.$url);

                return $url;
            } else {
                //  autre : ne pas générer de {lien brisé}, car peut-être 404 temporaire
                $this->log->warning('erreur sur extractWebData '.$e->getMessage());
                //file_put_contents(self::LOG_REQUEST_ERROR, $this->domain."\n", FILE_APPEND);

                return $url;
            }
        }

        if (empty($pageData)
            || (empty($pageData['JSON-LD']) && empty($pageData['meta']))
        ) {
            // site avec HTML pourri
            return $url;
        }

        if (isset($pageData['robots']) && strpos($pageData['robots'], 'noindex') !== false) {
            $this->log->notice('SKIP robots: noindex');

            return $url;
        }

        $mapData = $this->mapper->process($pageData);

        // check dataValide
        // Pas de skip domaine car s'agit peut-être d'un 404 ou erreur juste sur cette URL
        if (empty($mapData) || empty($mapData['url']) || empty($mapData['titre'])) {
            $this->log->info('Mapping incomplet');

            return $url;
        }

        $this->tagAndLog($mapData);
        $this->addSummaryLog($mapData);

        $template = $this->chooseTemplateByData($mapData);

        $mapData = $this->replaceSitenameByConfig($mapData, $template);
        $mapData = $this->replaceURLbyOriginal($mapData);


        if ($template instanceof ArticleTemplate) {
            unset($mapData['site']);
        }
        unset($mapData['DATA-TYPE']); // ugly
        unset($mapData['DATA-ARTICLE']); // ugly

        $template->hydrate($mapData);

        $optimizer = OptimizerFactory::fromTemplate($template);
        $optimizer->doTasks();
        $templateOptimized = $optimizer->getOptiTemplate();

        $serialized = $templateOptimized->serialize(true);
        $this->log->info($serialized."\n");

        return Normalizer::normalize($serialized);
    }

    /**
     * @param string $url
     *
     * @return bool
     * @throws Exception
     */
    protected function isURLAuthorized(string $url): bool
    {
        if (!ExternHttpClient::isWebURL($url)) {
            $this->log->debug('Skip : not an URL : '.$url);

            return false;
        }

        if ($this->hasForbiddenFilenameExtension($url)) {
            return false;
        }

        $this->url = $url;
        $this->domain = ExternDomains::extractSubDomain($this->url);

        if (in_array($this->domain, $this->skip_domain)) {
            $this->log->notice("Skip domain ".$this->domain);

            return false;
        }

        if (!isset($this->config[$this->domain])) {
            $this->log->debug("Domain ".$this->domain." non configuré");
            if ($this->skipUnauthorised) {
                return false;
            }
        } else {
            $this->log->debug("Domain ".$this->domain." configuré");
        }

        $this->config[$this->domain] = $this->config[$this->domain] ?? [];
        $this->config[$this->domain] = is_array($this->config[$this->domain]) ? $this->config[$this->domain] : [];

        if ($this->config[$this->domain] === 'deactivated' || isset($this->config[$this->domain]['deactivated'])) {
            $this->log->info("Domain ".$this->domain." desactivé\n");

            return false;
        }

        return true;
    }

    private function tagAndLog(array $mapData)
    {
        $this->log->debug('mapData', $mapData);

        if (isset($mapData['DATA-ARTICLE']) && $mapData['DATA-ARTICLE']) {
            $this->log->notice("Article OK");
        }
        if (isset($this->data['newspaper'][$this->domain])) {
            $this->log->notice('PRESSE');
        }
        if ($this->isScientificDomain()) {
            $this->log->notice('SCIENCE');
        }
    }

    private function isScientificDomain(): bool
    {
        if (isset($this->data['scientific domain'][$this->domain])) {
            return true;
        }
        if (strpos('.revues.org', $this->domain) > 0) {
            return true;
        }

        return false;
    }

    private function addSummaryLog(array $mapData)
    {
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
     * todo move ?
     * URL => "parismatch.com/People/bla…"
     *
     * @param string $url
     *
     * @return string
     */
    public function url2TextStyleTitle(string $url): string
    {
        $text = str_replace(['https://', 'http://', 'www.'], '', $url);
        if (strlen($text) > 30) {
            $text = substr($text, 0, 30).'…';
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
        if (preg_match(
            '#\.(pdf|jpg|jpeg|gif|png|xls|xlsx|xlr|xml|xlt|xlsx|txt|csv|js|docx|exe|gz|zip|ini|movie|mp3|mp4|ogg|raw|rss|tar|tgz|wma)$#i',
            $url
        )
        ) {
            return true;
        }

        return false;
    }

}
