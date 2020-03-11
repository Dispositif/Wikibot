<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Publisher\GoogleBookMapper;
use App\Domain\Utils\NumberUtil;
use App\Infrastructure\GoogleBooksAdapter;

/**
 * Transform <ref>https://books.google...</ref> to <ref>{{Ouvrage|...}}.</ref>
 * in an article wikitext.
 * Class RefGoogleBook
 *
 * @package App\Domain
 */
class RefGoogleBook
{
    const SLEEP_GOOGLE_API_INTERVAL = 8;

    /**
     * @var array OuvrageTemplate[]
     */
    private $cacheOuvrageTemplate = [];

    /**
     * RefGoogleBook constructor.
     * todo dependency injection
     */
    public function __construct() { }

    /**
     * Process page wikitext. Return wikitext with the <ref> converted.
     *
     * @param string $text Page wikitext
     *
     * @return string New wikitext
     */
    public function process(string $text): string
    {
        $refsData = $this->extractAllGoogleRefs($text);
        if (empty($refsData)) {
            echo "Pas d'URL GB trouvée";

            return $text;
        }

        foreach ($refsData as $ref) {
            try {
                $citation = $this->convertGBurl2OuvrageCitation($this->stripFinalPoint($ref[1]));
            } catch (\Exception $e) {
                echo "Exception ".$e->getMessage();
                continue;
            }

            // ajout point final pour référence
            $citation .= '.';

            $newRef = str_replace($ref[1], $citation, $ref[0]);
            echo $newRef."\n";

            $text = str_replace($ref[0], $newRef, $text);

            echo "sleep ".self::SLEEP_GOOGLE_API_INTERVAL."\n";
            sleep(self::SLEEP_GOOGLE_API_INTERVAL);
        }

        return $text;
    }

    /**
     * Strip the final point (".") as in <ref> ending.
     * TODO move to WikiRef or TextUtil class
     *
     * @param string $str
     *
     * @return string
     */
    private function stripFinalPoint(string $str): string
    {
        if (substr($str, -1, 1) === '.') {
            return substr($str, 0, strlen($str) - 1);
        }

        return $str;
    }

    /**
     * Convert GoogleBooks URL to wiki-template {ouvrage} citation.
     *
     * @param string $url GoogleBooks URL
     *
     * @return string {{ouvrage}}
     * @throws \Exception
     * @throws \Throwable
     */
    private function convertGBurl2OuvrageCitation(string $url): string
    {
        if (!GoogleLivresTemplate::isGoogleBookURL($url)) {
            throw new \DomainException('Pas de URL Google Books');
        }

        $gooDat = GoogleLivresTemplate::parseGoogleBookQuery($url);
        if (empty($gooDat['id'])) {
            throw new \DomainException('Pas de ID Google Books');
        }

        try {
            $ouvrage = $this->generateOuvrageFromGoogleData($gooDat['id']);
        } catch (\Throwable $e) {
            // ID n'existe pas sur Google Books
            if (strpos($e->getMessage(), '404 Not Found')
                && strpos($e->getMessage(), '"message": "The volume ID could n')
            ) {
                return sprintf(
                    '{{lien brisé |url= %s |titre= %s |brisé le=%s |CodexBot=1}}',
                    $url,
                    'ID introuvable sur Google Books',
                    date('d-m-Y')
                );
            }
            throw $e;
        }


        $cleanUrl = GoogleLivresTemplate::simplifyGoogleUrl($url);
        $ouvrage->unsetParam('présentation en ligne');
        $ouvrage->setParam('lire en ligne', $cleanUrl);
        $ouvrage->userSeparator = ' |';

        // Si titre absent
        if (empty($ouvrage->getParam('titre'))) {
            throw new \DomainException("Ouvrage sans titre (data Google?)");
        }

        // Google page => 'passage'
        if (!empty($gooDat['pg'])) {
            if (preg_match('#(?:PA|PT)([0-9]+)$#', $gooDat['pg'], $matches)) {
                // Exclusion de page=1, page=2 (vue par défaut sur Google Book)
                if (intval($matches[1]) >= 3) {
                    $page = $matches[1];
                }
            }
            // conversion chiffres Romain pour PR
            if (preg_match('#PR([0-9]+)$#', $gooDat['pg'], $matches)) {
                // Exclusion de page=1, page=2 (vue par défaut sur Google Book)
                if (intval($matches[1]) >= 3) {
                    $page = NumberUtil::arab2roman(intval($matches[1]), true);
                }
            }

            if (!empty($page)) {
                $ouvrage->setParam('passage', $page);
                // ajout commentaire '<!-- utile? -->' ?
            }
        }

        $optimizer = new OuvrageOptimize($ouvrage);
        $optimizer->doTasks();
        $ouvrage2 = $optimizer->getOuvrage();

        return $ouvrage2->serialize();
    }

    /**
     * todo: move (injection) to other class.
     * Generate wiki-template {ouvrage} from GoogleBook ID.
     *
     * @param string $id GoogleBooks ID
     *
     * @return OuvrageTemplate
     * @throws \Exception
     */
    private function generateOuvrageFromGoogleData(string $id): OuvrageTemplate
    {
        // return cached OuvrageTemplate
        if (isset($this->cacheOuvrageTemplate[$id])) {
            return clone $this->cacheOuvrageTemplate[$id];
        }

        // Get Google data by ID ZvhBAAAAcAAJ
        $adapter = new GoogleBooksAdapter();
        $volume = $adapter->getDataByGoogleId($id);

        $mapper = new GoogleBookMapper();
        $mapper->mapLanguageData(true);
        $data = $mapper->process($volume);

        // Generate wiki-template {ouvrage}
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrate($data);

        // cache
        $this->cacheOuvrageTemplate[$id] = clone $ouvrage;

        return $ouvrage;
    }

    /**
     * Extract all <ref>/{ref} with only GoogleBooks URL.
     * Todo : supprimer point final URL
     *
     * @param string $text Page wikitext
     *
     * @return array [0 => ['<ref>http...</ref>', 'http://'], 1 => ...]
     */
    private function extractAllGoogleRefs(string $text): array
    {
        // <ref>...</ref> or {{ref|...}}
        if (preg_match_all(
            '#(?:<ref[^>]*>|{{ref\|) ?(https?://(?:books|play)\.google\.[a-z]{2,3}/(?:books)?(?:/reader)?\?id=[^>\]} \n]+) ?(?:</ref>|}})#i',
            $text,
            $matches,
            PREG_SET_ORDER
        )
        ) {
            return $matches;
        }

        return [];
    }
}
