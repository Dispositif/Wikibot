<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Models;


use App\Domain\Utils\ArrayProcessTrait;

class BigTitle
{
    use ArrayProcessTrait;

    /**
     * @var string
     */
    private $title;
    /**
     * @var string|null
     */
    private $subTitle;

    private $lang = null;

    /**
     * BigTitle constructor.
     *
     * @param string      $title
     * @param string|null $subTitle
     */
    public function __construct(string $title, ?string $subTitle = null)
    {
        $this->title = $title;
        $this->subTitle = $subTitle;
    }

    public function getData(): array
    {
        return $this->deleteEmptyValueArray(
            [
                'titre' => $this->title,
                'sous-titre' => $this->subTitle,
                'langue' => $this->lang,
            ]
        );
    }
}
