<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageEdit;

// todo extends WikiPageAction ?
class PageWorkStatus
{
    /**
     * @var string
     */
    public $title;
    public $wikiText = null;
    public $errorWarning = [];
    public $featured_article = false;
    public $citationSummary = [];
    public $importantSummary = [];
    public $nbRows = 0;
    public $notCosmetic = false;

    // Minor flag on edit
    public $minorFlag = true;
    // WikiBotConfig flag on edit
    public $botFlag = true;
    public $citationVersion = '';
    public $luckyState = false;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }
}