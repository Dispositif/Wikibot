<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Domain\Models;

use DateTimeInterface;
use Exception;
use Simplon\Mysql\Crud\CrudModel;

/**
 * Currently data from page_ouvrages sql table.
 */
class PageOuvrageDTO extends CrudModel
{
    use DtoConvertDateTrait;

    final public const COLUMN_ID = 'id';
    final public const COLUMN_PAGE = 'page';

    /** @var int */
    protected $id;
    /** @var string */
    protected $page;
    /** @var string|null */
    protected $raw;
    /** @var string|null */
    protected $opti;
    /** @var string|null */
    protected $opticorrected;
    /** @var string|null */
    protected $optidate;
    /** @var @var bool|null */
    protected $skip;
    /** @var string|null */
    protected $modifs;
    /** @var string|null */
    protected $version;
    /** @var @var int|null */
    protected $notcosmetic;
    /** @var @var int|null */
    protected $major;
    /** @var string|null */
    protected $isbn;
    /** @var string|null */
    protected $edited;
    /** @var @var int|null */
    protected $priority;
    /** @var string|null */
    protected $corrected;
    /** @var @var int|null */
    protected $torevert;
    /** @var string|null */
    protected $reverted;
    /** @var string|null */
    protected $row;
    /** @var string|null */
    protected $verify;
    /** @var @var int|null */
    protected $altered;
    /** @var @var int|null */
    protected $label;

    public function getId(): ?int
    {
        return $this->id ? (int) $this->id : null;
    }

    public function getPage(): string
    {
        return $this->page;
    }

    public function setPage(string $page): PageOuvrageDTO
    {
        $this->page = $page;
        return $this;
    }

    public function getRaw(): ?string
    {
        return $this->raw;
    }

    public function setRaw(?string $raw): PageOuvrageDTO
    {
        $this->raw = $raw;
        return $this;
    }

    public function getOpti(): ?string
    {
        return $this->opti;
    }

    public function setOpti(?string $opti): PageOuvrageDTO
    {
        $this->opti = $opti;
        return $this;
    }

    public function getOpticorrected(): ?string
    {
        return $this->opticorrected;
    }

    public function setOpticorrected(?string $opticorrected): PageOuvrageDTO
    {
        $this->opticorrected = $opticorrected;
        return $this;
    }

    public function getOptidate(): ?string
    {
        return $this->optidate;
    }

    public function setOptidate($optidate): PageOuvrageDTO
    {
        if ($optidate instanceof DateTimeInterface) {
            $this->optidate = self::dateTimeToSql($optidate);

            return $this;
        }
        if (null === $optidate || is_string($optidate)) {
            $this->optidate = $optidate;

            return $this;
        }

        throw new Exception('optidate bad format');
    }

    public function getSkip()
    {
        return $this->skip;
    }

    public function setSkip($skip): PageOuvrageDTO
    {
        $this->skip = $skip;
        return $this;
    }

    public function getModifs(): ?string
    {
        return $this->modifs;
    }

    public function setModifs(?string $modifs): PageOuvrageDTO
    {
        $this->modifs = $modifs;
        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): PageOuvrageDTO
    {
        $this->version = $version;
        return $this;
    }

    public function getNotcosmetic()
    {
        return $this->notcosmetic;
    }

    public function setNotcosmetic($notcosmetic): PageOuvrageDTO
    {
        $this->notcosmetic = $notcosmetic;
        return $this;
    }

    public function getMajor()
    {
        return $this->major;
    }

    public function setMajor($major): PageOuvrageDTO
    {
        $this->major = $major;
        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): PageOuvrageDTO
    {
        $this->isbn = $isbn;
        return $this;
    }

    public function getEdited(): ?string
    {
        return $this->edited;
    }

    public function setEdited(?string $edited): PageOuvrageDTO
    {
        $this->edited = $edited;
        return $this;
    }

    public function getPriority()
    {
        return $this->priority;
    }

    public function setPriority($priority): PageOuvrageDTO
    {
        $this->priority = $priority;
        return $this;
    }

    public function getCorrected(): ?string
    {
        return $this->corrected;
    }

    public function setCorrected(?string $corrected): PageOuvrageDTO
    {
        $this->corrected = $corrected;
        return $this;
    }

    public function getTorevert()
    {
        return $this->torevert;
    }

    public function setTorevert($torevert): PageOuvrageDTO
    {
        $this->torevert = $torevert;
        return $this;
    }

    public function getReverted(): ?string
    {
        return $this->reverted;
    }

    public function setReverted(?string $reverted): PageOuvrageDTO
    {
        $this->reverted = $reverted;
        return $this;
    }

    public function getRow(): ?string
    {
        return $this->row;
    }

    public function setRow(?string $row): PageOuvrageDTO
    {
        $this->row = $row;
        return $this;
    }

    public function getVerify(): ?string
    {
        return $this->verify;
    }

    public function setVerify(?string $verify): PageOuvrageDTO
    {
        $this->verify = $verify;
        return $this;
    }

    public function getAltered()
    {
        return $this->altered;
    }

    public function setAltered($altered): PageOuvrageDTO
    {
        $this->altered = $altered;
        return $this;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function setLabel($label): PageOuvrageDTO
    {
        $this->label = $label;
        return $this;
    }
}