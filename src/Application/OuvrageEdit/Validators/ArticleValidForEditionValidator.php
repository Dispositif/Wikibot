<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageEdit\Validators;

use App\Application\InfrastructurePorts\DbAdapterInterface;
use App\Application\WikiPageAction;
use Mediawiki\DataModel\Revision;
use Psr\Log\LoggerInterface;

class ArticleValidForEditionValidator implements ValidatorInterface
{
    /**
     * @var string
     */
    protected $title;
    /**
     * @var LoggerInterface
     */
    protected $log;
    /**
     * @var DbAdapterInterface
     */
    protected $db;
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
        if (
            !$this->hasLastRevision()
            || $this->botIsLastEditor()
            || !$this->isInMainNamespace()
            || !$this->hasWikitext()
        ) {
            return false;
        }

        return true;
    }

    protected function hasLastRevision(): bool
    {
        // Page supprimée ?
        if (!$this->wikiPageAction->getLastRevision() instanceof Revision) {
            $this->log->warning("SKIP : page supprimée !\n");
            $this->db->deleteArticle($this->title);

            return false;
        }

        return true;
    }

    protected function botIsLastEditor(): bool
    {
        // HACK
        if ($this->wikiPageAction->getLastEditor() === getenv('BOT_NAME')) {
            $this->log->notice("SKIP : édité recemment par le bot.\n");
            $this->db->skipArticle($this->title);

            return true;
        }

        return false;
    }

    protected function isInMainNamespace(): bool
    {
        // todo include a sandbox page ?
        if ($this->wikiPageAction->getNs() !== 0) {
            $this->log->notice("SKIP : page n'est pas dans Main (ns 0)\n");
            $this->db->skipArticle($this->title);

            return false;
        }
        return true;
    }

    protected function hasWikitext(): bool
    {
        $wikiText = $this->wikiPageAction->getText();

        if (empty($wikiText)) {
            $this->log->warning("SKIP : this->wikitext vide\n");
            $this->db->skipArticle($this->title);

            return false;
        }

        return true;
    }
}