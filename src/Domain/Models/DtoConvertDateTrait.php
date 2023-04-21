<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Models;

use DateTime;
use DateTimeInterface;

/**
 * Hack because the minimalist ORM does not support DateTime<>string conversion.
 */
trait DtoConvertDateTrait
{
    // 2023-02-12 15:04:53
    public static function sqlToDateTime(?string $string): ?DateTime
    {
        return $string ? DateTime::createFromFormat('Y-m-d H:i:s', $string) : null;
    }

    public static function dateTimeToSql(?DateTimeInterface $date): ?string
    {
        return $date ? $date->format('Y-m-d H:i:s') : null;
    }
}