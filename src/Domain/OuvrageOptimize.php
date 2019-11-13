<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\FileManager;
use Exception;
use Throwable;

/**
 * Legacy.
 * TODO move methods to OuvrageClean setters
 * TODO AbstractProcess
 * TODO observer/event (log, MajorEdition)
 * Class OuvrageProcess.
 */
class OuvrageOptimize
{
    protected $original;

    private $wikiPageTitle;

    private $log = [];

    public $notCosmetic = false;

    private $ouvrage;

    private $currentTask;

    // todo inject TextUtil + ArticleVersion ou WikiRef
    public function __construct(OuvrageTemplate $ouvrage, $wikiPageTitle = null)
    {
        $this->original = $ouvrage;
        $this->ouvrage = clone $ouvrage;
        $this->wikiPageTitle = ($wikiPageTitle) ?? null;
    }

    public function doTasks(): self
    {
        $this->parametersErrorFromHydrate();

        $this->processAuthors();

        $this->processTitle();
        $this->processEditeur();
        $this->processDates();
        $this->externalTemplates();
        $this->predictFormatByPattern();

        $this->processIsbn();
        $this->processLang();
        $this->processLocation(); // 'lieu'

        $this->GoogleBookURL('lire en ligne');
        $this->GoogleBookURL('présentation en ligne');

        return $this;
    }

    /**
     * Todo: injection dep.
     *
     * @throws Exception
     */
    private function processLocation()
    {
        $location = $this->getParam('lieu');
        if (empty($location)) {
            return;
        }

        // typo and unwikify
        $memo = $location;
        $location = WikiTextUtil::unWikify($location);
        $location = TextUtil::mb_ucfirst($location);
        if($memo !== $location){
            $this->setParam('lieu', $location);
            $this->log('±lieu');
            $this->notCosmetic = true;
        }

        // french translation : "London"->"Londres"
        $manager = new FileManager();
        $row = $manager->findCSVline(__DIR__.'/resources/traduction_ville.csv', $location);
        if (!empty($row) || !empty($row[1])) {
            $this->setParam('lieu', $row[1]);
            $this->log('lieu francisé');
            $this->notCosmetic = true;
        }

    }

    /**
     * @throws Exception
     */
    private function processAuthors()
    {
        $this->distinguishAuthors();
        //$this->fusionFirstNameAndName(); // desactived : no consensus
    }

    /**
     * desactived (no consensus).
     */
    //    private function fusionFirstNameAndName()
    //    {
    //        // Fusion prénom+nom -> auteur, si :
    //        // (prénom simple ou prénom avec initiale) ET nom simple
    //        // cosmétique
    //        for ($i = 1; $i < 5; ++$i) {
    //            $prenom = $this->getParam('prénom'.$i) ?? false;
    //            $nom = $this->getParam('nom'.$i) ?? false;
    //            if ($prenom && $nom) {
    //                // prénom constitué de "mot A." ?
    //                $initialePrenom = preg_match('#^[^ .]+ [A-Z]\.$#', $prenom);
    //
    //                // fusion prénom1+nom1 => auteur1
    //                if (($initialePrenom || !strpos($prenom, ' ')) && !strpos($nom, ' ')) {
    //                    $this->setParam('auteur'.$i, sprintf('%s %s', $prenom, $nom));
    //                    $this->unsetParam('prénom'.$i);
    //                    $this->unsetParam('nom'.$i);
    //                    //                    $this->log('>auteur'.$i); // cosmétique
    //                }
    //            }
    //        }
    //    }

