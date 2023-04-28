<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\WikiOptimizer\Handlers;


use App\Domain\IsbnFacade;
use Exception;
use Throwable;

class OuvrageIsbnHandler extends AbstractOuvrageHandler
{
    /**
     * Refac complexity (lines, 20 conditions)
     * Validate or correct ISBN.
     * @throws Exception
     */
    public function handle()
    {
        $isbn = $this->getParam('isbn') ?? '';
        if (empty($isbn)) {
            return;
        }

        // ISBN-13 à partir de 2007
        $year = $this->findBookYear();
        if ($year !== null && $year < 2007 && 10 === strlen($this->stripIsbn($isbn))) {
            // juste mise en forme ISBN-10 pour 'isbn'
            try {
                $isbnMachine = new IsbnFacade($isbn);
                // skip trigger_error() for deprecated method
                @$isbnMachine->validate();
                $isbn10pretty = $isbnMachine->format('ISBN-10');
                if ($isbn10pretty !== $isbn) {
                    $this->setParam('isbn', $isbn10pretty);
                    $this->addSummaryLog('ISBN10');
                    //                    $this->notCosmetic = true;
                }
            } catch (Throwable $e) {
                // ISBN not validated
                $this->setParam(
                    'isbn invalide',
                    sprintf('%s %s', $isbn, $e->getMessage() ?? '')
                );
                $this->addSummaryLog(sprintf('ISBN invalide: %s', $e->getMessage()));
                $this->optiStatus->setNotCosmetic(true);
            }

            return;
        }

        try {
            $isbnMachine = new IsbnFacade($isbn);
            // skip trigger_error() for deprecated method
            @$isbnMachine->validate();
            $isbn13 = @$isbnMachine->format('ISBN-13');
        } catch (Throwable $e) {
            // ISBN not validated
            // TODO : bot ISBN invalide (queue, message PD...)
            $this->setParam(
                'isbn invalide',
                sprintf('%s %s', $isbn, $e->getMessage() ?? '')
            );
            $this->addSummaryLog(sprintf('ISBN invalide: %s', $e->getMessage()));
            $this->optiStatus->setNotCosmetic(true);

            // TODO log file ISBNinvalide
            return;
        }

        // Si $isbn13 et 'isbn2' correspond à ISBN-13 => suppression
        if ($this->hasParamValue('isbn2')
            && $this->stripIsbn($this->getParam('isbn2')) === $this->stripIsbn($isbn13)
        ) {
            $this->unsetParam('isbn2');
        }

        // ISBN-10 ?
        $stripIsbn = $this->stripIsbn($isbn);
        if (10 === mb_strlen($stripIsbn)) {
            // ajout des tirets
            $isbn10pretty = $isbn;

            try {
                $isbn10pretty = $isbnMachine->format('ISBN-10');
            } catch (Throwable $e) {
                unset($e);
            }

            // archivage ISBN-10 dans 'isbn2'
            if (!$this->getParam('isbn2')) {
                $this->setParam('isbn2', $isbn10pretty);
            }
            // sinon dans 'isbn3'
            if ($this->hasParamValue('isbn2')
                && $this->stripIsbn($this->getParam('isbn2')) !== $stripIsbn
                && empty($this->getParam('isbn3'))
            ) {
                $this->setParam('isbn3', $isbn10pretty);
            }
            // delete 'isbn10' (en attendant modification modèle)
            if ($this->hasParamValue('isbn10') && $this->stripIsbn($this->getParam('isbn10')) === $stripIsbn) {
                $this->unsetParam('isbn10');
            }
        }

        // ISBN correction
        if ($isbn13 !== $isbn) {
            $this->setParam('isbn', $isbn13);
            $this->addSummaryLog('ISBN');
            //            $this->notCosmetic = true;
        }
    }

    /**
     * Find year of book publication.
     */
    protected function findBookYear(): ?int
    {
        $annee = $this->getParam('année');
        if (!empty($annee) && is_numeric($annee)) {
            return (int)$annee;
        }
        $date = $this->getParam('date');
        if ($date && preg_match('#[^0-9]?([12]\d\d\d)[^0-9]?#', $date, $matches) > 0) {
            return (int)$matches[1];
        }

        return null;
    }

    protected function stripIsbn(string $isbn): string
    {
        return trim(preg_replace('#[^0-9Xx]#', '', $isbn));
    }
}