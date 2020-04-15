<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use Exception;

/**
 * Class ArticleTemplate
 */
class ArticleTemplate extends AbstractWikiTemplate implements ArticleTemplateAlias, ArticleOrLienBriseInterface
{
    use ArticleTemplateParams, BiblioTemplateTrait;

    const WIKITEMPLATE_NAME = 'Article';

    const REQUIRED_PARAMETERS = ['titre', 'périodique', 'date'];

    const MINIMUM_PARAMETERS
        = [
            //            'langue' => '',
            'auteur1' => '',
            'titre' => '', // <!-- Paramètre obligatoire -->
            'périodique' => '', // <!-- Paramètre obligatoire -->
            //            'volume' => '',
            //            'numéro' => '',
            'date' => '', // <!-- Paramètre obligatoire -->
            //            'pages' => '',
            //            'issn' => '', // Inutile ? https://fr.wikipedia.org/wiki/Discussion_mod%C3%A8le:Article#ISSN
            //            'e-issn' => '',
            'lire en ligne' => '',
            //            'consulté le' => '', // 16 mars 2020
            //            'id',
        ];

    /*
     * Default separator
     */
    public $userSeparator = ' |';

    public $externalTemplates = [];

    /**
     * todo move to BiblioTrait + fusion
     * Propose fubar pour un <ref name="fubar"> ou un {{article|'id=fubar'}}.
     *
     * @return string
     * @throws Exception
     */
    public function generateRefName(): string
    {
        // Style "auto1234"
        if (empty($this->getParam('périodique') || empty($this->getParam('date')))) {
            return 'auto'.(string)rand(1000, 9999);
        }
        // Style "LeMonde15022017"
        $periodique = str_replace(
            ' ',
            '',
            TextUtil::stripPunctuation(
                TextUtil::stripAccents(
                    WikiTextUtil::unWikify(
                        $this->getParam('périodique') ?? ''
                    )
                )
            )
        );
        $date = str_replace([' ', '-'], ['', ''], $this->getParam('date'));

        return $periodique.$date;
    }

}
