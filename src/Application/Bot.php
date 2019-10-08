<?php
declare(strict_types=1);

namespace App\Application;


class Bot
{
    private $userAgent;

    public function __construct()
    {
        ini_set("user_agent", getenv('USER_AGENT'));
        $this->userAgent = getenv('USER_AGENT');
    }
    
}
