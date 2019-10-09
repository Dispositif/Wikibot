<?php

declare(strict_types=1);

namespace App\Domain;

interface CorpusInterface
{
    public function inCorpus(string $element, string $corpusName): bool;

    public function addNewElementToCorpus(string $corpusName, string $element): bool;
}
