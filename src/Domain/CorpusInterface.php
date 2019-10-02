<?php


namespace App\Domain;


interface CorpusInterface
{
    public function getFirstnameCorpus(): ?array;

    public function addNewElementToCorpus(string $corpusName, string $element): bool;
}
