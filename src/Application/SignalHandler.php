<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

/**
 * Cooperative shutdown on SIGTERM/SIGINT.
 *
 * Workers running as long-lived containers (restart: unless-stopped) are recreated by
 * `docker compose up -d` on every deploy : Docker sends SIGTERM, waits stop_grace_period,
 * then SIGKILLs. Default PHP behaviour on SIGTERM is to die on the spot, which can land
 * between a wiki edit and the journal write that records it. Flagging instead, and
 * checking the flag at the title boundary in AbstractBotTaskWorker::run(), turns a deploy
 * into "finish the current article, then exit 0".
 *
 * Static state on purpose : a process has exactly one signal disposition, and the flag
 * has to be readable from the worker loop and from the CLI epilogue alike.
 */
final class SignalHandler
{
    private static bool $stopRequested = false;
    private static bool $registered = false;

    private function __construct()
    {
    }

    /**
     * No-op when ext-pcntl is missing (it is not in the php:8.3-cli base image, only
     * added by docker/php/Dockerfile) : a worker run outside the container then keeps
     * the default "die immediately" behaviour rather than failing to start.
     */
    public static function register(): void
    {
        if (self::$registered || !function_exists('pcntl_async_signals')) {
            return;
        }
        self::$registered = true;

        pcntl_async_signals(true);
        foreach ([SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, static function (): void {
                self::$stopRequested = true;
            });
        }
    }

    public static function isStopRequested(): bool
    {
        return self::$stopRequested;
    }

    /** Test seam : raising a real SIGTERM would race with the test runner itself. */
    public static function requestStopForTesting(): void
    {
        self::$stopRequested = true;
    }

    /** Test seam — a process cannot really un-receive a signal. */
    public static function reset(): void
    {
        self::$stopRequested = false;
        self::$registered = false;
    }
}
