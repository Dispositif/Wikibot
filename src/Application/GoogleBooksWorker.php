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
 * externe goo
 * Class GoogleBooksWorker
 *
 * @package App\Application\Examples
 */
class GoogleBooksWorker extends AbstractBotTaskWorker
{
    const SLEEP_AFTER_EDITION        = 60;
    const TASK_BOT_FLAG              = true;
    const ARTICLE_ANALYZED_FILENAME  = __DIR__.'/resources/gooBot_edited.txt';
    const SKIP_LASTEDIT_BY_BOT       = false;
    const SKIP_NOT_IN_MAIN_WIKISPACE = true;

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








