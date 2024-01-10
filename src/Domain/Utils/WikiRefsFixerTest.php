<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

use PHPUnit\Framework\TestCase;

class WikiRefsFixerTest extends TestCase
{
    public static function provideConcatenatedRefFixture(): array
    {
        return [
            [
                // skip
                '<references>
                <ref name="A">fu</ref>
                <ref name="B">fu</ref>',
                '<references>
                <ref name="A">fu</ref>
                <ref name="B">fu</ref>',
            ],
            [
                // skip
                '{{{Références | références=
                <ref name="A">fu</ref>
                <ref name="B">fu</ref>}}',
                '{{{Références | références=
                <ref name="A">fu</ref>
                <ref name="B">fu</ref>}}',
            ],
            [
                '{{Références nombreuses|taille=30 | références=
                <!-- :0 -->
                <ref name="A">fu</ref>
                <ref name="B">fu</ref>',
                '{{Références nombreuses|taille=30 | références=
                <!-- :0 -->
                <ref name="A">fu</ref>
                <ref name="B">fu</ref>',
            ],
            ['<ref>fu</ref><ref name="1">bar</ref>', '<ref>fu</ref>{{,}}<ref name="1">bar</ref>'],
            ['<ref>fu</ref>  <ref name="1">bar</ref>', '<ref>fu</ref>{{,}}<ref name="1">bar</ref>'],
            [
                // carriage return
                '<ref>fu</ref>
            <ref name="1">bar</ref>', '<ref>fu</ref>{{,}}<ref name="1">bar</ref>'],
            ['<ref>fu</ref>{{,}}<ref name="1">bar</ref>', '<ref>fu</ref>{{,}}<ref name="1">bar</ref>'],
            ['<ref name="A" /> <ref name="B">', '<ref name="A" />{{,}}<ref name="B">'],
            ['<ref name=A /><ref name="B">', '<ref name=A />{{,}}<ref name="B">'],
            ['<ref name=A/><ref name="B">', '<ref name=A/>{{,}}<ref name="B">'],
            // sfn
            [
                '<ref name=A/>{{Sfn|O. Teissier|1860|p=42-43}}<ref name=B/>',
                '<ref name=A/>{{,}}{{Sfn|O. Teissier|1860|p=42-43}}{{,}}<ref name=B/>',
            ],
            [
                '</ref>{{Sfn|O. Teissier|1860|p=42-43}}<ref name=B>',
                '</ref>{{,}}{{Sfn|O. Teissier|1860|p=42-43}}{{,}}<ref name=B>',
            ],
            [
                '{{Sfn|O. Teissier}}{{Sfn|O. Teissier}}',
                '{{Sfn|O. Teissier}}{{,}}{{Sfn|O. Teissier}}',
            ],
            ['</ref><ref group=n>fu</ref>', '</ref>{{,}}<ref group=n>fu</ref>'],
            [
                // inchanged with {{Références|références=
                '<ref name="A">fu</ref><ref name="B">bar</ref><ref name="C"/> bla {{Références|références=  <ref name="C">fu</ref><ref name="D">bar</ref> bla',
                '<ref name="A">fu</ref><ref name="B">bar</ref><ref name="C"/> bla {{Références|références=  <ref name="C">fu</ref><ref name="D">bar</ref> bla',
            ],
            [
                // inchanged with multilines {{Références|références=
                '<ref name="A">fu</ref><ref name="B">bar</ref><ref name="C"/> bla {{Références
                |références=<ref name="C">fu</ref><ref name="D">bar</ref> bla',
                '<ref name="A">fu</ref><ref name="B">bar</ref><ref name="C"/> bla {{Références
                |références=<ref name="C">fu</ref><ref name="D">bar</ref> bla',
            ],
        ];
    }

    public static function provideFixRefSpacingSyntax(): array
    {
        return [
            ['bla <ref>A</ref>.', 'bla<ref>A</ref>.'],
            ['bla <ref name="C"/>', 'bla<ref name="C"/>'],
            ['| <ref>', '| <ref>'], // unchanged
            ['| <ref name="C"/>', '| <ref name="C"/>'], // unchanged
            ['= <ref>', '= <ref>'], // unchanged
            ['= <ref name="C"/>', '= <ref name="C"/>'], // unchanged
            ['5 <ref>', '5 <ref>'], // unchanged
            ['9 <ref name="C"/>', '9 <ref name="C"/>'], // unchanged
            ['<ref> A </ref>', '<ref> A </ref>'],
            ['bla <ref name="B">A</ref>', 'bla<ref name="B">A</ref>'],
            ['bla <ref name="C"/> bla', 'bla<ref name="C"/> bla'],
        ];
    }

    public static function provideFixGenericWikiSyntax(): array
    {
        return [
            [
                <<<EOF
                {{Infobox}}
                Blab <ref name="A">fu</ref>
                <ref name="B">fu</ref>.
                bzear
                {{Références |taille=3 |références=
                
                <ref name="C">fu</ref>
                <ref name="D">fu</ref> <ref name="E">fu</ref>
                }}
                EOF,
                <<<EOF
                {{Infobox}}
                Blab<ref name="A">fu</ref>{{,}}<ref name="B">fu</ref>.
                bzear
                {{Références |taille=3 |références=
                
                <ref name="C">fu</ref>
                <ref name="D">fu</ref> <ref name="E">fu</ref>
                }}
                EOF
    ,
            ],
            [
                <<<EOF
                {{Infobox}}
                Blab <ref name="A">fu</ref>
                <ref name="B">fu</ref>.
                bzear
                {{Références nombreuses |taille=3 |références=
                <ref name="C">fu</ref>
                <ref name="D">fu</ref>
                }}
                EOF,
                <<<EOF
                {{Infobox}}
                Blab<ref name="A">fu</ref>{{,}}<ref name="B">fu</ref>.
                bzear
                {{Références nombreuses |taille=3 |références=
                <ref name="C">fu</ref>
                <ref name="D">fu</ref>
                }}
                EOF,
            ],
            [
                <<<EOF
                {{Infobox}}
                Blab <ref name="A">fu</ref>
                <ref name="B">fu</ref>.
                bzear
                {{Références nombreuses |taille=3 |références=
                <ref name="C">fu</ref>
                <ref name="D">fu</ref>
                }}
                EOF,
                <<<EOF
                {{Infobox}}
                Blab<ref name="A">fu</ref>{{,}}<ref name="B">fu</ref>.
                bzear
                {{Références nombreuses |taille=3 |références=
                <ref name="C">fu</ref>
                <ref name="D">fu</ref>
                }}
                EOF,
            ],
            [
                "le ''Paso de l'[[abrivado]]'', spécialement composé par [[Eddie Vartan]]

== Notes et références ==
{{Références|groupe=Note|colonnes=2}}
{{Références|références=
<ref name=ATP>
{{Pdf}}{{Lien web|url=http://www.sports.gouv.fr/IMG/pdf/atlas.pdf|titre=Atlas national des fédérations sportives 2012.|site=www.sports.gouv.fr|consulté le=05 mars 2015}}
.</ref>
|colonnes=2}}",
                "le ''Paso de l'[[abrivado]]'', spécialement composé par [[Eddie Vartan]]

== Notes et références ==
{{Références|groupe=Note|colonnes=2}}
{{Références|références=
<ref name=ATP>
{{Pdf}}{{Lien web|url=http://www.sports.gouv.fr/IMG/pdf/atlas.pdf|titre=Atlas national des fédérations sportives 2012.|site=www.sports.gouv.fr|consulté le=05 mars 2015}}
.</ref>
|colonnes=2}}",
            ],
        ];
    }

    /**
     * @dataProvider provideConcatenatedRefFixture
     */
    public function testFixConcatenatedRefs($text, $expected)
    {
        $this::assertSame($expected, WikiRefsFixer::fixConcatenatedRefsSyntax($text));
    }

    /**
     * @dataProvider provideFixRefSpacingSyntax
     */
    public function testFixRefSpacingSyntax($text, $expected)
    {
        $this::assertSame($expected, WikiRefsFixer::fixRefSpacingSyntax($text));
    }

    /**
     * @dataProvider provideFixGenericWikiSyntax
     */
    public function testFixRefWikiSyntax($text, $expected)
    {
        $this::assertSame($expected, WikiRefsFixer::fixRefWikiSyntax($text));
    }
}
