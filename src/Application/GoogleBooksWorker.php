<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application;

use App\Domain\Transformers\GoogleTransformer;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\GoogleBooksAdapter;
use Throwable;

/**
 * externe goo
 * Class GoogleBooksWorker
 *
 * @package App\Application\Examples
 */
class GoogleBooksWorker extends AbstractBotTaskWorker
{
    public const SLEEP_AFTER_EDITION        = 60;
    public const TASK_BOT_FLAG              = true;
    public const ARTICLE_ANALYZED_FILENAME  = __DIR__.'/resources/gooBot_edited.txt';
    public const SKIP_LASTEDIT_BY_BOT       = false;
    public const SKIP_NOT_IN_MAIN_WIKISPACE = true;
    public const SKIP_ADQ                   = false;

    protected $modeAuto = true;

    /**
     * @param string      $title
     * @param string|null $text
     *
     * @return string|null
     * @throws Throwable
     */
    protected function processWithDomainWorker(string $title, string $text): ?string
    {
        $ref = new GoogleTransformer(new GoogleApiQuota(), new GoogleBooksAdapter());

        return $ref->process($text);
    }
}








