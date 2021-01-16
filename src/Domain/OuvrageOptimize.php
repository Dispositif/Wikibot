<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Enums\Language;
use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Publisher\GoogleBooksUtil;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\FileManager;
use DomainException;
use Exception;

/**
 * Legacy.
 * TODO move methods to OuvrageClean setters
 * TODO AbstractProcess
 * TODO observer/event (log, MajorEdition)
 * Class OuvrageProcess.
 */
class OuvrageOptimize extends AbstractTemplateOptimizer
{
    use OptimizeISBNTrait;

    const CONVERT_GOOGLEBOOK_TEMPLATE = false; // change OuvrageOptimizeTest !!

    const WIKI_LANGUAGE = 'fr';

    const PUBLISHER_FRWIKI_FILENAME = __DIR__.'/resources/data_editors_wiki.json';

    public $notCosmetic = false;

    public $major = false;

    /**
     * @return $this
     * @throws Exception
     */
    public function doTasks()
    {
        $this->cleanAndPredictErrorParameters();

        $this->processAuthors();

        $this->processLang();
        $this->processLang('langue originale');

        $this->processTitle();
        $this->convertLienAuteurTitre();

        $this->processEditionCitebook();

        $this->processEditeur();
        $this->processDates();
        $this->externalTemplates();
        $this->predictFormatByPattern();

        $this->processIsbn();
        $this->processBnf();

        $this->processLocation(); // 'lieu'

        $this->GoogleBookURL('lire en ligne');
        $this->GoogleBookURL('présentation en ligne');

        return $this;
    }

    /**
     * Todo: injection dep.
     * Todo : "[s. l.]" sans lieu "s.l.n.d." sans lieu ni date.
     *
     * @throws Exception
     */
    protected function processLocation()
    {
        $location = $this->getParam('lieu');
        if (empty($location)) {
            return;
        }

        // typo and unwikify
        $memo = $location;
        $location = WikiTextUtil::unWikify($location);
        $location = TextUtil::mb_ucfirst($location);
        if ($memo !== $location) {
            $this->setParam('lieu', $location);
            $this->addSummaryLog('±lieu');
            $this->notCosmetic = true;
        }

        // translation : "London"->"Londres"
        $manager = new FileManager();
        $row = $manager->findCSVline(__DIR__.'/resources/traduction_ville.csv', $location);
        if (!empty($row) && !empty($row[1])) {
            $this->setParam('lieu', $row[1]);
            $this->addSummaryLog('lieu francisé');
            $this->notCosmetic = true;
        }
    }

    protected function processBnf()
    {
        $bnf = $this->getParam('bnf');
        if (!$bnf) {
            return;
        }
        $bnf = str_ireplace('FRBNF', '', $bnf);
        $this->setParam('bnf', $bnf);
    }

    /**
     * @throws Exception
     */
    protected function processAuthors()
    {
        $this->distinguishAuthors();
        //$this->fusionFirstNameAndName(); // deactivated : no consensus
    }

    protected function convertLienAuteurTitre(): void
    {
        $auteurParams = ['auteur1', 'auteur2', 'auteur2', 'titre'];
        foreach ($auteurParams as $auteurParam) {
            if ($this->hasParamValue($auteurParam)
                && $this->hasParamValue('lien '.$auteurParam)
            ) {
                $this->setParam(
                    $auteurParam,
                    WikiTextUtil::wikilink(
                        $this->getParam($auteurParam),
                        $this->getParam('lien '.$auteurParam)
                    )
                );
                $this->unsetParam('lien '.$auteurParam);
                $this->addSummaryLog('±lien '.$auteurParam);
                $this->notCosmetic = true;
            }
        }
    }

