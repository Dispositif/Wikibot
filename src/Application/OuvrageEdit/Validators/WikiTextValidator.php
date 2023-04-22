<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\OuvrageEdit\Validators;


use App\Application\InfrastructurePorts\DbAdapterInterface;
use Psr\Log\LoggerInterface;

class WikiTextValidator implements ValidatorInterface
{
    /** @var string|null */
    protected $wikiText;
    /**
     * @var string
     */
    protected $oldWikiText;
    /**
     * @var LoggerInterface
     */
    protected $log;
    /**
     * @var string
     */
    protected $title;
    /**
     * @var DbAdapterInterface
     */
    protected $db;

    public function __construct(?string $wikiText, string $oldWikiText, LoggerInterface $logger, string $title, DbAdapterInterface $db)
    {
        $this->wikiText = $wikiText;
        $this->oldWikiText = $oldWikiText;
        $this->log = $logger;
        $this->title = $title;
        $this->db = $db;
    }

    public function validate(): bool
    {
        if ($this->wikiText === '' || $this->wikiText === '0') {
            $this->log->warning("Empty wikitext...");

            return false;
        }
        if ($this->wikiText === $this->oldWikiText) {
            $this->log->debug("Rien à changer...");
            $this->db->skipArticle($this->title);

            return false;
        }

        return true;
    }
}