<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Publisher\WebMapper;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\WikiTemplateFactory;
use App\Infrastructure\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Class RefWebTransformer
 *
 * @package App\Application
 */
class RefWebTransformer implements TransformerInterface
{

    public $skipUnauthorised = true;
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
     * @var WebMapper
     */
    private $mapper;
    /**
     * @var array
     */
    private $data = [];
    /**
     * @var array
     */
    public $summaryLog = [];

    /**
     * RefWebTransformer constructor.
     *
     * @param LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;

        $this->config = Yaml::parseFile(__DIR__.'/resources/config_presse.yaml');
        $this->data['newspaper'] = json_decode(file_get_contents(__DIR__.'/resources/data_newspapers.json'), true);
        $this->data['scientific domain'] = json_decode(
            file_get_contents(__DIR__.'/resources/data_scientific_domain.json'),
            true
        );
        $this->data['scientific wiki'] = json_decode(
            file_get_contents(__DIR__.'/resources/data_scientific_wiki.json'),
            true
        );

        $this->mapper = new WebMapper(new Logger());
    }

    public function process(string $string)
    {
        if (!$this->isTransformAutorized($string)) {
            return $string;
        }

        $publish = new PublisherAction($this->url);
        try {
            sleep(3);
            $html = $publish->getHTMLSource();
            $htmlData = $publish->extractWebData($html);
            $this->log->debug('htmlData', $htmlData);
        } catch (Throwable $e) {
            // ne pas générer de lien brisé !!
            $this->log->notice('erreur sur extractWebData');

            return $string;
        }

        $mapData = $this->mapper->process($htmlData);

        // check dataValide
        if (empty($mapData) || empty($mapData['url']) || empty($mapData['titre'])) {
            $this->log->info('Mapping incomplet');

            return $string;
        }

        $this->tagAndLog($mapData);
        $this->addSummaryLog($mapData);

        $template = $this->chooseTemplateByData($mapData);

        $mapData = $this->replaceSitenameByConfig($mapData, $template);

        $template->hydrate($mapData, true);

        $serialized = $template->serialize(true);
        $this->log->info($serialized."\n");

        return $serialized;
    }

    protected function isTransformAutorized(string $string): bool
    {
        if (!preg_match('#^http?s://[^ ]+$#i', $string)) {
            return false;
        }
        $this->url = $string;
        $parseURL = parse_url($this->url);
        $this->domain = str_replace('www.', '', $parseURL['host']);

        if (!isset($this->config[$this->domain])) {
            $this->log->info("Domain ".$this->domain." non configuré\n");
            if ($this->skipUnauthorised) {
                return false;
            }
            echo "> Domaine ".Color::LIGHT_RED.$this->domain.Color::NORMAL." non configuré\n";
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

        return $mapData;
    }

}