    /**
     * Detect and correct multiple authors in same parameter.
     * Like "auteurs=J. M. Waller, M. Bigger, R. J. Hillocks".
     *
     * @throws Exception
     */
    protected function distinguishAuthors()
    {
        // merge params of author 1
        $auteur1 = $this->getParam('auteur') ?? '';
        $auteur1 .= $this->getParam('auteurs') ?? '';
        $auteur1 .= $this->getParam('prénom1') ?? '';
        $auteur1 .= ' '.$this->getParam('nom1') ?? '';
        $auteur1 = trim($auteur1);
        // of authors 2
        $auteur2 = $this->getParam('auteur2') ?? '';
        $auteur2 .= $this->getParam('prénom2') ?? '';
        $auteur2 .= ' '.$this->getParam('nom2') ?? '';
        $auteur2 = trim($auteur2);

        // skip if wikilink in author
        if (empty($auteur1) || WikiTextUtil::isWikify($auteur1)) {
            return;
        }

        $machine = new PredictAuthors();
        $res = $machine->predictAuthorNames($auteur1);

        if (1 === count($res)) {
            // auteurs->auteur?
            return;
        }
        // Many authors... and empty "auteur2"
        if (count($res) >= 2 && empty($auteur2)) {
            // delete author-params
            array_map(
                function ($param) {
                    $this->unsetParam($param);
                },
                ['auteur', 'auteurs', 'prénom1', 'nom1']
            );
            // iterate and edit new values
            $count = count($res);
            for ($i = 0; $i < $count; ++$i) {
                $this->setParam(sprintf('auteur%s', $i + 1), $res[$i]);
            }
            $this->addSummaryLog('distinction auteurs');
            $this->major = true;
            $this->notCosmetic = true;
        }
    }

    /**
     * todo: move/implement.
     *
     * @param string|null $param
     *
     * @throws Exception
     */
    protected function processLang(?string $param = 'langue')
    {
        $param = $param ?? 'langue';
        $lang = $this->getParam($param) ?? null;

        if ($lang) {
            $lang2 = Language::all2wiki($lang);

            // strip "langue originale=fr"
            if ('langue originale' === $param && self::WIKI_LANGUAGE === $lang2
                && (!$this->getParam('langue') || $this->getParam('langue') === $lang2)
            ) {
                $this->unsetParam('langue originale');
                $this->addSummaryLog('-langue originale');
            }

            if ($lang2 && $lang !== $lang2) {
                $this->setParam($param, $lang2);
                if (self::WIKI_LANGUAGE !== $lang2) {
                    $this->addSummaryLog('±'.$param);
                }
            }
        }
    }

    /**
     * Find year of book publication.
     *
     * @return int|null
     * @throws Exception
     */
    protected function findBookYear(): ?int
    {
        $annee = $this->getParam('année');
        if (!empty($annee) && is_numeric($annee)) {
            return intval($annee);
        }
        $date = $this->getParam('date');
        if ($date && preg_match('#[^0-9]?([12][0-9][0-9][0-9])[^0-9]?#', $date, $matches) > 0) {
            return intval($matches[1]);
        }

        return null;
    }

    protected function stripIsbn(string $isbn): string
    {
        return trim(preg_replace('#[^0-9Xx]#', '', $isbn));
    }

    protected function processTitle()
    {
        $oldtitre = $this->getParam('titre');
        $this->langInTitle();
        $this->extractExternalLink('titre');
        $this->upperCaseFirstLetter('titre');
        $this->typoDeuxPoints('titre');

        $this->extractSubTitle();

        // 20-11-2019 : Retiré majuscule à sous-titre

        if ($this->getParam('titre') !== $oldtitre) {
            $this->addSummaryLog('±titre');
        }

        $this->valideNumeroChapitre();
        $this->extractExternalLink('titre chapitre');
        $this->upperCaseFirstLetter('titre chapitre');
    }

    protected function detectColon($param): bool
    {
        // > 0 don't count a starting colon ":bla"
        if ($this->hasParamValue($param) && mb_strrpos($this->getParam('titre'), ':') > 0) {
            return true;
        }

        return false;
    }

    protected function extractSubTitle(): void
    {
        // FIXED bug [[fu:bar]]
        if (!$this->getParam('titre') || WikiTextUtil::isWikify($this->getParam('titre'))) {
            return;
        }

        if (!$this->detectColon('titre')) {
            return;
        }
        // Que faire si déjà un sous-titre ?
        if ($this->hasParamValue('sous-titre')) {
            return;
        }

        // titre>5 and sous-titre>5 and sous-titre<40
        if (preg_match('#^(?<titre>[^:]{5,}):(?<st>.{5,40})$#', $this->getParam('titre') ?? '', $matches) > 0) {
            $this->setParam('titre', trim($matches['titre']));
            $this->setParam('sous-titre', trim($matches['st']));
            $this->addSummaryLog('>sous-titre');
        }
    }

