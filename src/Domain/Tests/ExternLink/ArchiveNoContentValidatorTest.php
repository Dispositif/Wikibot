<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Domain\ExternLink\LinkVerdict;
use App\Domain\ExternLink\Validators\ArchiveNoContentValidator;
use PHPUnit\Framework\TestCase;

class ArchiveNoContentValidatorTest extends TestCase
{
    private const SHELL_BODY = '<!doctype html><html lang="en"><head><meta charset="utf-8"/>'
    . '<meta name="description" content="Wikiwix Archives"/><title>Wikiwix Archives</title>'
    . '</head><body><noscript>You need to enable JavaScript to run this app.</noscript>'
    . '<div id="root"></div></body></html>';

    private const UNKNOWN_PAGE_BODY = '<html><head><title>Wikiwix Archive - Unknown page</title></head>'
    . '<body><h1>404</h1><p>We are really sorry.</p></body></html>';

    private const WIKIWIX_URL = 'https://archive.wikiwix.com/cache/index2.php?url=http://www.ign.fr/x';

    public function testViewerShellDetectedByTitle(): void
    {
        $validator = new ArchiveNoContentValidator(
            ['meta' => ['html-title' => 'Wikiwix Archives']],
            self::WIKIWIX_URL
        );

        self::assertSame(LinkVerdict::KeepUrlAsIs, $validator->check());
    }

    /** the SPA may end up serving a differently-titled shell : body markers stay */
    public function testViewerShellDetectedByBodyMarkers(): void
    {
        $validator = new ArchiveNoContentValidator(['meta' => []], self::WIKIWIX_URL, self::SHELL_BODY);

        self::assertSame(LinkVerdict::KeepUrlAsIs, $validator->check());
    }

    /** URL never archived : HTTP 200 over an <h1>404</h1>, would have given "|titre=Unknown page" */
    public function testUnknownPageSoft404DetectedByTitle(): void
    {
        $validator = new ArchiveNoContentValidator(
        // ExternPage strips the "Wikiwix Archive - " prefix off the html title
            ['meta' => ['html-title' => 'Unknown page']],
            self::WIKIWIX_URL,
            self::UNKNOWN_PAGE_BODY
        );

        self::assertSame(LinkVerdict::KeepUrlAsIs, $validator->check());
    }

    public function testUnknownPageDetectedByBodyMarker(): void
    {
        $validator = new ArchiveNoContentValidator(['meta' => []], self::WIKIWIX_URL, self::UNKNOWN_PAGE_BODY);

        self::assertSame(LinkVerdict::KeepUrlAsIs, $validator->check());
    }

    public function testArchivedContentAccepted(): void
    {
        $validator = new ArchiveNoContentValidator(
            ['meta' => ['html-title' => 'IGN - Données géographiques sur ma commune']],
            self::WIKIWIX_URL,
            '<html lang="FR"><head><title>IGN - Données géographiques sur ma commune</title></head><body>...</body></html>'
        );

        self::assertSame(LinkVerdict::Accept, $validator->check());
    }

    /** host-scoped : a legitimate page elsewhere must not be mistaken for an archive answer */
    public function testNonArchiveHostAlwaysAccepted(): void
    {
        $validator = new ArchiveNoContentValidator(
            ['meta' => ['html-title' => 'Wikiwix Archives']],
            'https://www.lemonde.fr/article',
            self::SHELL_BODY
        );

        self::assertSame(LinkVerdict::Accept, $validator->check());
    }
}
