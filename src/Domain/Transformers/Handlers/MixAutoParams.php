<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Transformers\Handlers;

// completion automatique des paramètres manquants
class MixAutoParams extends AbstractMixHandler
{
    final public const WIKI_LANGUAGE = 'fr';

    final public const SKIP_PARAMS = [
            'isbn invalide',
            'auteurs',
            'auteur1',
            'prénom1',
            'nom1',
            'auteur2',
            'prénom2',
            'nom2',
            'auteur3',
            'prénom3',
            'nom3',
            'auteur4',
            'prénom4',
            'nom4',
            'lire en ligne',
            'présentation en ligne',
            'date',
            'sous-titre',
            'lien auteur1',
            'lien auteur2',
        ];

    public function handle()
    {
        foreach ($this->book->toArray() as $param => $value) {
            if (!$this->origin->hasParamValue($param)) {
                if (in_array($param, self::SKIP_PARAMS)) {
                    continue;
                }
                // skip 'année' if 'date' not empty
                if ('année' === $param && $this->origin->hasParamValue('date')) {
                    continue;
                }

                $this->origin->setParam($param, $value);

                if ('langue' === $param && self::WIKI_LANGUAGE === $value) {
                    //$this->log('fr'.$param);
                    continue;
                }

                $this->optiStatus->addSummaryLog('++' . $param);
                $this->optiStatus->setMajor(true);
                $this->optiStatus->setNotCosmetic(true);
            }
        }
    }
}