<?php

/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */
declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\AbstractBotTaskWorker;
use App\Domain\RefGoogleBook;

/**
 * Class RefGooWorker
 *
 * @package App\Application\Examples
 */
class RefGooWorker extends AbstractBotTaskWorker
{
    const TASK_NAME           = "bot : Amélioration bibliographique : lien Google Books ⇒ {ouvrage}"; // 😎
    const SLEEP_AFTER_EDITION = 20;
    protected $botFlag = true;

    protected $modeAuto = true;

    protected function getTitles(): array
    {
        $cirrusURL
            = 'https://fr.wikipedia.org/w/api.php?action=query&list=search'
            .'&srsearch=%22https://books.google%22%20insource:/\%3Cref[^\%3E]*\%3Ehttps\:\/\/books\.google/&formatversion=2&format=json&srnamespace=0'
            .'&srlimit=100&srqiprofile=popular_inclinks_pv&srsort=last_edit_desc';

        $search = $this->cirrusSearch;

        return $search->search($cirrusURL);
    }

    protected function processDomain(string $title, ?string $text): ?string
    {
        $ref = new RefGoogleBook();

        return $ref->process($text);
    }
}








