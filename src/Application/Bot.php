<?php


namespace App\Application;


class Bot
{
    private $userAgent = '';

    public function __construct($app=null) {
        $this->setUserAgent();
    }

    protected function setUserAgent(): void
    {
        ini_set("user_agent", $_ENV['USER_AGENT']);
        $this->userAgent = $_ENV['USER_AGENT'];
    }

}