    /**
     * Normalize a Google Book links.
     * Clean the useless URL parameters or transform into wiki-template.
     *
     * @param $param
     *
     * @throws Exception
     */
    protected function googleBookUrl(string $param): void
    {
        $url = $this->getParam($param);
        if (empty($url)
            || !GoogleBooksUtil::isGoogleBookURL($url)
        ) {
            return;
        }

        if (self::CONVERT_GOOGLEBOOK_TEMPLATE) {
            $template = GoogleLivresTemplate::createFromURL($url);
            if ($template) {
                $this->setParam($param, $template->serialize());
                $this->addSummaryLog('{Google}');
                $this->notCosmetic = true;

                return;
            }
        }

        try {
            $goo = GoogleBooksUtil::simplifyGoogleUrl($url);
        } catch (DomainException $e) {
            // ID manquant ou malformé
            $errorValue = sprintf(
                '%s <!-- ERREUR %s -->',
                $url,
                $e->getMessage()
            );
            $this->setParam($param, $errorValue);
            $this->addSummaryLog('erreur URL');
            $this->notCosmetic = true;
            $this->major = true;
        }

        if (!empty($goo) && $goo !== $url) {
            $this->setParam($param, $goo);
            // cleaned tracking parameters in Google URL ?
            if (GoogleBooksUtil::isTrackingUrl($url)) {
                $this->addSummaryLog('tracking');
                $this->notCosmetic = true;
            }
        }
    }

    /**
     * - {{lang|...}} dans titre => langue=... puis titre nettoyé
     *  langue=L’utilisation de ce paramètre permet aussi aux synthétiseurs vocaux de reconnaître la langue du titre de
     * l’ouvrage.
     * Il est possible d'afficher plusieurs langues, en saisissant le nom séparé par des espaces ou des virgules. La première langue doit être celle du titre.
     *
     * @throws Exception
     */
    protected function langInTitle(): void
    {
        if (preg_match(
                '#^{{ ?(?:lang|langue) ?\| ?([a-z-]{2,5}) ?\| ?(?:texte=)?([^{}=]+)(?:\|dir=rtl)?}}$#i',
                $this->getParam('titre'),
                $matches
            ) > 0
        ) {
            $lang = trim($matches[1]);
            $newtitre = str_replace($matches[0], trim($matches[2]), $this->getParam('titre'));

            // problème : titre anglais de livre français
            // => conversion {{lang}} du titre seulement si langue= défini
            // opti : restreindre à ISBN zone 2 fr ?
            if ($lang === $this->getParam('langue')) {
                $this->setParam('titre', $newtitre);
                $this->addSummaryLog('°titre');
            }

            // desactivé à cause de l'exception décrite ci-dessus
            // si langue=VIDE : ajout langue= à partir de langue titre
            //            if (self::WIKI_LANGUAGE !== $lang && empty($this->getParam('langue'))) {
            //                $this->setParam('langue', $lang);
            //                $this->log('+langue='.$lang);
            //            }
        }
    }

    protected function processDates()
    {
        // dewikification
        $params = ['date', 'année', 'mois', 'jour'];
        foreach ($params as $param) {
            if ($this->hasParamValue($param) && WikiTextUtil::isWikify(' '.$this->getParam($param))) {
                $this->setParam($param, WikiTextUtil::unWikify($this->getParam($param)));
            }
        }

        try {
            $this->moveDate2Year();
        } catch (Exception $e) {
            $this->log->warning('Exception '.$e->getMessage());
        }
    }

    /**
     * Bool ?
     * Retire lien externe du titre : consensus Bistro 27 août 2011
     * idem  'titre chapitre'.
     * Lien externe déplacé éventuellement dans "lire en ligne"
     *
     * @param string $param
     *
     * @throws Exception
     */
    protected function extractExternalLink(string $param): void
    {
        if (empty($this->getParam($param))) {
            return;
        }
        if (preg_match('#^\[(http[^ \]]+) ([^]]+)]#i', $this->getParam($param), $matches) > 0) {
            $this->setParam($param, str_replace($matches[0], $matches[2], $this->getParam($param)));
            $this->addSummaryLog('±'.$param);

            if (in_array($param, ['titre', 'titre chapitre'])) {
                if (empty($this->getParam('lire en ligne'))) {
                    $this->setParam('lire en ligne', $matches[1]);
                    $this->addSummaryLog('+lire en ligne');

                    return;
                }
                $this->addSummaryLog('autre lien externe: '.$matches[1]);
            }
        }
    }

