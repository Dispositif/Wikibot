<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Domain\Models\Wiki;

use App\Domain\WikiTemplateFactory;
use Exception;
use Throwable;

/**
 * Convert wiki-template in another wiki-template.
 * Class TemplateConverter
 *
 * @package App\Domain\Models\Wiki
 */
class TemplateConverter
{
    const PARAM_OUVRAGE_TO_ARTICLE
        = [
            'tome' => 'volume',
            'numéro chapitre' => 'numéro',
            'pages totales' => 'pages',
        ];

    /**
     * Conversion {ouvrage} en {article}.
     * todo Move factory ?
     *
     * @param OuvrageTemplate $ouvrage
     *
     * @return ArticleTemplate|null
     * @throws Exception
     */
    public static function ArticleFromOuvrage(OuvrageTemplate $ouvrage): ?ArticleTemplate
    {
        $article = WikiTemplateFactory::create('article');
        try {
            $data = self::convertDataOuvrage2Article($ouvrage->toArray());

            $article->hydrate($data);

            $articleInfos = array_merge(['ConvertFromOuvrage' => 1], $ouvrage->getInfos());
            $article->setInfos($articleInfos);
        } catch (Throwable $e) {
            return null;
        }

        if ($article instanceof ArticleTemplate
            && self::hasNeededParams($article)
        ) {
            return $article;
        }

        return null;
    }

    /**
     * TODO : refac/move in AbstractTemplate
     *
     * @param ArticleTemplate $article
     *
     * @return bool
     * @throws Exception
     */
    public static function hasNeededParams(ArticleTemplate $article): bool
    {
        $needed = ['titre', 'périodique', 'date'];
        foreach ($needed as $need) {
            if (empty($article->getParam($need))) {
                echo "Article : paramètre obligatoire '$need' manquant";

                return false;
            }
        }

        return true;
    }

    /**
     * Convert param names between wiki-templates.
     *
     * @param array $ouvrageData
     *
     * @return array
     */
    private static function convertDataOuvrage2Article(array $ouvrageData): array
    {
        $data = [];
        foreach ($ouvrageData as $param => $value) {
            // Conversion du nom de paramètre
            // + esquive des doublons qui supprimeraient une value
            if (key_exists($param, self::PARAM_OUVRAGE_TO_ARTICLE)
                && !key_exists(self::PARAM_OUVRAGE_TO_ARTICLE[$param], $ouvrageData)
            ) {
                $data[self::PARAM_OUVRAGE_TO_ARTICLE[$param]] = $value;
                continue;
            }
            // Sinon : on conserve param/value, même si param invalide dans Article
            $data[$param] = $value;
        }

        $data = self::convertDateForArticle($data);

        return $data;
    }

    public static function convertDateForArticle(array $data): array
    {
        // generate 'date'
        if (empty($data['date'])) {
            $data['date'] = trim(
                sprintf(
                    '%s %s %s',
                    $data['jour'] ?? '',
                    $data['mois'] ?? '',
                    $data['année'] ?? ''
                )
            );
            unset($data['jour']);
            unset($data['mois']);
            unset($data['année']);
        }

        return $data;
    }
}
