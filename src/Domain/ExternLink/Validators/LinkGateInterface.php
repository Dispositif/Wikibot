<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Validators;

use App\Domain\ExternLink\LinkVerdict;

/**
 * A gate in the extern-link processing pipeline (ExternRefTransformer::process()).
 * Distinct from the generic App\Domain\ValidatorInterface (shared with unrelated
 * OuvrageEdit/Transformers validators) so this pipeline can carry a typed verdict
 * instead of a plain bool.
 */
interface LinkGateInterface
{
    public function check(): LinkVerdict;
}
