<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\WikiTextUtil;
use Symfony\Component\Yaml\Yaml;

trait PublisherLogicTrait
{
    /**
     * todo extract Infra getcont form file + inject
     */
    protected function importConfigAndData(): void
    {
        // todo REFAC DataObject[] (WTF?)
        $this->config = Yaml::parseFile(self::CONFIG_PRESSE);
        $skipFromFile = file(
            self::SKIP_DOMAIN_FILENAME,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );
        $this->skip_domain = $skipFromFile ?: [];

        $this->publisherData['newspaper'] = json_decode(file_get_contents(self::CONFIG_NEWSPAPER_JSON), true, 512, JSON_THROW_ON_ERROR);
        $this->publisherData['scientific domain'] = json_decode(
            file_get_contents(self::CONFIG_SCIENTIFIC_JSON),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->publisherData['scientific wiki'] = json_decode(
            file_get_contents(self::CONFIG_SCIENTIFIC_WIKI_JSON),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * TODO rename/move Postprocess data site/périodique
     * Replace 'site' and 'périodique' with wikidata URL of newspapers and scientific journals or config_press (YAML).
     */
    protected function replaceSitenameByConfig(array $mapData, AbstractWikiTemplate $template): array
    {
        // Wikidata replacements
        $mapData = $this->replaceWithWDataNewspaper($mapData, $template);
        $mapData = $this->replaceWithWDataSciences($mapData);

        // config_press YAML replacements
        return $this->replaceWithHumanConfig($template, $mapData);
    }

    /**
     * Replace 'site' with wikidata URL of newspapers
     */
    protected function replaceWithWDataNewspaper(array $mapData, AbstractWikiTemplate $template): array
    {
        if (!empty($this->publisherData['newspaper'][$this->registrableDomain])) {
            $frwiki = $this->publisherData['newspaper'][$this->registrableDomain]['frwiki'];
            $label = $this->publisherData['newspaper'][$this->registrableDomain]['fr'];
            if (isset($mapData['site']) || $template instanceof LienWebTemplate) {
                $mapData['site'] = WikiTextUtil::wikilink($label, $frwiki);
            }
            if (isset($mapData['périodique']) || $template instanceof ArticleTemplate) {
                $mapData['périodique'] = WikiTextUtil::wikilink($label, $frwiki);
            }
        }
        return $mapData;
    }

    /**
     * Replace 'périodique' with wikidata URL of scientific journals
     */
    protected function replaceWithWDataSciences(array $mapData): array
    {
        if (isset($mapData['périodique']) && isset($this->publisherData['scientific wiki'][$mapData['périodique']])) {
            $mapData['périodique'] = WikiTextUtil::wikilink(
                $mapData['périodique'],
                $this->publisherData['scientific wiki'][$mapData['périodique']]
            );
        }
        return $mapData;
    }

    /**
     * Replace 'site' and 'périodique' with config_press (YAML)
     */
    protected function replaceWithHumanConfig(AbstractWikiTemplate $template, array $mapData): array
    {
        $mapData = $this->replaceSiteForLienWeb($template, $mapData);
        $mapData = $this->replacePeriodique($mapData, $template);

        $mapData = $this->replaceEditor($mapData);

        $mapData = $this->stripParamValue('stripfromauthor', $mapData, 'auteur1');

        return $this->stripParamValue('stripfromtitle', $mapData, 'titre');
    }

    protected function replaceSiteForLienWeb(AbstractWikiTemplate $template, array $mapData): array
    {
        if (isset($this->config[$this->registrableDomain]['site']) && $template instanceof LienWebTemplate) {
            $mapData['site'] = $this->config[$this->registrableDomain]['site'];
            if (empty($mapData['site'])) {
                unset($mapData['site']);
            }
        }
        return $mapData;
    }

    protected function replacePeriodique(array $mapData, AbstractWikiTemplate $template): array
    {
        if (isset($this->config[$this->registrableDomain]['périodique'])
            && (!empty($mapData['périodique'])
                || $template instanceof OuvrageTemplate)
        ) {
            $mapData['périodique'] = $this->config[$this->registrableDomain]['périodique'];
            if (empty($mapData['périodique'])) {
                unset($mapData['périodique']);
            }
        }
        return $mapData;
    }

    /**
     * Replace 'éditeur' on {article} todo move
     */
    protected function replaceEditor(array $mapData): array
    {
        if (isset($this->config[$this->registrableDomain]['éditeur'])
            && !empty($mapData['éditeur'])) {
            $mapData['éditeur'] = $this->config[$this->registrableDomain]['éditeur'];
            if (empty($mapData['éditeur'])) {
                unset($mapData['éditeur']);
            }
        }
        return $mapData;
    }

    /**
     * Use config_press stripFromAuthor (string array) and stripFromTitle, to remove text from author and title.
     */
    protected function stripParamValue(string $configParam, array $mapData, string $templateParam): array
    {
        if (!empty($this->config[$this->registrableDomain][$configParam])
            && isset($this->config[$this->registrableDomain][$configParam])
            && !empty($mapData[$templateParam])) {
            $stripText = $this->config[$this->registrableDomain][$configParam]; // string|array
            $mapData[$templateParam] = trim(str_ireplace((string) $stripText, '', (string) $mapData[$templateParam]));
            if (empty($mapData[$templateParam])) {
                unset($mapData[$templateParam]);
            }
        }
        return $mapData;
    }

    protected function isScientificDomain(): bool
    {
        if (isset($this->publisherData['scientific domain'][$this->registrableDomain])) {
            return true;
        }
        return strpos('.revues.org', (string) $this->registrableDomain) > 0;
    }
}