    protected function upperCaseFirstLetter($param)
    {
        if (!$this->hasParamValue($param)) {
            return;
        }
        $newValue = TextUtil::mb_ucfirst(trim($this->getParam($param)));
        $this->setParam($param, $newValue);
    }

    /**
     * Typo internationale 'titre : sous-titre'.
     * Fix fantasy typo of subtitle with '. ' or ' - '.
     * International Standard Bibliographic Description :
     * https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Le_Bistro/13_janvier_2016#Modif_du_mod%C3%A8le:Ouvrage.
     *
     * @param $param
     *
     * @throws Exception
     */
    protected function typoDeuxPoints($param)
    {
        $origin = $this->getParam($param) ?? '';
        if (empty($origin)) {
            return;
        }
        // FIXED bug [[fu:bar]]
        if (WikiTextUtil::isWikify($origin)) {
            return;
        }

        $origin = TextUtil::replaceNonBreakingSpaces($origin);

        $strTitle = $origin;

        // CORRECTING TYPO FANTASY OF SUBTITLE

        // Replace first '.' by ':' if no ': ' and no numbers around (as PHP 7.3)
        // exlude pattern "blabla... blabla"
        // TODO: statistics

        // Replace ' - ' or ' / ' (spaced!) by ' : ' if no ':' and no numbers after (as PHP 7.3 or 1939-1945)
        if (!mb_strpos(':', $strTitle) && preg_match('#.{6,} ?[-/] ?[^0-9)]{6,}#', $strTitle) > 0) {
            $strTitle = preg_replace('#(.{6,}) [-/] ([^0-9)]{6,})#', '$1 : $2', $strTitle);
        }

        // international typo style " : " (first occurrence)
        $strTitle = preg_replace('#[ ]*:[ ]*#', ' : ', $strTitle);

        if ($strTitle !== $origin) {
            $this->setParam($param, $strTitle);
            $this->addSummaryLog(sprintf(':%s', $param));
        }
    }

    protected function valideNumeroChapitre()
    {
        $value = $this->getParam('numéro chapitre');
        if (empty($value)) {
            return;
        }
        // "12" ou "VI", {{II}}, II:3
        if (preg_match('#^[0-9IVXL\-.:{}]+$#i', $value) > 0) {
            return;
        }
        // déplace vers "titre chapitre" ?
        if (!$this->getParam('titre chapitre')) {
            $this->unsetParam('numéro chapitre');
            $this->setParam('titre chapitre', $value);
        }
        $this->addSummaryLog('≠numéro chapitre');
    }

    /**
     * TODO move+refac
     * TODO CommentaireBiblioTemplate  ExtraitTemplate
     * Probleme {{commentaire biblio}} <> {{commentaire biblio SRL}}
     * Generate supplementary templates from obsoletes params.
     *
     * @throws Exception
     */
    protected function externalTemplates()
    {
        // "extrait=bla" => {{citation bloc|bla}}
        if ($this->hasParamValue('extrait')) {
            $extrait = $this->getParam('extrait');
            // todo bug {{citation bloc}} si "=" ou "|" dans texte de citation
            // Legacy : use {{début citation}} ... {{fin citation}}
            if (preg_match('#[=|]#', $extrait) > 0) {
                $this->optiTemplate->externalTemplates[] = (object)[
                    'template' => 'début citation',
                    '1' => '',
                    'raw' => '{{Début citation}}'.$extrait.'{{Fin citation}}',
                ];
                $this->addSummaryLog('{Début citation}');
                $this->notCosmetic = true;
            } else {
                // StdClass
                $this->optiTemplate->externalTemplates[] = (object)[
                    'template' => 'citation bloc',
                    '1' => $extrait,
                    'raw' => '{{Citation bloc|'.$extrait.'}}',
                ];
                $this->addSummaryLog('{Citation bloc}');
                $this->notCosmetic = true;
            }

            $this->unsetParam('extrait');
            $this->notCosmetic = true;
        }

        // "commentaire=bla" => {{Commentaire biblio|1=bla}}
        if ($this->hasParamValue('commentaire')) {
            $commentaire = $this->getParam('commentaire');
            $this->optiTemplate->externalTemplates[] = (object)[
                'template' => 'commentaire biblio',
                '1' => $commentaire,
                'raw' => '{{Commentaire biblio|'.$commentaire.'}}',
            ];
            $this->unsetParam('commentaire');
            $this->addSummaryLog('{commentaire}');
            $this->notCosmetic = true;
        }
    }

