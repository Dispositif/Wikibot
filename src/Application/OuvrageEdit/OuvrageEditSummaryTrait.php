<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageEdit;

use DateTime;
use function App\Application\mb_substr;

/**
 * Used only by OuvrageEditWorker.
 */
trait OuvrageEditSummaryTrait
{
    /* Beware !! $importantSummary also defined in OuvrageEditWorker */
    public $importantSummary = [];

    abstract protected function addErrorWarning(string $title, string $text): void;

    /**
     * Generate wiki edition summary.
     */
    protected function generateFinalSummary(): string
    {
        $prefix = $this->generatePrefix();
        $citeSummary = $this->getCiteSummary();

        $summary = sprintf(
            '%s [%s] %s %sx : %s',
            trim($prefix),
            str_replace('v', '', $this->pageWorkStatus->citationVersion),
            trim(self::TASK_NAME),
            $this->pageWorkStatus->nbRows,
            $citeSummary
        );

        $summary = $this->shrinkLongSummaryIfNoImportantDetailsToVerify($summary);
        $summary = $this->couldAddLuckMessage($summary);
        $this->log->notice($summary);

        return $summary;
    }

    /**
     * Shrink long summary if no important details to verify.
     */
    protected function shrinkLongSummaryIfNoImportantDetailsToVerify(string $summary): string
    {
        if (empty($this->pageWorkStatus->importantSummary)) {
            $length = strlen($summary);
            $summary = mb_substr($summary, 0, 80);
            $summary .= ($length > strlen($summary)) ? '…' : '';
        } else {
            $summary .= '…'; // ?
        }
        return $summary;
    }

    protected function couldAddLuckMessage(string $summary): string
    {
        if (!$this->pageWorkStatus->luckyState && (new DateTime())->format('H:i') === '11:11') {
            $this->pageWorkStatus->luckyState = true;
            $summary .= self::LUCKY_MESSAGE;
        }

        return $summary;
    }

    protected function generatePrefix(): string
    {
        $prefix = ($this->pageWorkStatus->botFlag) ? 'bot ' : '';
        $prefix .= (empty($this->pageWorkStatus->errorWarning)) ? '' : ' ⚠️';
        $prefix .= (empty($this->pageWorkStatus->featured_article)) ? '' : ' ☆'; // AdQ, BA

        return $prefix;
    }

    /**
     * Generate list of details about current bot edition.
     */
    protected function getCiteSummary(): string
    {
        // basic modifs
        $citeSummary = implode(' ', $this->pageWorkStatus->citationSummary);
        // replaced by list of modifs to verify by humans
        if (!empty($this->pageWorkStatus->importantSummary)) {
            $citeSummary = implode(', ', $this->pageWorkStatus->importantSummary);
        }
        return $citeSummary;
    }

    /**
     * For substantive or ambiguous modifications done.
     *
     * @param string $tag
     */
    protected function addSummaryTag(string $tag)
    {
        if (!in_array($tag, $this->pageWorkStatus->importantSummary)) {
            $this->pageWorkStatus->importantSummary[] = $tag;
        }
    }

    /**
     * todo extract. => responsability : pageWorkStatus + summary
     * Vérifie alerte d'erreurs humaines.
     */
    protected function addSummaryDataOnPageWorkStatus(array $ouvrageData): void
    {
        // paramètre inconnu
        if (preg_match_all(
                "#\|[^|]+<!-- ?(PARAMETRE [^>]+ N'EXISTE PAS|VALEUR SANS NOM DE PARAMETRE|ERREUR [^>]+) ?-->#",
                $ouvrageData['opti'],
                $matches
            ) > 0
        ) {
            foreach ($matches[0] as $line) {
                $this->addErrorWarning($ouvrageData['page'], $line);
            }
            //  $this->pageWorkStatus->botFlag = false;
            $this->addSummaryTag('paramètre non corrigé');
        }

        // ISBN invalide
        if (preg_match("#isbn invalide ?=[^|}]+#i", $ouvrageData['opti'], $matches) > 0) {
            $this->addErrorWarning($ouvrageData['page'], $matches[0]);
            $this->pageWorkStatus->botFlag = false;
            $this->addSummaryTag('ISBN invalide 💩');
        }

        // Edits avec ajout conséquent de donnée
        if (preg_match('#distinction des auteurs#', $ouvrageData['modifs']) > 0) {
            $this->pageWorkStatus->botFlag = false;
            $this->addSummaryTag('distinction auteurs 🧠');
        }
        // prédiction paramètre correct
        if (preg_match('#[^,]+(=>|⇒)[^,]+#', $ouvrageData['modifs'], $matches) > 0) {
            $this->pageWorkStatus->botFlag = false;
            $this->addSummaryTag($matches[0]);
        }
        if (preg_match('#\+\+sous-titre#', $ouvrageData['modifs']) > 0) {
            $this->pageWorkStatus->botFlag = false;
            $this->addSummaryTag('+sous-titre');
        }
        if (preg_match('#\+lieu#', $ouvrageData['modifs']) > 0) {
            $this->addSummaryTag('+lieu');
        }
        if (preg_match('#tracking#', $ouvrageData['modifs']) > 0) {
            $this->addSummaryTag('tracking');
        }
        if (preg_match('#présentation en ligne#', $ouvrageData['modifs']) > 0) {
            $this->addSummaryTag('+présentation en ligne✨');
        }
        if (preg_match('#distinction auteurs#', $ouvrageData['modifs']) > 0) {
            $this->addSummaryTag('distinction auteurs 🧠');
        }
        if (preg_match('#\+lire en ligne#', $ouvrageData['modifs']) > 0) {
            $this->addSummaryTag('+lire en ligne✨');
        }
        if (preg_match('#\+lien #', $ouvrageData['modifs']) > 0) {
            $this->addSummaryTag('wikif');
        }

        if (preg_match('#\+éditeur#', $ouvrageData['modifs']) > 0) {
            $this->addSummaryTag('éditeur');
        }
        //        if (preg_match('#\+langue#', $data['modifs']) > 0) {
        //            $this->addSummaryTag('langue');
        //        }

        // mention BnF si ajout donnée + ajout identifiant bnf=
        if (!empty($this->pageWorkStatus->importantSummary) && preg_match('#BnF#i', $ouvrageData['modifs'], $matches) > 0) {
            $this->addSummaryTag('©[[BnF]]');
        }
    }
}
