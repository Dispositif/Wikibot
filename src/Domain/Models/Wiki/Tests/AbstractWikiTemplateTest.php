<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki\Tests;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\WikiTemplateFactory;
use Exception;
use PHPUnit\Framework\TestCase;

class AbstractWikiTemplateTest extends TestCase
{
    public function testIsValidForEdit(){
        $article = WikiTemplateFactory::create('article');
        $article->hydrate(
            [
                'auteur' => 'Michou',
                'titre' => 'Au soleil',
                'périodique' => 'Paris Match',
                'année' => '2010', // équivalence 'date'
            ]
        );
        $this::assertTrue($article->isValidForEdit());


        $articleInc = WikiTemplateFactory::create('article');
        $articleInc->hydrate(
            [
                'auteur' => 'Michou',
                'titre' => 'Au soleil',
                'date' => '2010', // année ???
            ]
        );
        $this::assertFalse($articleInc->isValidForEdit());


        $empty = new OuvrageTemplate();
        $this::assertFalse($empty->isValidForEdit());
    }

    public function testOuvrageSerialize()
    {
        $ouvrage = WikiTemplateFactory::create('ouvrage');
        $ouvrage->hydrate(
            [
                'nom1' => 'Michou',
                'prénom1' => 'Bob',
                'titre' => 'Au soleil',
            ]
        );
        $ouvrage->setParam('auteur2', 'Sophie');
        $this::assertSame(
            '{{Ouvrage|nom1=Michou|auteur2=Sophie|prénom1=Bob|titre=Au soleil|éditeur=|année=|pages totales=|isbn=}}',
            $ouvrage->serialize()
        );
        $this::assertSame(
            '{{Ouvrage|prénom1=Bob|nom1=Michou|auteur2=Sophie|titre=Au soleil|éditeur=|année=|pages totales=|isbn=}}',
            $ouvrage->serialize(true)
        );
    }

    /**
     * @throws Exception
     */
    public function testSerialize()
    {
        $data = [
            //            '1' => 'fr',
            'url' => 'http://google.com',
            'auteur1' => 'Bob',
            'date' => '2010-11-25',
            'titre' => 'foo bar',
        ];

        $lienWeb = WikiTemplateFactory::create('lien web');
        $lienWeb->hydrate($data);

        $this::assertSame(
            '{{lien web|auteur1=Bob|titre=Foo bar|url=http://google.com|date=2010-11-25|consulté le=}}',
            $lienWeb->serialize(true)
        );

        $this::assertSame(
            '{{lien web|url=http://google.com|auteur1=Bob|date=2010-11-25|consulté le=|titre=Foo bar}}',
            $lienWeb->serialize()
        );

        $lienWeb->userSeparator = "\n|";
        $this::assertSame(
            '{{lien web
|url=http://google.com
|auteur1=Bob
|date=2010-11-25
|consulté le=
|titre=Foo bar
}}',
            $lienWeb->serialize()
        );

        $lienWeb->userMultiSpaced = true;
        $lienWeb->userSeparator = "\n|";
        $this::assertSame(
            '{{lien web
|url         = http://google.com
|auteur1     = Bob
|date        = 2010-11-25
|consulté le = 
|titre       = Foo bar
}}',
            $lienWeb->serialize()
        );
    }

    public function testToArrayNoError()
    {
        $data = [
            'url' => 'http://google.com',
            'auteur1' => 'Bob',
            'date' => '2010-11-25',
            'titre' => 'Foo bar',
            'fu' => 'bar',
        ];

        $lienWeb = WikiTemplateFactory::create('lien web');
        $lienWeb->hydrate($data, true);
        $this::assertSame(
            [
                'titre' => 'Foo bar',
                'url' => 'http://google.com',
                'auteur1' => 'Bob',
                'date' => '2010-11-25',
            ],
            $lienWeb->toArray()
        );
    }

    public function testAliasParameter()
    {
        $lienWeb = WikiTemplateFactory::create('lien web');
        $lienWeb->hydrate(
            [
                'lang' => 'fr',
            ]
        );
        $this::assertSame(
            '{{lien web|langue=fr|titre=|url=|consulté le=}}',
            $lienWeb->serialize()
        );
    }


    public function testEmptyValue()
    {
        $data = [
            'url' => 'http://google.com',
        ];

        $lienWeb = WikiTemplateFactory::create('lien web');
        $lienWeb->hydrate($data);

        $lienWeb->setParam('url', '');

        $this::assertSame(
            '{{lien web|titre=|url=|consulté le=}}',
            $lienWeb->serialize(true)
        );
    }

    public function testUserOrder()
    {
        $data = [
            'url' => 'http://google.com',
            'langue' => 'fr',
        ];

        $lienWeb = WikiTemplateFactory::create('lien web');
        $lienWeb->hydrate($data);

        $this::assertSame(
            '{{lien web|langue=fr|titre=|url=http://google.com|consulté le=}}',
            $lienWeb->serialize(true)
        );
    }
}
