<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageEdit;

use LogicException;

/**
 * For substantive or ambiguous modifications done.
 * Move to /Domain ?
 */
class ImportantSummaryCreator
{
    /**
     * @var PageWorkStatus
     */
    protected $pageWorkStatus;

    public function __construct(PageWorkStatus $pageWorkStatus)
    {
        $this->pageWorkStatus = $pageWorkStatus;
    }

    public function processPageOuvrage(array $ouvrageData): void
    {
        if ($this->pageWorkStatus->getTitle() !== $ouvrageData['page']) {
            throw new LogicException('Title mismatch between PageWorkStatus and ouvrageData');
        }

        $this->parseParamNotCorrected($ouvrageData['opti']);

        $this->parseOpti($ouvrageData['opti']);

        $this->parseModifs($ouvrageData['modifs']);
    }

    protected function parseParamNotCorrected(string $opti)
    {
        // paramètre inconnu
        if (preg_match_all(
                "#\|[^|]+<!-- ?(PARAMETRE [^>]+ N'EXISTE PAS|VALEUR SANS NOM DE PARAMETRE|ERREUR [^>]+) ?-->#",
                $opti,
                $matches
            ) > 0
        ) {
            foreach ($matches[0] as $line) {
                $this->addErrorWarning($line);
            }
            $this->addSummaryTag('paramètre non corrigé');
        }
    }

    protected function addErrorWarning(string $string)
    {
        $this->pageWorkStatus->addErrorWarning($string);
    }

    protected function addSummaryTag(string $string)
    {
        $this->pageWorkStatus->addSummaryTag($string);
    }

    protected function parseOpti(string $opti): void
    {
        // ISBN invalide
        if (preg_match("#isbn invalide ?=[^|}]+#i", $opti, $matches) > 0) {
            $this->addErrorWarning($matches[0]);
            $this->addSummaryTag('ISBN invalide 💩');
        }
    }

    protected function parseModifs(string $modifs): void
    {
        // Edits avec ajout conséquent de donnée
        if (preg_match('#distinction des auteurs#', $modifs) > 0) {
            $this->pageWorkStatus->botFlag = false;
            $this->addSummaryTag('distinction auteurs 🧠');
        }
        // prédiction paramètre correct
        if (preg_match('#[^,]+(=>|⇒)[^,]+#', $modifs, $matches) > 0) {
            $this->addSummaryTag($matches[0]);
        }
        if (preg_match('#\+\+sous-titre#', $modifs) > 0) {
            $this->addSummaryTag('+sous-titre');
        }
        if (preg_match('#\+lieu#', $modifs) > 0) {
            $this->addSummaryTag('+lieu');
        }
        if (preg_match('#tracking#', $modifs) > 0) {
            $this->addSummaryTag('tracking');
        }
        if (preg_match('#présentation en ligne#', $modifs) > 0) {
            $this->addSummaryTag('+présentation en ligne✨');
        }
        if (preg_match('#distinction auteurs#', $modifs) > 0) {
            $this->addSummaryTag('auteurs 🧠');
        }
        if (preg_match('#\+lire en ligne#', $modifs) > 0) {
            $this->addSummaryTag('+lire en ligne✨');
        }
        if (preg_match('#\+lien #', $modifs) > 0) {
            $this->addSummaryTag('wikif');
        }
        if (preg_match('#\+éditeur#', $modifs) > 0) {
            $this->addSummaryTag('+éditeur');
        }
        if (preg_match('#\+lien éditeur#', $modifs) > 0) {
            $this->addSummaryTag('wikif');
        }
        $this->parseBNFdata($modifs);
    }

    // mention BnF si ajout donnée + ajout identifiant bnf=
    protected function parseBNFdata(string $modifs): void
    {
        if (!empty($this->pageWorkStatus->importantSummary) && preg_match('#BnF#i', $modifs) > 0) {
            $this->addSummaryTag('©[[BnF]]');
        }
    }
}