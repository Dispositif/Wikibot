<?php


namespace App\Domain;


interface MapperInterface
{
    public function process($dataObject): array;
}
