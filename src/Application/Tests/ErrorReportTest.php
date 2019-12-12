<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Tests;

use App\Application\ErrorReport;
use PHPUnit\Framework\TestCase;

class ErrorReportTest extends TestCase
{
    public function testGetReport()
    {
        $text = file_get_contents(__DIR__.'/../resources/fixture_error_report.wiki');
        $report = new ErrorReport();
        $errors = $report->getReport($text);
        $this::assertSame(
            [
                "|editor=JT Staley, MP Bryant, N Pfennig, and JG Holt, eds. <!--PARAMETRE 'editor' N'EXISTE PAS -->",
                "|editor=DR Boone and RW Castenholz, eds. <!--PARAMETRE 'editor' N'EXISTE PAS -->",
            ],
            $errors
        );

        return $errors;
    }

    /**
     * @depends testGetReport
     */
    public function testCountErrorInText($errors)
    {
        $article = <<<EOF
sadfzd |editor=JT Staley, MP Bryant, N Pfennig, and JG Holt, eds. <!--PARAMETRE 'editor' N'EXISTE PAS -->
qsfqsf |editor=DR Boone and RW Castenholz, eds. <!--PARAMETRE 'editor' N'EXISTE PAS --> sqdf bla
EOF;
        $report = new ErrorReport();
        $this::assertSame(
            2,
            $report->countErrorInText($errors, $article)
        );
    }

    public function testDeleteAllReports()
    {
        $text = file_get_contents(__DIR__.'/../resources/fixture_error_report.wiki');
        $report = new ErrorReport();
        $this::assertSame(
            '{{À faire}}

== Bla ==
Erat autem diritatis eius hoc quoque indicium nec obscurum nec latens, quod ludicris cruentis delectabatur et in circo sex vel septem aliquotiens vetitis certaminibus pugilum vicissim se concidentium perfusorumque sanguine specie ut lucratus ingentia laetabatur.

[[Utilisateur:ZiziBot|ZiziBot]]

== Blabla ==

Erat autem diritatis eius hoc quoque indicium nec obscurum nec latens, quod ludicris
cruentis delectabatur et in circo sex vel septem aliquotiens vetitis certaminibus pugilum vicissim se concidentium perfusorumque sanguine specie ut lucratus ingentia laetabatur.
',
            $report->deleteAllReports($text)
        );
    }

}
