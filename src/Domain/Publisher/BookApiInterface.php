<?php
declare(strict_types=1);

namespace App\Domain\Publisher;


interface BookApiInterface
{
    public function getDataByIsbn(string $isbn);
    public function getMapper();
}
