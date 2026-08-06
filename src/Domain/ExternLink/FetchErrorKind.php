<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

/**
 * Network-level failures that don't carry an HTTP status code
 * (the transfer itself failed before/without a server response).
 */
enum FetchErrorKind
{
    case DnsResolutionFailed;
    case EmptyReply;
    case ProxyFailure;
    case ConnectionTimeout;
    case TlsError;
    case TooManyRedirects;
    case Unknown;
}
