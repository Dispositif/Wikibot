<?php

namespace App\Domain\Models\Wiki;

use PHPUnit\Framework\TestCase;

class GoogleLivresTemplateTest extends TestCase
{

    public function testIsGoogleBookURL()
    {
        $url = 'https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926&source=bl&ots=kiCzMrHO7b&sig=Jxt2Ybpig7Oo-Mtuzgp_sL5ipQ4&hl=fr&sa=X&ei=6SMLU_zIDarL0AX75YAI&ved=0CFEQ6AEwBA#v=onepage&q=D%C3%A9cret-Loi%2010%20septembre%201926&f=false';
        $this::assertEquals(
            true,
            GoogleLivresTemplate::isGoogleBookURL($url)
        );
    }

    public function testCreateFromURL()
    {
        $url = 'https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395';
        $this::assertEquals(
            '{{Google Livres|pbspjvZst5UC|page=395}}',
            GoogleLivresTemplate::createFromURL($url)->serialize()
        );

    }

    public function testCreateFromComplexURL()
    {

        $url = 'https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926&source=bl&ots=kiCzMrHO7b&sig=Jxt2Ybpig7Oo-Mtuzgp_sL5ipQ4&hl=fr&sa=X&ei=6SMLU_zIDarL0AX75YAI&ved=0CFEQ6AEwBA#v=onepage&q=D%C3%A9cret-Loi%2010%20septembre%201926&f=false';

        $this::assertEquals(
            '{{Google Livres|pbspjvZst5UC|page=395|surligne=D%C3%A9cret-Loi+10+septembre+1926}}',
            GoogleLivresTemplate::createFromURL($url)->serialize()
        );

    }
}
