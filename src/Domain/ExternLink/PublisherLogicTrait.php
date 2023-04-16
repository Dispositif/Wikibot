<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

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
        // todo REFAC DataObject[]
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
     * Logique : remplacement titre périodique ou nom du site
     *
     * @param array $mapData
     * @param       $template
     *
     * @return array
     */
    protected function replaceSitenameByConfig(array $mapData, $template): array
    {
        // from wikidata URL of newspapers
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

        // from wikidata of scientific journals
        if (isset($mapData['périodique']) && isset($this->publisherData['scientific wiki'][$mapData['périodique']])) {
            $mapData['périodique'] = WikiTextUtil::wikilink(
                $mapData['périodique'],
                $this->publisherData['scientific wiki'][$mapData['périodique']]
            );
        }

        // from YAML config
        if (!empty($this->config[$this->registrableDomain]['site']) && $template instanceof LienWebTemplate) {
            $mapData['site'] = $this->config[$this->registrableDomain]['site'];
        }
        if (!empty($this->config[$this->registrableDomain]['périodique'])
            && (!empty($mapData['périodique'])
                || $template instanceof OuvrageTemplate)
        ) {
            $mapData['périodique'] = $this->config[$this->registrableDomain]['périodique'];
        }

        return $mapData;
    }

    protected function isScientificDomain(): bool
    {
        if (isset($this->publisherData['scientific domain'][$this->registrableDomain])) {
            return true;
        }
        return strpos('.revues.org', $this->registrableDomain) > 0;
    }
}