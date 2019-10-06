<?php


namespace App\Domain\Publisher;


interface MapperInterface
{
    public function process($dataObject): array;
}
