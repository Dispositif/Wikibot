<?php


namespace App\Domain\Publisher;


interface BookApiInterface
{
    public function getDataByIsbn(string $isbn);
    public function getMapper();
}
