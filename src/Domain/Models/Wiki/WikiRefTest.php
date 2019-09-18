<?php

namespace App\Domain\Models\Wiki;

use PHPUnit\Framework\TestCase;

class WikiRefTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testGetTemplateParsed()
    {
        $text = '{{lien web|url=blabla}} {{légende plume}}
{{Ouvrage |prénom1=Jacques |nom1=Lacoursière |lien auteur1=Jacques Lacoursière |titre=Histoire populaire du Québec |lieu=Sillery |éditeur=Éditions du Septentrion |année=1995 |pages totales=416 |passage=18}} : {{plume}}
{{citation bloc|À Rome, Bruchési frappe à toutes les portes dans l’espoir d’empêcher l’établissement d’un ministère de l’Éducation. […] Le lendemain, le texte du discours du Trône contenait l’annonce du projet de loi.}}
{{commentaire biblio|Ce livre trace un portrait exhaustif de l\'histoire du Québec jusqu\'au milieu du {{s-|XX}}.}}';

        $ref = new WikiRef($text);
        $ref->parseAllTemplates();
        $templates = $ref->getTemplateParsed();

        $this::assertIsArray($templates);
        $this::assertEquals(
            '{{lien web|url=blabla}}',
            $templates['lien web']['raw']
        );
        $this::assertInstanceOf(
            OuvrageTemplate::class,
            $templates['ouvrage']['model']
        );
    }


    public function testGetTemplateNames()
    {
        $text = '{{lien web|url=blabla}} {{légende plume}}
{{Ouvrage |prénom1=Jacques |nom1=Lacoursière |lien auteur1=Jacques Lacoursière |titre=Histoire populaire du Québec |lieu=Sillery |éditeur=Éditions du Septentrion |année=1995 |pages totales=416 |passage=18}} : {{plume}}
{{citation bloc|À Rome, Bruchési frappe à toutes les portes dans l’espoir d’empêcher l’établissement d’un ministère de l’Éducation. […] Le lendemain, le texte du discours du Trône contenait l’annonce du projet de loi.}}
{{commentaire biblio|Ce livre trace un portrait exhaustif de l\'histoire du Québec jusqu\'au milieu du {{s-|XX}}.}}';

        $ref = new WikiRef($text);
        $ref->parseAllTemplates();
        $names = $ref->getTemplateNames();
        $this::assertEquals('lien web', $names[0]);
        $this::assertEquals('ouvrage', $names[2]);
    }
}
