<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use Exception;

/**
 * todo Extract other Handlers from this class.
 */
class TitleHandler extends AbstractOuvrageHandler
{
    public function handle()
    {
        $oldtitre = $this->ouvrage->getParam('titre');
        $this->langInTitle();
        $this->extractExternalLink('titre');
        $this->upperCaseFirstLetter('titre');
        $this->typoDeuxPoints('titre');

        $this->extractSubTitle();

        // 20-11-2019 : Retiré majuscule à sous-titre

        if ($this->ouvrage->getParam('titre') !== $oldtitre) {
            $this->optiStatus->addSummaryLog('±titre');
        }

        $this->valideNumeroChapitre();
        $this->extractExternalLink('titre chapitre');
        $this->upperCaseFirstLetter('titre chapitre');
    }

    /**
     * - {{lang|...}} dans titre => langue=... puis titre nettoyé
     * langue=L’utilisation de ce paramètre permet aussi aux synthétiseurs vocaux de reconnaître la langue du titre de
     * l’ouvrage.
     * Il est possible d'afficher plusieurs langues, en saisissant le nom séparé par des espaces ou des virgules.
     * La première langue doit être celle du titre.
     * @throws Exception
     */
    protected function langInTitle(): void
    {
        if (preg_match(
                '#^{{ ?(?:lang|langue) ?\| ?([a-z-]{2,5}) ?\| ?(?:texte=)?([^{}=]+)(?:\|dir=rtl)?}}$#i',
                $this->ouvrage->getParam('titre'),
                $matches
            ) > 0
        ) {
            $lang = trim($matches[1]);
            $newtitre = str_replace($matches[0], trim($matches[2]), $this->ouvrage->getParam('titre'));

            // problème : titre anglais de livre français
            // => conversion {{lang}} du titre seulement si langue= défini
            // opti : restreindre à ISBN zone 2 fr ?
            if ($lang === $this->ouvrage->getParam('langue')) {
                $this->ouvrage->setParam('titre', $newtitre);
                $this->optiStatus->addSummaryLog('°titre');
            }

            // desactivé à cause de l'exception décrite ci-dessus
            // si langue=VIDE : ajout langue= à partir de langue titre
            //            if (self::WIKI_LANGUAGE !== $lang && empty($this->ouvrage->getParam('langue'))) {
            //                $this->setParam('langue', $lang);
            //                $this->log('+langue='.$lang);
            //            }
        }
    }

    /**
     * Bool ?
     * Retire lien externe du titre : consensus Bistro 27 août 2011
     * idem  'titre chapitre'.
     * Lien externe déplacé éventuellement dans "lire en ligne"
     *
     *
     * @throws Exception
     */
    protected function extractExternalLink(string $param): void
    {
        if (empty($this->ouvrage->getParam($param))) {
            return;
        }
        if (preg_match('#^\[(http[^ \]]+) ([^]]+)]#i', $this->ouvrage->getParam($param), $matches) > 0) {
            $this->ouvrage->setParam($param, str_replace($matches[0], $matches[2], $this->ouvrage->getParam($param)));
            $this->optiStatus->addSummaryLog('±' . $param);

            if (in_array($param, ['titre', 'titre chapitre'])) {
                if (empty($this->ouvrage->getParam('lire en ligne'))) {
                    $this->ouvrage->setParam('lire en ligne', $matches[1]);
                    $this->optiStatus->addSummaryLog('+lire en ligne');

                    return;
                }
                $this->optiStatus->addSummaryLog('autre lien externe: ' . $matches[1]);
            }
        }
    }

    protected function upperCaseFirstLetter($param)
    {
        if (!$this->ouvrage->hasParamValue($param)) {
            return;
        }
        $newValue = TextUtil::mb_ucfirst(trim($this->ouvrage->getParam($param)));
        $this->ouvrage->setParam($param, $newValue);
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
        $origin = $this->ouvrage->getParam($param) ?? '';
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
            $this->ouvrage->setParam($param, $strTitle);
            $this->optiStatus->addSummaryLog(sprintf(':%s', $param));
        }
    }

    protected function extractSubTitle(): void
    {
        // FIXED bug [[fu:bar]]
        if (
            !$this->ouvrage->getParam('titre')
            || WikiTextUtil::isWikify($this->ouvrage->getParam('titre'))
        ) {
            return;
        }

        if (!$this->detectColon('titre')) {
            return;
        }
        // Que faire si déjà un sous-titre ?
        if ($this->ouvrage->hasParamValue('sous-titre')) {
            return;
        }

        // titre>5 and sous-titre>5 and sous-titre<40
        if (preg_match(
                '#^(?<titre>[^:]{5,}):(?<st>.{5,40})$#',
                $this->ouvrage->getParam('titre') ?? '',
                $matches
            ) > 0) {
            $this->ouvrage->setParam('titre', trim($matches['titre']));
            $this->ouvrage->setParam('sous-titre', trim($matches['st']));
            $this->optiStatus->addSummaryLog('>sous-titre');
        }
    }

    protected function detectColon($param): bool
    {
        // > 0 don't count a starting colon ":bla"
        return $this->ouvrage->hasParamValue($param) && mb_strrpos($this->ouvrage->getParam('titre'), ':') > 0;
    }


    protected function valideNumeroChapitre()
    {
        $value = $this->ouvrage->getParam('numéro chapitre');
        if (empty($value)) {
            return;
        }
        // "12" ou "VI", {{II}}, II:3
        if (preg_match('#^[0-9IVXL\-.:{}]+$#i', $value) > 0) {
            return;
        }
        // déplace vers "titre chapitre" ?
        if (!$this->ouvrage->getParam('titre chapitre')) {
            $this->ouvrage->unsetParam('numéro chapitre');
            $this->ouvrage->setParam('titre chapitre', $value);
        }
        $this->optiStatus->addSummaryLog('≠numéro chapitre');
    }
}