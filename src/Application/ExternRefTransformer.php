<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\ExternDomains;
use App\Domain\ExternPageFactory;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Publisher\ExternMapper;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\WikiTemplateFactory;
use App\Infrastructure\Logger;
use Codedungeon\PHPCliColors\Color;
use Normalizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * todo move Domain
 * Class ExternRefTransformer
 *
 * @package App\Application
 */
class ExternRefTransformer implements TransformerInterface
{

    const SKIPPED_FILE_LOG  = __DIR__.'/resources/external_skipped.log';
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
    private $skip_domain = [];
    /**
     * @var \App\Domain\ExternPage
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
        $skipFromFile = file(__DIR__.'/resources/config_skip_domain.txt');
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
     * @param string $string
     *
     * @return string
     * @throws \Exception
     */
    public function process(string $string): string
    {
        if (!$this->isURLAutorized($string)) {
            return $string;
        }
        try {
            sleep(5);
            $this->externalPage = ExternPageFactory::fromURL($string, $this->log);
            $pageData = $this->externalPage->getData();
            $this->log->debug('metaData', $this->externalPage->getData());
        } catch (\Exception $e) {
            // ne pas générer de {lien brisé}, car peut-être 404 temporaire
            $this->log->notice('erreur sur extractWebData '.$e->getMessage());
            file_put_contents(self::LOG_REQUEST_ERROR, $this->domain);
        }

        if (empty($pageData)
            || (empty($pageData['JSON-LD']) && empty($pageData['meta']))
        ) {
            // site avec HTML pourri
            return $string;
        }

        if (isset($pageData['robots']) && strpos($pageData['robots'], 'noindex') !== false) {
            $this->log->notice('SKIP robots: noindex');

            return $string;
        }

        $mapData = $this->mapper->process($pageData);

        // check dataValide
        if (empty($mapData) || empty($mapData['url']) || empty($mapData['titre'])) {
            $this->skip_domain[] = $this->domain;
            $this->log->info('Mapping incomplet');
            // Todo : temp data
            try {
                file_put_contents(self::SKIPPED_FILE_LOG, $this->domain.",".$this->url."\n", FILE_APPEND);
            } catch (\Throwable $e) {
                unset($e);
            }

            return $string;
        }

        $this->tagAndLog($mapData);
        $this->addSummaryLog($mapData);

        $template = $this->chooseTemplateByData($mapData);

        $mapData = $this->replaceSitenameByConfig($mapData, $template);
        $mapData = $this->replaceURLbyOriginal($mapData);

        $template->hydrate($mapData);

        $serialized = $template->serialize(true);
        $this->log->info($serialized."\n");

        return Normalizer::normalize($serialized);
    }

    /**
     * @param string $string
     *
     * @return bool
     * @throws \Exception
     */
    protected function isURLAutorized(string $string): bool
    {
        if (!preg_match('#^http?s://[^ ]+$#i', $string)) {
            return false;
        }

        $this->url = $string;
        $this->domain = ExternDomains::extractSubDomain($this->url);

        if (in_array($this->domain, $this->skip_domain)) {
            return false;
        }

        if (!isset($this->config[$this->domain])) {
            $this->log->info("Domain ".$this->domain." non configuré\n");
            if ($this->skipUnauthorised) {
                return false;
            }
        } else {
            echo "> Domaine ".Color::LIGHT_GREEN.$this->domain.Color::NORMAL." configuré\n";
        }

        $this->config[$this->domain] = $this->config[$this->domain] ?? [];
        $this->config[$this->domain] = is_array($this->config[$this->domain]) ? $this->config[$this->domain] : [];

        if ($this->config[$this->domain] === 'desactived' || isset($this->config[$this->domain]['desactived'])) {
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
     */
    private function chooseTemplateByData(array $mapData): AbstractWikiTemplate
    {
        // Logique : choix template
        $this->config[$this->domain]['template'] = $this->config[$this->domain]['template'] ?? [];
        $mapData['DATA-ARTICLE'] = $mapData['DATA-ARTICLE'] ?? false;

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
            } catch (\Throwable $e) {
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

}
