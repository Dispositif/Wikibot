<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

/**
 * The daily Google Books API quota is reached.
 * Distinct type so callers can let it propagate and stop a run instead of
 * catching it alongside unrelated per-item errors (network hiccup, bad ISBN...).
 */
class QuotaExceededException extends DomainException
{
    protected $message = 'Quota Google dépassé';
}
