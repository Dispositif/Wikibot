<?php

namespace App\src\Infrastructure;

class Worker
{
    public function __construct()
    {
        $this->keepRunning = true;
//        pcntl_async_signals(true);
//        pcntl_signal(SIGTERM, function() {
//            $this->keepRunning = false;
//        });
    }

    public function run()
    {
        while ($this->keepRunning) {
            // do

            if(time() - $this->started >= 60 * 60 * 2) {
                $this->keepRunning = false;
            }

        }
    }
}
