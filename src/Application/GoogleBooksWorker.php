<?php

/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */
declare(strict_types=1);

namespace App\Application;

use App\Domain\GoogleTransformer;
use Throwable;

/**
 * todo Refac duplication
 * externe goo
 * Class GoogleBooksWorker
 *
 * @package App\Application\Examples
 */
class GoogleBooksWorker extends AbstractBotTaskWorker
{
    const TASK_NAME           = "Amélioration bibliographique : lien Google Books ⇒ {ouvrage}"; // 😎
    const SLEEP_AFTER_EDITION = 300;
    protected $botFlag = false;

    protected $modeAuto = true;

    /**
     * @param string      $title
     * @param string|null $text
     *
     * @return string|null
     * @throws Throwable
     */
    protected function processDomain(string $title, string $text): ?string
    {
        $ref = new GoogleTransformer();

        return $ref->process($text);
    }
}








