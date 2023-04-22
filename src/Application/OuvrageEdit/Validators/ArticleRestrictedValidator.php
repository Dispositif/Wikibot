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
use Psr\Log\LoggerInterface;

class ArticleRestrictedValidator implements ValidatorInterface
{
    /**
     * @var DbAdapterInterface
     */
    protected $db;
    /**
     * @var LoggerInterface
     */
    protected $log;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var WikiPageAction
     */
    protected $wikiPageAction;

    public function __construct(string $title, LoggerInterface $logger, DbAdapterInterface $db, WikiPageAction $wikiPageAction)
    {
        $this->title = $title;
        $this->log = $logger;
        $this->db = $db;
        $this->wikiPageAction = $wikiPageAction;
    }

    public function validate(): bool
    {
        if (WikiBotConfig::isEditionTemporaryRestrictedOnWiki($this->wikiPageAction->getText())) {
            // new feature ? Gestion d'une repasse dans X jours
            $this->log->info("SKIP : protection/3R/travaux.\n");
            $this->db->skipArticle($this->title);

            return false;
        }

        return true;
    }
}