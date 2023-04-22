<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\OuvrageEdit\Validators;


use App\Application\InfrastructurePorts\DbAdapterInterface;
use App\Domain\Utils\WikiTextUtil;
use Psr\Log\LoggerInterface;

class CitationValidator implements ValidatorInterface
{
    /**
     * @var array
     */
    protected $ouvrageData;
    /**
     * @var LoggerInterface
     */
    protected $log;
    /**
     * @var DbAdapterInterface
     */
    protected $db;
    /**
     * @var string
     */
    protected $wikiText;

    public function __construct(array $ouvrageData, string $wikiText, LoggerInterface $logger, DbAdapterInterface $db)
    {
        $this->ouvrageData = $ouvrageData;
        $this->wikiText = $wikiText;
        $this->log = $logger;
        $this->db = $db;
    }

    public function validate(): bool
    {
        if (empty($this->ouvrageData['opti'])) {
            $this->log->notice("SKIP: pas d'optimisation proposée.");

            return false;
        }
        if (
            WikiTextUtil::isCommented($this->ouvrageData['raw'])
            || $this->isTextCreatingError($this->ouvrageData['raw'])
        ) {
            $this->log->notice("SKIP: template avec commentaire HTML ou modèle problématique.");
            $this->db->skipRow((int)$this->ouvrageData['id']);

            return false;
        }
        if (!$this->stringFound()) {
            return false;
        }

        return true;
    }

    protected function isTextCreatingError(string $string): bool
    {
        // mauvaise Modèle:Sp
        return (preg_match('#\{\{-?(sp|s|sap)-?\|#', $string) === 1);
    }

    protected function stringFound(): bool
    {
        $find = mb_strpos($this->wikiText, $this->ouvrageData['raw']);
        if ($find === false) {
            $this->log->notice("String non trouvée.");
            $this->db->skipRow((int)$this->ouvrageData['id']);

            return false;
        }

        return true;
    }
}