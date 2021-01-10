<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\Bazar;

use App\Domain\Utils\DateUtil;
use DateTime;

/**
 * Représente un message utilisateur (en PD, bistro, etc).
 * Généré par parsing wikicode dans TalkPage.
 * Class TalkMessage
 */
class TalkMessage
{
    private $order; // order in section
    private $raw;
    private $user;
    private $rawDate;
    private $indentation;
    /**
     * @var DateTime
     */
    private $date;

    public function __construct(int $order, string $raw)
    {
        $this->order = $order;
        $this->raw = $raw;
    }

    /**
     * @return int
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * @return string
     */
    public function getRaw(): string
    {
        return $this->raw;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return mixed
     */
    public function getRawDate()
    {
        return $this->rawDate;
    }

    /**
     * @return DateTime
     */
    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * @return mixed
     */
    public function getIndentation()
    {
        return $this->indentation;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @param mixed $rawDate
     *
     * @throws \Exception
     */
    public function setRawDate($rawDate): void
    {
        $this->rawDate = $rawDate;
        $this->date = DateUtil::fromWikiSignature($rawDate);
    }

    /**
     * @param mixed $indentation
     */
    public function setIndentation($indentation): void
    {
        $this->indentation = $indentation;
    }

}
