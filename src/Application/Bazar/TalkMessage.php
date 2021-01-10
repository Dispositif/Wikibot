<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\Bazar;

/**
 * Représente un message utilisateur (en PD, bistro, etc).
 * Généré par parsing wikicode dans TalkPage.
 * Class TalkMessage
 *
 * @package App\Application\Bazar
 */
class TalkMessage
{
    private $talkPage;
    private $section; // todo sections de même nom :(
    private $order; // order in section
    private $raw;
    private $user;
    private $date;
    private $indentation;

    /**
     * TalkMessage constructor.
     *
     * @param $talkPage
     * @param $section
     * @param $order
     * @param $raw
     */
    public function __construct(string $talkPage, string $section, int $order, string $raw)
    {
        $this->talkPage = $talkPage;
        $this->section = $section;
        $this->order = $order;
        $this->raw = $raw;
    }

}
