<?php


namespace App\Domain;


interface BookApiInterface
{
    public function getDataByIsbn(string $isbn);
}
