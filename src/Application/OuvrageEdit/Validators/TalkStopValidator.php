<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\OuvrageEdit\Validators;


use App\Application\WikiBotConfig;
use Mediawiki\Api\UsageException;

class TalkStopValidator implements ValidatorInterface
{
    /**
     * @var WikiBotConfig
     */
    protected $botConfig;

    public function __construct(WikiBotConfig $botConfig)
    {
        $this->botConfig = $botConfig;
    }

    /**
     * @throws UsageException
     */
    public function validate(): bool
    {
        $this->botConfig->checkStopOnTalkpage();

        return true;
    }
}