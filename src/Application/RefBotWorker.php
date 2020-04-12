<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Application;


use App\Domain\Utils\WikiTextUtil;
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
    protected function processDomain(string $title, ?string $text): ?string
    {
        $this->taskName = static::TASK_NAME;
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
        $refs = WikiTextUtil::extractAllRefs($text);
        if (empty($refs)) {
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
        if (WikiTextUtil::stripFinalPoint(trim($replace)) === WikiTextUtil::stripFinalPoint(trim($ref[1]))) {
            //            echo Color::BG_LIGHT_GRAY."xx".Color::NORMAL." ".$ref[1]."\n";
            return $text;
        }

        $replace .= '.'; // ending point
        $result = str_replace($ref[1], $replace, $ref[0]);
        $this->log->debug(Color::BG_LIGHT_RED."--".Color::NORMAL." ".$ref[0]."\n");
        $this->log->debug(Color::BG_LIGHT_GREEN."++".Color::NORMAL." $result \n\n");

        return str_replace($ref[0], $result, $text);
    }
}
