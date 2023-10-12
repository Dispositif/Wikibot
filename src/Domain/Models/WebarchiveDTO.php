<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Models;

use DateTimeInterface;

final class WebarchiveDTO
{
    public function __construct(
        protected string             $archiver,
        protected string             $originalUrl,
        protected string             $archiveUrl,
        protected ?DateTimeInterface $archiveDate
    )
    {
    }

    public function getArchiver(): string
    {
        return $this->archiver;
    }

    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    public function getArchiveUrl(): string
    {
        return $this->archiveUrl;
    }

    public function getArchiveDate(): ?DateTimeInterface
    {
        return $this->archiveDate;
    }
}