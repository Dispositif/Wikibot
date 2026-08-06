<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

/**
 * Explicit outcome of a link-processing gate (RobotNoIndexValidator, InterstitialPageValidator,
 * SoftFailureDetector...), replacing the previous plain bool which made every "don't touch
 * anything" gate indistinguishable from every other one downstream.
 */
enum LinkVerdict
{
    case Accept;       // proceed with template generation
    case KeepUrlAsIs;  // deliberate no-op (noindex, blacklist, bot-challenge page...)
    case TreatAsDead;  // page is unusable content-wise : route to DeadLinkTransformer
}