    // ----------------------
    // ----------------------
    // ----------------------

    /**
     * Date->année (nécessaire pour OuvrageComplete).
     *
     * @throws Exception
     */
    protected function moveDate2Year()
    {
        $date = $this->getParam('date') ?? false;
        if ($date) {
            if (preg_match('#^-?[12][0-9][0-9][0-9]$#', $date)) {
                $this->setParam('année', $date);
                $this->unsetParam('date');
                //$this->log('>année');
            }
        }
    }

    protected function predictFormatByPattern()
    {
        if (($value = $this->getParam('format'))) {
            // predict if 'format électronique'
            // format electronique lié au champ 'lire en ligne'
            // 2015 https://fr.wikipedia.org/wiki/Discussion_mod%C3%A8le:Ouvrage#format,_format_livre,_format_%C3%A9lectronique
            //            if (preg_match('#(pdf|epub|html|kindle|audio|\{\{aud|jpg)#i', $value) > 0) {
            //
            //                $this->setParam('format électronique', $value);
            //                $this->unsetParam('format');
            //                $this->log('format:électronique?');
            //
            //                return;
            //            }
            if (preg_match(
                    '#(ill\.|couv\.|in-[0-9]|in-fol|poche|broché|relié|{{unité|{{Dunité|[0-9]{2} ?cm|\|cm}}|vol\.|A4)#i',
                    $value
                ) > 0
            ) {
                $this->setParam('format livre', $value);
                $this->unsetParam('format');
                $this->addSummaryLog('format:livre?');
                $this->notCosmetic = true;
            }
            // Certainement 'format électronique'...
        }
    }

    /**
     * todo : vérif lien rouge
     * todo 'lien éditeur' affiché 1x par page
     * opti : Suppression lien éditeur si c'est l'article de l'éditeur.
     *
     * @throws Exception
     */
    protected function processEditeur()
    {
        $editeur = $this->getParam('éditeur');
        if (empty($editeur)) {
            return;
        }

        // FIX bug "GEO Art ([[Prisma Media]]) ; [[Le Monde]]"
        if (preg_match('#\[.*\[.*\[#', $editeur) > 0) {
            return;
        }
        // FIX bug "[[Fu|Bar]] bla" => [[Fu|Bar bla]]
        if (preg_match('#(.+\[\[|\]\].+)#', $editeur) > 0) {
            return;
        }

        // [[éditeur]]
        if (preg_match('#\[\[([^|]+)]]#', $editeur, $matches) > 0) {
            $editeurUrl = $matches[1];
        }
        // [[bla|éditeur]]
        if (preg_match('#\[\[([^]|]+)\|.+]]#', $editeur, $matches) > 0) {
            $editeurUrl = $matches[1];
        }

        // Todo : traitement/suppression des abréviations communes :
        // ['éd. de ', 'éd. du ', 'éd.', 'ed.', 'Éd. de ', 'Éd.', 'édit.', 'Édit.', '(éd.)', '(ed.)', 'Ltd.']

        $editeurStr = WikiTextUtil::unWikify($editeur);
        // On garde minuscule sur éditeur, pour nuance Éditeur/éditeur permettant de supprimer "éditeur"
        // ex: "éditions Milan" => "Milan"

        // Déconseillé : 'lien éditeur' (obsolete 2019)
        if ($this->hasParamValue('lien éditeur')) {
            if (empty($editeurUrl)) {
                $editeurUrl = $this->getParam('lien éditeur');
            }
            $this->unsetParam('lien éditeur');
        }

        if (empty($editeurUrl)) {
            $editeurUrl = $this->predictPublisherWikiTitle($editeurStr);
            if (!empty($editeurUrl) && $this->wikiPageTitle !== $editeurUrl) {
                $this->addSummaryLog('+lien éditeur');
                $this->notCosmetic = true;
                $this->major = true;
            }
        }


        $newEditeur = $editeurStr;
        if (!empty($editeurUrl)) {
            $newEditeur = WikiTextUtil::wikilink($editeurStr, $editeurUrl);
        }

        if ($newEditeur !== $editeur) {
            $this->setParam('éditeur', $newEditeur);
            $this->addSummaryLog('±éditeur');
            $this->notCosmetic = true;
        }
    }

