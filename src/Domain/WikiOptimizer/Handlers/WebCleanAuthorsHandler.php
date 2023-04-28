<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Utils\WikiTextUtil;

class WebCleanAuthorsHandler implements OptimizeHandlerInterface
{
    /**
     * @var LienWebTemplate
     */
    protected $template;

    public function __construct(LienWebTemplate $template)
    {
        $this->template = $template;
    }

    public function handle()
    {
        if (in_array($this->template->getParam('auteur1'), ['Rédaction', 'La Rédaction'])) {
            $this->template->unsetParam('auteur1');
        }
        // doublon auteur - site  ou doublon auteur - périodique
        if ((WikiTextUtil::unWikify($this->template->getParam('auteur1') ?? '') === WikiTextUtil::unWikify(
                    $this->template->getParam('site') ?? ''
                ))
            || (WikiTextUtil::unWikify($this->template->getParam('auteur1') ?? '') === WikiTextUtil::unWikify(
                    $this->template->getParam('périodique') ?? ''
                ))
        ) {
            $this->template->unsetParam('auteur1');
        }
    }
}