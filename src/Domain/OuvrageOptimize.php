<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;

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
        $this->currentTask = 'start';
        $this->parametersErrorFromHydrate();
        $this->processTitle();
        $this->processEditeur();
        $this->processDates();
        $this->externalTemplates();
        $this->currentTask = 'suite';
        $this->predictFormatByPattern();

        $this->processIsbn();

        $this->GoogleBookURL('lire en ligne');
        $this->GoogleBookURL('présentation en ligne');

        return $this;
    }

    /**
     * Validate or correct ISBN.
     *
     * @throws \Exception
     */
    private function processIsbn()
    {
        if (empty($this->getParam('isbn'))) {
            return;
        }
        $isbn = $this->getParam('isbn');
        $isbnMachine = new IsbnFacade($isbn);

        try {
            $isbnMachine->validate();
            $isbn13 = $isbnMachine->format('ISBN-13');
        } catch (\Exception $e) {
            // ISBN invalide
            // TODO : bot ISBN invalide
            $this->log(
                sprintf(
                    'ISBN invalide: %s',
                    $isbnMachine->translateMessageFr($e->getMessage())
                )
            );

            return;
        }
        // ISBN-13 valide
        $this->setParam('isbn', $isbn13);

        if ($isbnMachine::isbn2ean($isbn13) === $isbn13) {
            $this->log('ISBN style');

            return;
        }
        $this->log('±ISBN');
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
     * @throws \Exception
     */
    private function langInTitle()
    {
        if (preg_match(
                '#^\{\{ ?(?:lang|langue) ?\| ?([a-z-]{2,5}) ?\| ?(?:texte=)?([^\{\}=]+)(?:\|dir=rtl)?\}\}$#i',
                $this->getParam('titre'),
                $matches
            ) > 0
        ) {
            $lang = trim($matches[1]);
            $newtitre = str_replace($matches[0], trim($matches[2]), $this->getParam('titre'));
            $this->setParam('titre', $newtitre);
            $this->log('°titre');
            if (empty($this->getParam('langue'))) {
                $this->setParam('langue', $matches[1]);
                $this->log('+lang='.$matches[1]);
            }
        }
    }

    private function processDates()
    {
        // dewikification
        $params = ['date', 'année', 'mois', 'jour'];
    }

    /**
     * todo: move to AbstractWikiTemplate ?
     * Correction des parametres rejetés à l'hydratation données.
     *
     * @throws \Exception
     */
    private function parametersErrorFromHydrate()
    {
        if (empty($this->ouvrage->parametersErrorFromHydrate)) {
            return;
        }
        $allParamsAndAlias = $this->ouvrage->getParamsAndAlias();

        foreach ($this->ouvrage->parametersErrorFromHydrate as $name => $value) {
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
     * @throws \Exception
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

    public function log(string $text)
    {
        $this->log[] = $text;
    }

    /**
     * Bool ?
     * déwikification du titre : consensus Bistro 27 août 2011
     * idem  'titre chapitre'.
     *
     * @throws \Exception
     */
    private function deWikifyExternalLink(string $param): void
    {
        if (empty($this->getParam($param))) {
            return;
        }
        if (preg_match('#^\[(http[^ \]]+) ([^\]]+)\]#i', $this->getParam($param), $matches) > 0) {
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

        // Typo : sous-titre précédé de " : "
        // provoque bug regex détection lien externe http://
        //        $titre = preg_replace('#[ ]*\:[ ]*#', ' : ', $titre);
        // todo typo : déplacer sous-titre dans [sous-titre]
    }

    /**
     * Typo internationale 'titre : sous-titre'
     * https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Le_Bistro/13_janvier_2016#Modif_du_mod%C3%A8le:Ouvrage.
     *
     * @param $param
     *
     * @throws \Exception
     */
    private function typoDeuxPoints($param)
    {
        $new = preg_replace('#[ ]*\:[ ]*#', ' : ', $this->getParam($param));
        $this->setParam($param, $new);
    }

    private function valideNumeroChapitre()
    {
        $value = $this->getParam('numéro chapitre');
        if (empty($value)) {
            return;
        }
        // "12" ou "VI", {{II}}, II:3
        if (preg_match('#^[0-9IVXL\-\.\:\{\}]+$#i', $value) > 0) {
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
     * @throws \Exception
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
            $this->log('+{{plume}}');
        }

        // "extrait=bla" => {{citation bloc|bla}}
        if (!empty($this->getParam('extrait'))) {
            $extrait = $this->getParam('extrait');
            // todo bug {{citation bloc}} si "=" ou "|" dans texte de citation
            // Legacy : use {{début citation}} ... {{fin citation}}
            if (preg_match('#[=|\|]#', $extrait) > 0) {
                $this->ouvrage->externalTemplates[] = (object) [
                    'template' => 'début citation',
                    '1' => '',
                    'raw' => '{{début citation}}'.$extrait.'{{fin citation}}',
                ];
                $this->log('+{{début citation}}');
            } else {
                // StdClass
                $this->ouvrage->externalTemplates[] = (object) [
                    'template' => 'citation bloc',
                    '1' => $extrait,
                    'raw' => '{{extrait|'.$extrait.'}}',
                ];
                $this->log('+{{extrait}}');
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
            $this->log('+{{commentaire}}');
        }
    }

    // ----------------------
    // ----------------------
    // ----------------------

    /**
     * todo : invisible/inutile ? (LUA).
     *
     * @throws \Exception
     */
    //    private function dateIsYear()
    //    {
    //        if (($date = $this->getParam('date'))) {
    //            if (preg_match('#^\-?[12][0-9][0-9][0-9]$#', $date)) {
    //                $this->setParam('année', $date);
    //                $this->unsetParam('date');
    //                $this->log('date=>année');
    //            }
    //        }
    //    }

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
                    '#(ill\.|couv\.|in\-[0-9]|in-fol|poche|broché|relié|\{\{unité|\{\{Dunité|[0-9]{2} ?cm|\|cm\}\}|vol\.|A4)#i',
                    $value
                ) > 0
            ) {
                $this->setParam('format livre', $value);
                $this->unsetParam('format');
                $this->log('format:livre?');
            }
            // Certainement 'format électronique'...
        }
    }

    /**
     * @return bool
     *
     * @throws \Exception
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

    private function iswikified(string $str)
    {
        if (preg_match('#\[\[.+\]\]#', $str) > 0) {
            return true;
        }

        return false;
    }

    /**
     * todo : vérif lien rouge
     * todo 'lien éditeur' affiché 1x par page
     * opti : Suppression lien éditeur si c'est l'article de l'éditeur.
     *
     * @throws \Exception
     */
    private function processEditeur()
    {
        $this->currentTask = 'start';
        $editeur = $this->getParam('éditeur');
        if (empty($editeur)) {
            return;
        }

        // [[éditeur]]
        if (preg_match('#\[\[([^\|]+)\]\]#', $editeur, $matches) > 0) {
            $editeurUrl = $matches[1];
        }
        // [[bla|éditeur]]
        if (preg_match('#\[\[([^\]\|]+)\|.+\]\]#', $editeur, $matches) > 0) {
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
        }
    }
}
