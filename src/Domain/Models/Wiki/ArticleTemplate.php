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
    use ArticleTemplateParams, InfoTrait;

    const MODEL_NAME = 'Article';

    const REQUIRED_PARAMETERS
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

    private $source;

    /**
     * @param bool|null $cleanOrder
     *
     * @return string
     */
    public function serialize(?bool $cleanOrder = false): string
    {
        // modifier ici le this->userSeparator
        //        if('|' === $this->userSeparator) {
        //            $this->userSeparator = ' |';
        //        }
        $serial = parent::serialize($cleanOrder);

        //        $serial = $this->anneeOrDateSerialize($serial);


        return $serial.$this->serializeExternalTemplates();
    }

    /**
     * todo move to abstract ? + refac
     * dirty.
     */
    public function serializeExternalTemplates(): string
    {
        $res = '';
        if (!empty($this->externalTemplates)) {
            foreach ($this->externalTemplates as $externalTemplate) {
                $res .= $externalTemplate->raw;
            }
        }

        return $res;
    }

    /**
     * Pas de serialization année vide si date non vide.
     *
     * @param string $serial
     *
     * @return string
     */
    private function anneeOrDateSerialize(string $serial): string
    {
        if (preg_match("#\|[\n ]*année=[\n ]*\|#", $serial) > 0
            && preg_match("#\|[\n ]*date=#", $serial) > 0
        ) {
            $serial = preg_replace("#\|[\n ]*année=[\n ]*#", '', $serial);
        }

        return $serial;
    }

    /**
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
                        $this->getParam('périodique')
                    )
                )
            )
        );
        $date = str_replace([' ', '-'], ['', ''], $this->getParam('date'));

        return $periodique.$date;
    }

}