    /**
     * todo move (cf. Article/Lien web optimizing)
     *
     * @param string $publisherName
     *
     * @return string|null
     */
    public function predictPublisherWikiTitle(string $publisherName): ?string
    {
        try {
            $data = json_decode(file_get_contents(self::PUBLISHER_FRWIKI_FILENAME), true);
        } catch (\Throwable $e) {
            $this->log->error('Catch EDITOR_TITLES_FILENAME import '.$e->getMessage());
        }
        if (isset($data[$publisherName])) {
            return (string)urldecode($data[$publisherName]);
        }

        return null;
    }

    /**
     * {Cite book}:"edition" [ordinal number] => {ouvrage}::"numéro d'édition" (ou "réimpression" [année])
     * {Cite book}:origyear => {ouvrage}:"année première édition"
     * https://wstat.fr/template/index.php?title=Ouvrage&query=paramvalue&param=edition&limit=5000&searchtext=.&searchre=1
     * Pas mal de corrupted sur "éditions"
     * https://wstat.fr/template/index.php?title=Ouvrage&query=paramvalue&param=%C3%A9dition&limit=5000&searchtext=.&searchre=1
     * Note : impossible de faire getParam("éditeur-doublon")
     */
    private function processEditionCitebook(): void
    {
        // "édition" alias de "éditeur", mais OuvrageTemplateAlias:"édition"=>"numéro d'édition" à cause des doublons
        if (!empty($this->getParam("numéro d'édition"))) {
            $numeroEdition = $this->getParam("numéro d'édition");
            if (empty($this->getParam('éditeur'))
                && $this->getEditionOrdinalNumber($numeroEdition) === null
                && $this->isEditionYear($numeroEdition) === false
            ) {
                $this->setParam('éditeur', $numeroEdition);
                $this->unsetParam("numéro d'édition");
                $this->addSummaryLog('±éditeur');
            }
        }

        // Correction nom de paramètre selon type de valeur
        $this->correctReimpressionByParam("numéro d'édition");
        $this->correctReimpressionByParam("éditeur");
        $this->correctReimpressionByParam("édition");
    }

    private function correctReimpressionByParam(string $param): void
    {
        $editionNumber = $this->getParam($param);
        if (!empty($editionNumber) && $this->isEditionYear($editionNumber)) {
            $this->unsetParam($param);
            $this->setParam('réimpression', $editionNumber);
            $this->addSummaryLog('+réimpression');
            $this->notCosmetic = true;

            return;
        }

        $editionOrdinal = $this->getEditionOrdinalNumber($editionNumber);
        if (!empty($editionNumber) && !$this->isEditionYear($editionNumber) && $editionOrdinal) {
            $this->unsetParam($param);
            $this->setParam("numéro d'édition", $editionOrdinal);
            $this->addSummaryLog("±numéro d'édition");
            $this->notCosmetic = true;
        }
    }

    private function getEditionOrdinalNumber(?string $str): ?string
    {
        if (!$str) {
            return null;
        }
        // {{5e}}
        if (preg_match('#^\{\{([0-9]+)e\}\}$#', $str, $matches)) {
            return $matches[1];
        }
        // "1st ed."
        if (preg_match(
            '#^([0-9]+) ?(st|nd|rd|th|e|ème)? ?(ed|ed\.|edition|reprint|published|publication)?$#i',
            $str,
            $matches
        )
        ) {
            return $matches[1];
        }

        return null;
    }

    private function isEditionYear(string $str): bool
    {
        if (preg_match('#^[0-9]{4}$#', $str) && intval($str) > 1700 && intval($str) < 2025) {
            return true;
        }

        return false;
    }
}
