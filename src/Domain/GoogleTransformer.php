<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Publisher\GoogleBookMapper;
use App\Domain\Publisher\GoogleBooksUtil;
use App\Domain\Utils\NumberUtil;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\GoogleBooksAdapter;
use DomainException;
use Exception;
use Throwable;

/**
 * TODO REFAC : duplicate, extract methods in trait or in RefBotWorker + ExternBotWorker
 * --
 * Transform <ref>https://books.google...</ref> to <ref>{{Ouvrage|...}}.</ref>
 * in an article wikitext.
 * Class GoogleTransformer
 *
 * @package App\Domain
 */
class GoogleTransformer
{
    const SLEEP_GOOGLE_API_INTERVAL = 5;

    /**
     * @var array OuvrageTemplate[]
     */
    private $cacheOuvrageTemplate = [];
    /**
     * @var GoogleApiQuota
     */
    private $quota;

    /**
     * GoogleTransformer constructor.
     * todo dependency injection
     */
    public function __construct()
    {
        $this->quota = new GoogleApiQuota();
    }

    /**
     * process page wikitext. Return wikitext with the <ref> converted.
     *
     * @param string $text Page wikitext
     *
     * @return string New wikitext
     * @throws Throwable
     */
    public function process(string $text): string
    {
        if ($this->quota->isQuotaReached()) {
            throw new DomainException('Quota Google atteint');
        }

        $refsData = $this->extractAllGoogleRefs($text);
        if (!empty($refsData)) {
            $text = $this->processRef($text, $refsData);
        }

        $links = $this->extractGoogleExternal($text);
        if (!empty($links)) {
            $text = $this->processExternLinks($text, $links);
        }

        return $text;
    }

    /**
     * todo move
     *
     * @param string $text
     *
     * @return array
     */
    public function extractGoogleExternal(string $text): array
    {
        // match "* https://books.google.fr/..."
        if (preg_match_all(
            '#^\* *('.GoogleBooksUtil::GOOGLEBOOKS_START_URL_PATTERN.'[^ <{\]}\n\r]+) *$#im',
            $text,
            $matches,
            PREG_SET_ORDER
        )
        ) {
            return $matches;
        }

        return [];
    }

    /**
     * TODO Duplication du dessus...
     *
     * @param string $text
     * @param array  $links
     *
     * @return string|string[]
     * @throws Throwable
     */
    private function processExternLinks(string $text, array $links)
    {
        foreach ($links as $pattern) {
            if ($this->quota->isQuotaReached()) {
                throw new DomainException('Quota Google atteint');
            }
            try {
                $citation = $this->convertGBurl2OuvrageCitation(WikiTextUtil::stripFinalPoint($pattern[1]));
            } catch (Exception $e) {
                echo "Exception ".$e->getMessage();
                continue;
            }

            // todo : ajout point final pour référence ???
            $citation .= '.';

            $newRef = str_replace($pattern[1], $citation, $pattern[0]);
            echo $newRef."\n";

            $text = str_replace($pattern[0], $newRef, $text);

            echo "sleep ".self::SLEEP_GOOGLE_API_INTERVAL."\n";
            sleep(self::SLEEP_GOOGLE_API_INTERVAL);
        }

        return $text;
    }

    /**
     * TODO : extract
     * Convert GoogleBooks URL to wiki-template {ouvrage} citation.
     *
     * @param string $url GoogleBooks URL
     *
     * @return string {{ouvrage}}
     * @throws Exception
     * @throws Throwable
     */
    public function convertGBurl2OuvrageCitation(string $url): string
    {
        if (!GoogleBooksUtil::isGoogleBookURL($url)) {
            throw new DomainException('Pas de URL Google Books');
        }

        $gooDat = GoogleBooksUtil::parseGoogleBookQuery($url);
        if (empty($gooDat['id'])) {
            throw new DomainException('Pas de ID Google Books');
        }

        try {
            $ouvrage = $this->generateOuvrageFromGoogleData($gooDat['id']);
        } catch (Throwable $e) {
            // ID n'existe pas sur Google Books
            if (strpos($e->getMessage(), '"message": "The volume ID could n')) {
                return sprintf(
                    '{{lien brisé |url= %s |titre= %s |brisé le=%s}}',
                    $url,
                    'Ouvrage inexistant sur Google Books',
                    date('d-m-Y')
                );
            }
            throw $e;
        }

        $cleanUrl = GoogleBooksUtil::simplifyGoogleUrl($url);
        $ouvrage->unsetParam('présentation en ligne');
        $ouvrage->setParam('lire en ligne', $cleanUrl);
        $ouvrage->userSeparator = ' |';

        // Si titre absent
        if (!$ouvrage->hasParamValue('titre')) {
            throw new DomainException("Ouvrage sans titre (data Google?)");
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
     * @throws Exception
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
        $ouvrage = WikiTemplateFactory::create('ouvrage');
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
        // GoogleLivresTemplate::GOOGLEBOOK_URL_PATTERN
        if (preg_match_all(
            '#(?:<ref[^>]*>|{{ref\|) ?('.GoogleBooksUtil::GOOGLEBOOKS_START_URL_PATTERN
            .'[^>\]} \n]+) ?(?:</ref>|}})#i',
            $text,
            $matches,
            PREG_SET_ORDER
        )
        ) {
            return $matches;
        }

        return [];
    }

    private function processRef(string $text, array $refsData): string
    {
        foreach ($refsData as $ref) {
            if ($this->quota->isQuotaReached()) {
                throw new DomainException('Quota Google atteint');
            }
            try {
                $citation = $this->convertGBurl2OuvrageCitation(WikiTextUtil::stripFinalPoint($ref[1]));
                sleep(2);
            } catch (Exception $e) {
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

}
