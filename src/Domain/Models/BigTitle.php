<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Models;


use App\Domain\Utils\ArrayProcessTrait;

/**
 * @notused
 * Class BigTitle
 *
 * @package App\Domain\Models
 */
class BigTitle
{
    use ArrayProcessTrait;

    private $lang = null;

    /**
     * BigTitle constructor.
     */
    public function __construct(private readonly string $title, private readonly ?string $subTitle = null)
    {
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
