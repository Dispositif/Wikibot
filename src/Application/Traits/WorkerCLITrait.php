<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Traits;

use Codedungeon\PHPCliColors\Color;

/**
 * CLI : confirmation, mode auto, colors...
 * todo move infra ou lib ?
 */
trait WorkerCLITrait
{
    protected function autoOrYesConfirmation(string $question = 'ÉDITION ?'): bool
    {
        $this->modeAuto = $this->modeAuto ?? false;
        if ($this->modeAuto) {
            return true;
        }
        $ask = readline(Color::LIGHT_MAGENTA . '*** '.$question.' [y/n/auto]' . Color::NORMAL);
        if ('auto' === $ask) {
            $this->modeAuto = true;

            return true;
        }
        if ('y' === $ask) {
            return true;
        }

        return false;
    }
}