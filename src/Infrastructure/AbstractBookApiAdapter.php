<?php


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
