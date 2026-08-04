<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

/**
 * 'asin' retiré du modèle Wikipédia:Ouvrage le 26/08/2025 (cf. Discussion modèle:ASIN/Admissibilité).
 * Pour les livres, Amazon utilisait littéralement l'ISBN-10 (ou parfois l'EAN/ISBN-13) comme ASIN :
 * si la valeur passe le checksum ISBN, on la migre vers isbn/isbn2/isbn3. Sinon (vrai produit
 * Amazon, ex. "B0031Y..."), la valeur n'est pas récupérable en bibliographie : le paramètre est
 * simplement retiré.
 */
class AsinHandler extends AbstractOuvrageHandler
{
    private const ISBN_SLOTS = ['isbn', 'isbn2', 'isbn3'];

    public function handle()
    {
        $asin = trim((string)$this->getParam('asin'));
        if (empty($asin)) {
            return;
        }

        $isbnCandidate = $this->toValidIsbnOrNull($asin);

        if ($isbnCandidate === null) {
            $this->unsetParam('asin');
            $this->addSummaryLog('ASIN retiré (non convertible en ISBN)');

            return;
        }

        foreach (self::ISBN_SLOTS as $slot) {
            $existing = $this->getParam($slot);
            if ($existing && $this->normalizeIsbn($existing) === $isbnCandidate) {
                $this->unsetParam('asin');
                $this->addSummaryLog('ASIN retiré (doublon ISBN)');

                return;
            }
        }

        foreach (self::ISBN_SLOTS as $slot) {
            if (empty($this->getParam($slot))) {
                $this->setParam($slot, $isbnCandidate);
                $this->unsetParam('asin');
                $this->addSummaryLog(sprintf('ASIN converti en %s', $slot));

                return;
            }
        }

        // isbn/isbn2/isbn3 déjà tous occupés par des valeurs différentes : rien de sûr à faire
        $this->unsetParam('asin');
        $this->addSummaryLog('ASIN retiré (isbn/isbn2/isbn3 déjà occupés)');
    }

    /**
     * @return string|null ISBN nettoyé (sans tiret) si $asin est un ISBN-10 ou ISBN-13 valide, sinon null.
     */
    private function toValidIsbnOrNull(string $asin): ?string
    {
        $stripped = strtoupper($this->normalizeIsbn($asin));

        if (10 === strlen($stripped) && $this->isValidIsbn10($stripped)) {
            return $stripped;
        }

        if (13 === strlen($stripped) && $this->isValidIsbn13($stripped)) {
            return $stripped;
        }

        return null;
    }

    private function normalizeIsbn(string $isbn): string
    {
        return strtoupper((string)preg_replace('#[^0-9Xx]#', '', $isbn));
    }

    private function isValidIsbn10(string $isbn): bool
    {
        if (!preg_match('#^\d{9}[\dX]$#', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = ('X' === $isbn[$i]) ? 10 : (int)$isbn[$i];
            $sum += $digit * (10 - $i);
        }

        return 0 === $sum % 11;
    }

    private function isValidIsbn13(string $isbn): bool
    {
        if (!preg_match('#^97[89]\d{10}$#', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $digit = (int)$isbn[$i];
            $sum += (0 === $i % 2) ? $digit : $digit * 3;
        }

        return 0 === $sum % 10;
    }
}
