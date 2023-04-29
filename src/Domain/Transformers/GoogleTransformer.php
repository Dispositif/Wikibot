<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Transformers;

use App\Domain\InfrastructurePorts\GoogleApiQuotaInterface;
use App\Domain\InfrastructurePorts\GoogleBooksInterface;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Publisher\GoogleBookMapper;
use App\Domain\Publisher\GoogleBooksUtil;
use App\Domain\Utils\NumberUtil;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\WikiOptimizer\OptimizerFactory;
use App\Domain\WikiTemplateFactory;
use DomainException;
use Exception;
use Scriptotek\GoogleBooks\Volume;
use Throwable;

/**
 * TODO REFAC : duplicate, extract methods in trait or in AbstractRefBotWorker + ExternBotWorker
 * --
 * Transform <ref>https://books.google...</ref> to <ref>{{Ouvrage|...}}.</ref>
 * in an article wikitext.
 * Class GoogleTransformer
 *
 * @package App\Domain
 */
class GoogleTransformer
{
    public const SLEEP_GOOGLE_API_INTERVAL = 5;

    /**
     * @var array OuvrageTemplate[]
     */
    private array $cacheOuvrageTemplate = [];

    /**
     * GoogleTransformer constructor.
     * todo dependency injection
     */
    public function __construct(private readonly GoogleApiQuotaInterface $quota, private readonly GoogleBooksInterface $googleBooksAdapter, private ?\Psr\Log\LoggerInterface $logger = null)
    {
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
        if ($refsData !== []) {
            $text = $this->processRef($text, $refsData);
        }

        $links = $this->extractGoogleExternalBullets($text);
        if ($links !== []) {
            $text = $this->processExternLinks($text, $links);
        }

        return $text;
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
            '#(?:<ref[^>]*>|{{ref\|) ?('.GoogleBooksUtil::GOOGLEBOOKS_START_URL_PATTERN.'[^>\]} \n]+) ?(?:</ref>|}})#i',
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
            } catch (Throwable $e) {
                echo "Exception ".$e->getMessage();
                continue;
            }

            // ajout point final pour référence
            $citation .= '.';

            $newRef = str_replace($ref[1], $citation, (string) $ref[0]);
            echo $newRef."\n";

            $text = str_replace($ref[0], $newRef, $text);

            echo "sleep ".self::SLEEP_GOOGLE_API_INTERVAL."\n";
            sleep(self::SLEEP_GOOGLE_API_INTERVAL);
        }

        return $text;
    }

    /**
     * TODO : extract. TODO private ?
     * Convert GoogleBooks URL to wiki-template {ouvrage} citation.
     * Need GoogleBooksAdapter injection.
     *
     * @throws Throwable
     */
    public function convertGBurl2OuvrageCitation(string $url): string
    {
        if (!GoogleBooksUtil::isGoogleBookURL($url)) {
            throw new DomainException('Pas de URL Google Books');
        }

        $gooDat = GoogleBooksUtil::parseGoogleBookQuery($url);
        if (empty($gooDat['isbn']) && empty($gooDat['id'])) {
            throw new DomainException('Pas de ISBN ou ID Google Books');
        }

        try {
            $identifiant = $gooDat['id'] ?? $gooDat['isbn'];
            $isISBN = !empty($gooDat['isbn']);
            $ouvrage = $this->generateOuvrageFromGoogleData($identifiant, $isISBN);
        } catch (Throwable $e) {
            // ID n'existe pas sur Google Books
            if (strpos($e->getMessage(), '"message": "The volume ID could n')) {
                return sprintf(
                    '{{lien brisé |url= %s |titre=%s |brisé le=%s}}',
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
            // Exclusion de page=1, page=2 (vue par défaut sur Google Book)
            if (preg_match('#(?:PA|PT)(\d+)$#', (string) $gooDat['pg'], $matches) && (int) $matches[1] >= 3) {
                $page = $matches[1];
            }
            // conversion chiffres Romain pour PR
            // Exclusion de page=1, page=2 (vue par défaut sur Google Book)
            if (preg_match('#PR(\d+)$#', (string) $gooDat['pg'], $matches) && (int) $matches[1] >= 3) {
                $page = NumberUtil::arab2roman((int) $matches[1], true);
            }

            if (!empty($page)) {
                $ouvrage->setParam('passage', $page);
                // ajout commentaire '<!-- utile? -->' ?
            }
        }

        $optimizer = OptimizerFactory::fromTemplate($ouvrage, null, $this->logger = null);
        $optimizer->doTasks();
        $ouvrage2 = $optimizer->getOptiTemplate();

        return $ouvrage2->serialize();
    }

    /**
     * todo: move (injection) to other class.
     * Generate wiki-template {ouvrage} from GoogleBook ID.
     *
     * @param string    $id GoogleBooks ID
     *
     * @throws Exception
     */
    private function generateOuvrageFromGoogleData(string $id, ?bool $isISBN = false): OuvrageTemplate
    {
        // return cached OuvrageTemplate
        if (!$isISBN && isset($this->cacheOuvrageTemplate[$id])) {
            return clone $this->cacheOuvrageTemplate[$id];
        }

        // Get Google data by ID ZvhBAAAAcAAJ
        $volume = $isISBN === true
            ? $this->googleBooksAdapter->getDataByIsbn($id)
            : $this->googleBooksAdapter->getDataByGoogleId($id);
        if (!$volume instanceof Volume) {
            throw new DomainException('googleBooks Volume not found for that GB-id/isbn');
        }

        $mapper = new GoogleBookMapper();
        $mapper->mapLanguageData(true);
        $data = $mapper->process($volume);

        // Generate wiki-template {ouvrage}
        $ouvrage = WikiTemplateFactory::create('ouvrage');
        $ouvrage->hydrate($data);
        $ouvrage->setParam('consulté le', date('d-m-Y'));

        // cache
        $this->cacheOuvrageTemplate[$id] = clone $ouvrage;

        return $ouvrage;
    }

    /**
     * todo move
     *
     *
     */
    public function extractGoogleExternalBullets(string $text): array
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
     *
     * @return string|string[]
     * @throws Throwable
     */
    private function processExternLinks(string $text, array $links): string|array
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

            $newRef = str_replace($pattern[1], $citation, (string) $pattern[0]);
            echo $newRef."\n";

            $text = str_replace($pattern[0], $newRef, $text);

            echo "sleep ".self::SLEEP_GOOGLE_API_INTERVAL."\n";
            sleep(self::SLEEP_GOOGLE_API_INTERVAL);
        }

        return $text;
    }

}
