<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\OuvrageEdit\Validators;


use App\Application\OuvrageEdit\OuvrageEditWorker;
use App\Application\WikiBotConfig;
use Psr\Log\LoggerInterface;

class HumanDelayValidator implements ValidatorInterface
{
    /**
     * @var string
     */
    protected $title;
    /**
     * @var WikiBotConfig
     */
    protected $bot;
    /**
     * @var LoggerInterface
     */
    protected $log;

    public function __construct(string $title, WikiBotConfig $bot)
    {
        $this->title = $title;
        $this->bot = $bot;
        $this->log = $bot->getLogger();
    }

    public function validate(): bool
    {
        if ($this->bot->minutesSinceLastEdit($this->title) < 10) {
            // to improve : Gestion d'une repasse dans X jours
            $this->log->notice(
                sprintf(
                    "SKIP : édition humaine dans les dernières %s minutes.\n",
                    OuvrageEditWorker::DELAY_MINUTES_AFTER_HUMAN_EDIT
                )
            );
            sleep(60 * OuvrageEditWorker::DELAY_MINUTES_AFTER_HUMAN_EDIT); // hack: waiting cycles

            return false;
        }

        return true;
    }
}