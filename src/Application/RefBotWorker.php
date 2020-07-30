<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Application;


use App\Domain\Utils\WikiTextUtil;
use Codedungeon\PHPCliColors\Color;
use Exception;

abstract class RefBotWorker extends AbstractBotTaskWorker
{
    const TASK_BOT_FLAG = false;

    protected $warning = false;
    protected $botFlagOnPage;

    public function hasWarning(): bool
    {
        return (bool)$this->warning;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function processDomain(string $title, string $text): ?string
    {
        $this->botFlagOnPage = static::TASK_BOT_FLAG;

        return $this->processText($text);
    }

    /**
     * @param $text
     *
     * @return string|string[]
     * @throws Exception
     */
    public function processText($text)
    {
        $refs = WikiTextUtil::extractRefsAndListOfLinks($text);
        if (empty($refs)) {
            $this->log->debug('empty extractRefsAndListOfLinks');
            return $text;
        }

        foreach ($refs as $ref) {
            $refContent = WikiTextUtil::stripFinalPoint(trim($ref[1]));

            $newRefContent = $this->processRefContent($refContent);

            $text = $this->replaceRefInText($ref, $newRefContent, $text);
        }

        return $text;
    }

    public abstract function processRefContent($refContent): string;

    protected function replaceRefInText(array $ref, string $replace, string $text)
    {
        // Pas de changement
        if (WikiTextUtil::stripFinalPoint(trim($replace)) === WikiTextUtil::stripFinalPoint(trim($ref[1]))) {
            return $text;
        }
        // Ajout point final si </ref> détecté
        if (preg_match('#</ref>#', $ref[0])) {
            $replace .= '.'; // ending point
        }
        $result = str_replace($ref[1], $replace, $ref[0]);
        $this->log->debug(Color::BG_LIGHT_RED."--".Color::NORMAL." ".$ref[0]."\n");
        $this->log->debug(Color::BG_LIGHT_GREEN."++".Color::NORMAL." $result \n\n");

        return str_replace($ref[0], $result, $text);
    }
}
