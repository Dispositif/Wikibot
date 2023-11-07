<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application;

use App\Application\InfrastructurePorts\PageListForAppInterface as PageListInterface;
use App\Domain\Transformers\GoogleTransformer;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\GoogleBooksAdapter;
use Mediawiki\Api\MediawikiFactory;

/**
 * Parse and transform Google Books URL.
 */
class GoogleBooksWorker extends AbstractBotTaskWorker
{
    final public const SLEEP_AFTER_EDITION        = 60;
    final public const TASK_BOT_FLAG              = true;
    final public const ARTICLE_ANALYZED_FILENAME  = __DIR__.'/resources/gooBot_edited.txt';
    final public const SKIP_LASTEDIT_BY_BOT       = false;
    final public const SKIP_NOT_IN_MAIN_WIKISPACE = true;
    final public const SKIP_ADQ                   = false;

    protected $modeAuto = true;
    protected GoogleTransformer $transformer;

    public function __construct(
        WikiBotConfig      $bot,
        MediawikiFactory   $wiki,
        ?PageListInterface $pagesGen = null
    )
    {
        $this->transformer = new GoogleTransformer(new GoogleApiQuota(), new GoogleBooksAdapter(), $bot->getLogger());
        parent::__construct($bot, $wiki, $pagesGen);
    }

    protected function processWithDomainWorker(string $title, string $text): ?string
    {
        return $this->transformer->process($text);
    }
}








