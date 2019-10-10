<?php

declare(strict_types=1);

namespace App\Infrastructure;

use SplObserver;
use SplSubject;

/**
 * TODO: not used
 * Observer pattern. For class which implements SplSubject.
 * Trait SubjectTrait.
 */
trait SubjectTrait
{
    protected $observers = [];

    public function attach(SplObserver $observer)
    {
        $this->observers[] = $observer;

        return $this;
    }

    public function detach(SplObserver $observer)
    {
        if (is_int($key = array_search($observer, $this->observers, true))) {
            unset($this->observers[$key]);
        }

        return $this;
    }

    public function notify()
    {
        foreach ($this->observers as $observer) {
            /*
             * @var $observer SplObserver
             */
            $observer->update($this);
        }
    }
}