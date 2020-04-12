<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Application;

/**
 * Juste pour faire des analyses de texte
 * Class DataAnalysis
 *
 * @package App\Application
 */
class DataAnalysis
{
    /**
     * @var string|null
     */
    private $title;
    /**
     * @var string|null
     */
    private $text;

    /**
     * @param string|null $text
     * @param string|null $title
     */
    public function process(?string $text = null, ?string $title = null): void
    {
//        $this->text = $text;
//        $this->title = $title;

    }

}
