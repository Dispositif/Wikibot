<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Transformers\Handlers;


use LogicException;

class OuvrageMixComposite extends AbstractMixHandler
{
    public function handle()
    {
        $components = [
            new MixAutoParams($this->origin, $this->book, $this->log, $this->optiStatus),
            new MixLienAuteur($this->origin, $this->book, $this->log, $this->optiStatus),
            new MixLireEnLigne($this->origin, $this->book, $this->log, $this->optiStatus),
            new MixTitle($this->origin, $this->book, $this->log, $this->optiStatus),
            new MixBnfCopyright($this->origin, $this->book, $this->log, $this->optiStatus),
        ];

        foreach ($components as $component) {
            if (!$component instanceof TransformHandlerInterface) {
                throw new LogicException('TransformHandlerInterface expected');
            }
            $component->handle();
        }
    }
}