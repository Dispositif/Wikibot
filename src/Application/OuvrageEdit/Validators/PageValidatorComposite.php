<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\OuvrageEdit\Validators;


use App\Application\InfrastructurePorts\DbAdapterInterface;
use App\Application\WikiBotConfig;
use App\Application\WikiPageAction;
use RuntimeException;

/**
 * needs WikiPageAction injection.
 */
class PageValidatorComposite implements ValidatorInterface
{
    /** @var ValidatorInterface[] */
    protected $validators;

    public function __construct(
        WikiBotConfig $botConfig,
        array $pageCitationCollection,
        DbAdapterInterface $db,
        WikiPageAction $wikiPageAction
    )
    {
        $title = $pageCitationCollection[0]['page'];
        $this->validators = [
            new CitationsAllCompletedValidator($pageCitationCollection, $botConfig->getLogger(), $db),
            new ArticleValidForEditionValidator($title, $botConfig->getLogger(), $db, $wikiPageAction),
            new ArticleRestrictedValidator($title, $botConfig->getLogger(), $db, $wikiPageAction),
            new HumanDelayValidator($title, $botConfig),
        ];
    }

    public function validate(): bool
    {
        foreach ($this->validators as $validator) {
            if (!$validator instanceof ValidatorInterface) {
                throw new RuntimeException($validator::class . ' must implement ValidatorInterface.');
            }
            if (!$validator->validate()) {
                return false;
            }
        }

        return true;
    }
}