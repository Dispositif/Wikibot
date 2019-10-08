<?php
declare(strict_types=1);


namespace App\Infrastructure;


abstract class AbstractBookApiAdapter
{
    protected $api;
    protected $mapper;

    final public function getMapper()
    {
        return $this->mapper;
    }
}