    /**
     * Detect and correct multiple authors in same parameter.
     * Like "auteurs=J. M. Waller, M. Bigger, R. J. Hillocks".
     *
     * @throws Exception
     */
    private function distinguishAuthors()
    {
        // merge params of author 1
        $auteur1 = $this->getParam('auteur') ?? '';
        $auteur1 .= $this->getParam('auteurs') ?? '';
        $auteur1 .= $this->getParam('prénom1') ?? '';
        $auteur1 .= ' '. $this->getParam('nom1') ?? '';
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
            for ($i = 0; $i < count($res); ++$i) {
                $this->setParam(sprintf('auteur%s', $i + 1), $res[$i]);
            }
            $this->log('distinction auteurs');
            $this->major=true;
            $this->notCosmetic = true;
        }
    }

    /**
     * todo: move/implement.
     */
    private function processLang()
    {
        try {
            $lang = $this->getParam('langue') ?? null;
        } catch (Exception $e) {
            dump('ERROR '.$e);

            return;
        }
        if ($lang) {
            $lang2 = str_ireplace(
                ['française', 'français', 'anglaise', 'anglais', 'japonais', 'allemand', 'espagnol'],
                ['fr', 'fr', 'en', 'en', 'ja', 'de', 'es'],
                $lang
            );
            if ($lang !== $lang2) {
                $this->setParam('langue', $lang2);
                $this->log('±langue');
            }
        }
    }

    /**
     * Validate or correct ISBN.
     *
     * @throws Exception
     */
    private function processIsbn()
    {
        $isbn = $this->getParam('isbn') ?? '';

        if (empty($isbn)) {
            return;
        }

        $isbnMachine = new IsbnFacade($isbn);

        try {
            $isbnMachine->validate();
            $isbn13 = $isbnMachine->format('ISBN-13');
        } catch (Throwable $e) {
            // ISBN not validated
            // TODO : bot ISBN invalide (queue, message PD...)
            $this->setParam(
                'isbn invalide',
                sprintf(
                    '%s %s',
                    $isbn,
                    $e->getMessage() ?? ''
                )
            );
            $this->log(
                sprintf(
                    'ISBN invalide: %s',
                    $e->getMessage()
                )
            );
            $this->notCosmetic = true;

            // TODO log file ISBNinvalide
            return;
        }

        // Langue d'après ISBN : restreint à 'fr' pour fiabilité frwiki
        $langFromIsbn = $isbnMachine->getCountryShortName();
        if ($langFromIsbn && empty($this->getParam('langue')) && 'fr' === $langFromIsbn) {
            $this->setParam('langue', $langFromIsbn);
            $this->log('+langue:'.$langFromIsbn);
            $this->notCosmetic = true;
        }

        // ISBN 10 ?
        if (10 === strlen(str_replace('-', '', $isbn)) && !$this->getParam('isbn10')) {
            $this->setParam('isbn10', $isbn);
            $this->log('isbn10');
            $this->notCosmetic = true;
        }

        // ISBN correction
        if ($isbn13 !== $isbn) {
            $this->setParam('isbn', $isbn13);
            $this->log('ISBN');
            $this->notCosmetic = true;
        }
    }

    private function processTitle()
    {
        $this->currentTask = 'titres';

        $oldtitre = $this->getParam('titre');
        $this->langInTitle();
        $this->deWikifyExternalLink('titre');
        $this->upperCaseFirstLetter('titre');
        $this->typoDeuxPoints('titre');
        // todo :extract sous-titre
        $this->extractSubTitle();

        $this->upperCaseFirstLetter('sous-titre');

        if ($this->getParam('titre') !== $oldtitre) {
            $this->log('±titre');
        }

        $this->currentTask = 'titre chapitre';
        $this->valideNumeroChapitre();
        $this->deWikifyExternalLink('titre chapitre');
        $this->upperCaseFirstLetter('titre chapitre');
        $this->typoDeuxPoints('titre chapitre');
    }

    private function detectColon($param): bool
    {
        // > 0 don't count a starting colon ":bla"
        if (!empty($this->getParam($param)) && mb_strrpos($this->getParam('titre'), ':') > 0) {
            return true;
        }

        return false;
    }

    private function extractSubTitle(): void
    {
        if (!$this->detectColon('titre')) {
            return;
        }
        // Que faire si déjà un sous-titre ?
        if (!empty($this->getParam('sous-titre'))) {
            return;
        }

        // titre>3 and sous-titre>5
        if (preg_match('#^(?<titre>[^:]{3,}):(?<st>.{5,})$#', $this->getParam('titre'), $matches) > 0) {
            $this->setParam('titre', trim($matches['titre']));
            $this->setParam('sous-titre', trim($matches['st']));
            $this->log('>sous-titre');
        }
    }

    private function googleBookUrl($param)
    {
        if (!empty($this->getParam($param))
            && GoogleLivresTemplate::isGoogleBookURL($this->getParam($param))
        ) {
            $goo = GoogleLivresTemplate::createFromURL($this->getParam($param));
            if (is_object($goo)) {
                $this->setParam($param, $goo->serialize());
                $this->log('{Google}');
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
    private function langInTitle()
    {
        if (preg_match(
                '#^{{ ?(?:lang|langue) ?\| ?([a-z-]{2,5}) ?\| ?(?:texte=)?([^{}=]+)(?:\|dir=rtl)?}}$#i',
                $this->getParam('titre'),
                $matches
            ) > 0
        ) {
            $lang = trim($matches[1]);
            $newtitre = str_replace($matches[0], trim($matches[2]), $this->getParam('titre'));
            $this->setParam('titre', $newtitre);
            $this->log('°titre');
            if (empty($this->getParam('langue'))) {
                $this->setParam('langue', $lang);
                $this->log('+langue='.$lang);
            }
        }
    }

    private function processDates()
    {
        try {
            $this->dateIsYear();
        } catch (Exception $e) {
            dump($e);
        }
        // dewikification TODO
        $params = ['date', 'année', 'mois', 'jour'];
    }

    /**
     * todo: move to AbstractWikiTemplate ?
     * Correction des parametres rejetés à l'hydratation données.
     *
     * @throws Exception
     */
    private function parametersErrorFromHydrate()
    {
        if (empty($this->ouvrage->parametersErrorFromHydrate)) {
            return;
        }
        $allParamsAndAlias = $this->ouvrage->getParamsAndAlias();

        foreach ($this->ouvrage->parametersErrorFromHydrate as $name => $value) {
            if (!is_string($name)) {
                // example : 1 => "ouvrage collectif" from |ouvrage collectif|
                continue;
            }
            $maxDistance = 1;
            if (strlen($name) >= 4) {
                $maxDistance = 2;
            }
            if (strlen($name) >= 8) {
                $maxDistance = 3;
            }

            $predName = TextUtil::predictCorrectParam($name, $allParamsAndAlias, $maxDistance);
            if ($predName && strlen($name) >= 4) {
                if (empty($this->getParam($predName))) {
                    $this->setParam($predName, $value);
                    $predName = $this->ouvrage->getAliasParam($predName);
                    $this->log("$name => $predName");
                    $this->notCosmetic = true;
                    unset($this->ouvrage->parametersErrorFromHydrate[$name]);
                }
            }
        }
    }

    /**
     * TODO : return "" instead of null ?
     *
     * @param $name
     *
     * @return string|null
     *
     * @throws Exception
     */
    private function getParam(string $name): ?string
    {
        return $this->ouvrage->getParam($name);
    }

    private function setParam($name, $value)
    {
        // todo : overwrite setParam() ?
        if (!empty($value) || $this->ouvrage->getParam($name)) {
            $this->ouvrage->setParam($name, $value);
        }
    }

    private function log(string $string): void
    {
        if (!empty($string)) {
            $this->log[] = trim($string);
        }
    }

    /**
     * Bool ?
     * déwikification du titre : consensus Bistro 27 août 2011
     * idem  'titre chapitre'.
     *
     * @param string $param
     *
     * @throws Exception
     */
    private function deWikifyExternalLink(string $param): void
    {
        if (empty($this->getParam($param))) {
            return;
        }
        if (preg_match('#^\[(http[^ \]]+) ([^]]+)]#i', $this->getParam($param), $matches) > 0) {
            $this->setParam($param, str_replace($matches[0], $matches[2], $this->getParam($param)));
            $this->log('±'.$param);

            if (in_array($param, ['titre', 'titre chapitre'])) {
                if (empty($this->getParam('lire en ligne'))) {
                    $this->setParam('lire en ligne', $matches[1]);
                    $this->log('+lire en ligne');

                    return;
                }
                $this->log('autre lien externe: '.$matches[1]);
            }
        }
    }

    private function upperCaseFirstLetter($param)
    {
        if (empty($this->getParam($param))) {
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
    private function typoDeuxPoints($param)
    {
        $origin = $this->getParam($param) ?? '';
        if (empty($origin)) {
            return;
        }
        $origin = TextUtil::replaceNonBreakingSpaces($origin);

        $strTitle = $origin;
        // CORRECTING TYPO FANTASY OF SUBTITLE

        // Replace first '.' by ':' if no ': ' and no numbers around (as PHP 7.3)
        // exlude pattern "blabla... blabla"
        if (!mb_strpos(':', $strTitle) && preg_match('#[^0-9.]{5,}\. ?[^0-9.]{5,}#', $strTitle) > 0) {
            $strTitle = preg_replace('#([^0-9]{5,})\. ?([^0-9)]{5,})#', '$1 : $2', $strTitle);
            // opti : replace all '.' ?
        }

        // Replace ' - ' or ' / ' (spaced!) by ' : ' if no ':' and no numbers after (as PHP 7.3 or 1939-1945)
        if (!mb_strpos(':', $strTitle) && preg_match('#.{6,} ?[-/] ?[^0-9)]{6,}#', $strTitle) > 0) {
            $strTitle = preg_replace('#(.{6,}) [-/] ([^0-9)]{6,})#', '$1 : $2', $strTitle);
        }

        // international typo style " : " (first occurrence)
        $strTitle = preg_replace('#[ ]*:[ ]*#', ' : ', $strTitle);

        if ($strTitle !== $origin) {
            $this->setParam($param, $strTitle);
            $this->log(sprintf(':%s', $param));
        }
    }

    private function valideNumeroChapitre()
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
        $this->log('≠numéro chapitre');
    }

    private function unsetParam($name)
    {
        $this->ouvrage->unsetParam($name);
    }

    /**
     * -----------------------------------------------------------
     *              TASKS
     * --------------------------------------------------------.
     */

    /**
     * TODO move+refac
     * TODO PlumeTemplate CommentaireBiblioTemplate  ExtraitTemplate
     * Probleme {{commentaire biblio}} <> {{commentaire biblio SRL}}
     * Generate supplementary templates from obsoletes params.
     *
     * @throws Exception
     */
    protected function externalTemplates()
    {
        // "plume=bla" => {{plume}}
        // =oui selon doc, mais testé OK avec "non"
        // todo detect duplication ouvrage/plume dans externalTemplate ?
        if (!empty($this->getParam('plume'))) {
            $plumeValue = $this->getParam('plume');
            $this->ouvrage->externalTemplates[] = (object) [
                'template' => 'plume',
                '1' => $plumeValue,
                'raw' => '{{plume}}',
            ];
            $this->unsetParam('plume');
            $this->log('{plume}');
        }

        // "extrait=bla" => {{citation bloc|bla}}
        if (!empty($this->getParam('extrait'))) {
            $extrait = $this->getParam('extrait');
            // todo bug {{citation bloc}} si "=" ou "|" dans texte de citation
            // Legacy : use {{début citation}} ... {{fin citation}}
            if (preg_match('#[=|]#', $extrait) > 0) {
                $this->ouvrage->externalTemplates[] = (object) [
                    'template' => 'début citation',
                    '1' => '',
                    'raw' => '{{début citation}}'.$extrait.'{{fin citation}}',
                ];
                $this->log('{début citation}');
            } else {
                // StdClass
                $this->ouvrage->externalTemplates[] = (object) [
                    'template' => 'citation bloc',
                    '1' => $extrait,
                    'raw' => '{{extrait|'.$extrait.'}}',
                ];
                $this->log('{extrait}');
            }

            $this->unsetParam('extrait');
        }

        // "commentaire=bla" => {{Commentaire biblio|1=bla}}
        if (!empty($this->getParam('commentaire'))) {
            $commentaire = $this->getParam('commentaire');
            $this->ouvrage->externalTemplates[] = (object) [
                'template' => 'commentaire biblio',
                '1' => $commentaire,
                'raw' => '{{commentaire biblio|'.$commentaire.'}}',
            ];
            $this->unsetParam('commentaire');
            $this->log('{commentaire}');
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
    private function dateIsYear()
    {
        $date = $this->getParam('date') ?? false;
        if ($date) {
            if (preg_match('#^-?[12][0-9][0-9][0-9]$#', $date)) {
                $this->setParam('année', $date);
                $this->unsetParam('date');
                $this->log('>année');
            }
        }
    }

    private function predictFormatByPattern()
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
                $this->log('format:livre?');
                $this->notCosmetic = true;
            }
            // Certainement 'format électronique'...
        }
    }

    /**
     * @return bool
     *
     * @throws Exception
     */
    public function checkMajorEdit(): bool
    {
        // Correction paramètre
        if ($this->ouvrage->parametersErrorFromHydrate !== $this->original->parametersErrorFromHydrate) {
            return true;
        }
        // Complétion langue ?
        if (!empty($this->getParam('langue')) && empty($this->original->getParam('langue'))) {
            return true;
        }
        // TODO replace conditions ci-dessous par event flagMajor()
        // Retire le param/value 'langue' (pas major si conversion nom langue)
        $datOuvrage = $this->ouvrage->toArray();
        $datOriginal = $this->original->toArray();
        unset($datOriginal['langue'], $datOuvrage['langue']);

        // Modification données
        if ($datOriginal !== $datOuvrage) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * @return OuvrageTemplate
     */
    public function getOuvrage(): OuvrageTemplate
    {
        return $this->ouvrage;
    }

    /**
     * todo : vérif lien rouge
     * todo 'lien éditeur' affiché 1x par page
     * opti : Suppression lien éditeur si c'est l'article de l'éditeur.
     *
     * @throws Exception
     */
    private function processEditeur()
    {
        $this->currentTask = 'start';
        $editeur = $this->getParam('éditeur');
        if (empty($editeur)) {
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

        // abréviations communes
        $editeurStr = WikiTextUtil::unWikify($editeur);
        $editeurStr = trim(
            str_ireplace(
                ['éd. de ', 'éd.', 'ed.', 'Éd. de ', 'Éd.', 'édit.', 'Édit.', '(éd.)', '(ed.)', 'Ltd.'],
                '',
                $editeurStr
            )
        );

        // "Éditions de la Louve" => "La Louve"
        if (preg_match('#([EeÉé]ditions? de )(la|le|l\')#iu', $editeurStr, $matches) > 0) {
            $editeurStr = str_replace($matches[1], '', $editeurStr);
        }

        // Déconseillé : 'lien éditeur' (obsolete 2019)
        if (!empty($this->getParam('lien éditeur'))) {
            if (empty($editeurUrl)) {
                $editeurUrl = $this->getParam('lien éditeur');
            }
            $this->log('-lien éditeur');
            $this->unsetParam('lien éditeur');
        }

        $newEditeur = $editeurStr;
        if (isset($editeurUrl) && $editeurUrl !== $editeurStr) {
            $newEditeur = '[['.$editeurUrl.'|'.$editeurStr.']]';
        }
        if (isset($editeurUrl) && $editeurUrl === $editeurStr) {
            $newEditeur = '[['.$editeurStr.']]';
        }

        if ($newEditeur !== $editeur) {
            $this->setParam('éditeur', $newEditeur);
            $this->log('±éditeur');
            $this->notCosmetic = true;
        }
    }
}
