<?php


namespace App\Domain;


use App\Domain\Models\Wiki\OuvrageTemplate;

/**
 * TODO AbstractProcess
 * TODO observer/event (log, MajorEdition)
 * Class OuvrageProcess
 */
class OuvrageProcess
{
    protected $original;
    private $wikiPageTitle;
    private $log = [];
    private $ouvrage;
    private $currentTask;

    public function __construct(OuvrageTemplate $ouvrage, $wikiPageTitle = null)
    {
        $this->original = $ouvrage;
        $this->ouvrage = clone $ouvrage;
        $this->wikiPageTitle = ($wikiPageTitle) ?? null;
    }

    public function doTasks()
    {
        $this->currentTask = 'start';
        $this->parametersErrorFromHydrate();

        $this->currentTask = 'titre';
        $oldtitre = $this->getParam('titre');
        $this->deWikifyUrlLink('titre');
        $this->currentTask = 'upperCase title';
        $this->upperCaseFirstLetter('titre');
        $this->typoDeuxPoints('titre');
        if ($this->getParam('titre') !== $oldtitre) {
            $this->log('±titre');
        }

        $this->currentTask = 'titre chapitre';
        $this->valideNumeroChapitre();
        $this->deWikifyUrlLink('titre chapitre');
        $this->upperCaseFirstLetter('titre chapitre');
        $this->typoDeuxPoints('titre chapitre');

        $this->currentTask = 'éditeur';
        //        $this->deWikifyEditeur();

        $this->currentTask = 'suite';
        $this->dateIsYear();
        $this->predictFormatByPattern();

        //        $tasks = [
        //            ['deWikifyUrlLink', 'title'],
        //            ['deWikifyUrlLink', 'titre chapitre'],
        //            ['upperCaseFirstLetter', 'title']
        ////            'blabla',
        //        ];
        //
        //        foreach ($tasks as $task ) {
        //            if(!is_array($task)) {
        //                $this->currentTask = $task;
        //                $this->{$task}();
        //                continue;
        //            }
        //            $this->currentTask = implode(' ', $task);
        //            $this->{$task[0]}($task[1]);
        //        }
    }

    /**
     * todo: move to AbstractWikiTemplate ?
     * Correction des parametres rejetés à l'hydratation données
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
     * @param $name
     *
     * @return string|null
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
        $this->log[] = $this->currentTask.' : '.$text;
    }

    /**
     * déwikification du titre : consensus Bistro 27 août 2011
     * idem  'titre chapitre'
     *
     * @throws \Exception
     */
    private function deWikifyUrlLink($param)
    {
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
        $newValue = TextUtil::mb_ucfirst(trim($this->getParam($param)));
        $this->setParam($param, $newValue);

        // Typo : sous-titre précédé de " : "
        // provoque bug regex détection lien externe http://
        //        $titre = preg_replace('#[ ]*\:[ ]*#', ' : ', $titre);
        // todo typo : déplacer sous-titre dans [sous-titre]
    }

    /**
     * Typo internationale 'titre : sous-titre'
     * Todo? déplacer sous-titre dans 'sous-titre' ?
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
        // "12" ou "VI"
        if (preg_match('#^[0-9IVXL]+$#i', $value) > 0) {
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

    // ----------------------
    // ----------------------
    // ----------------------

    private function dateIsYear()
    {
        if (($date = $this->getParam('date'))) {
            if (preg_match('#^\-?[12][0-9][0-9][0-9]$#', $date)) {
                $this->setParam('année', $date);
                $this->unsetParam('date');
                $this->log('date=>année');
            }
        }
    }

    private function predictFormatByPattern()
    {
        if (($value = $this->getParam('format'))) {
            // predict if 'format électronique'
            if (preg_match('#(pdf|epub|html|kindle|audio|\{\{aud|jpg)#i', $value) > 0) {
                $this->setParam('format électronique', $value);
                $this->unsetParam('format');
                $this->log('format:électronique?');

                return;
            }
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
     * @throws \Exception
     */
    public function checkMajorEdit(): bool
    {
        // compare object attributes (==)
        if ($this->original == $this->ouvrage) {
            return false;
        }
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
        unset($datOriginal['langue']);
        unset($datOuvrage['langue']);

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

    private function deWikifyEditeur()
    {
        // Déconseillé: 'lien éditeur' (obsolete)
        // todo 'lien éditeur' affiché 1x par page

        // [[éditeur]]
        $old = $this->getParam('éditeur');
        // - "éd."
        $new = trim(str_ireplace(['éd.', 'ed.', 'Éd.', 'édit.', 'Édit.', '(éd.)', '(ed.)'], '', $old));
        // todo gérer "Ed. de ...."
        $new = trim($new);
        if ($old !== $new) {
            $this->setParam('éditeur', $new);
            $this->log('±éditeur');
        }
    }

    //----

    /**
     * underTwoAuthors by MartinS
     * Return true if 0 or 1 author in $author; false otherwise
     *
     * @param $author
     *
     * @return bool
     */
    private function underTwoAuthors($author)
    {
        $chars = count_chars(trim($author));
        if ($chars[ord(";")] > 0 || $chars[ord(" ")] > 2 || $chars[ord(",")] > 1) {
            return false;
        }

        return true;
    }

}
