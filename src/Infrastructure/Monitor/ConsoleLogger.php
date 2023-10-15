<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Infrastructure\Monitor;

use Codedungeon\PHPCliColors\Color;
use Exception;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

// todo move /Monitor
class ConsoleLogger extends AbstractLogger implements LoggerInterface
{
    //    use LoggerTrait;

    protected const CRITICAL_LOG_PATH = __DIR__ . '/../../../log/critical.log';
    public bool $verbose = false;
    public bool $debug = false;
    public bool $colorMode = false;

    public function __construct(public StatsInterface $stats = new NullStats())
    {
        try {
            $this->stats->increment('test.consolelogger');
        } catch (Exception $e) {
            $this->stats = new NullStats();
        }
    }

    public function __call(string $method, array $args): void
    {
        $this->notice('Call to undefined method ConsoleLogger:' . $method . '()');
    }

    /**
     * Ultralight logger.
     * @inheritDoc
     */
    public function log($level, $message, array $context = []): void
    {
        if (is_array($message)) {
            $this->error('Array comme message de log...', ['message' => $message]);

            return;
        }
        $message = trim($message);
        $date = date('Y-m-d H:i:s');

        $this->incrementStatsFromContext($context);
        if (isset($context['stats'])) {
            unset($context['stats']);
        }

        switch ($level) {
            case 'emergency':
            case 'alert':
            case 'critical':
                $this->echoColor("[$level] " . $date . ' : ' . $message . "\n", Color::BG_RED . Color::WHITE);
                if ($context !== []) {
                    dump($context);
                }
                $this->logInFile($level, $message);
                break;
            case 'error':
            case 'warning':
                $this->echoColor("[$level] " . $date . ' : ' . $message . "\n", Color::BG_YELLOW . Color::BLACK);
                if ($context !== []) {
                    dump($context);
                }
                break;
            case 'notice':
                $this->echoColor("[$level] " . $message . "\n");
                if ($context !== []) {
                    dump($context);
                }
                break;
            case 'info':
                if ($this->verbose || $this->debug) {
                    $this->echoColor("[$level] " . $message . "\n", Color::GRAY);
                    if ($context !== []) {
                        dump($context);
                    }
                }
                break;
            case 'debug':
                if ($this->debug) {
                    $this->echoColor("[$level] " . $message . "\n", Color::GRAY);
                    if ($context !== []) {
                        dump($context);
                    }
                }
                break;
            case 'echo':
                $this->echoColor($message . "\n");
                break;
        }
    }

    protected function incrementStatsFromContext(array $context): void
    {
        if (!isset($context['stats'])) {
            return;
        }
        if (is_string($context['stats']) && $context['stats'] !== '') {
            $this->stats->increment($context['stats']);
        }
        if (is_array($context['stats'])) {
            foreach ($context['stats'] as $tag) {
                $this->stats->increment($tag);
            }
        }
    }

    private function echoColor(string $text, ?string $color = null): void
    {
        if ($this->colorMode && !empty($color)) {
            echo $color . $text . Color::NORMAL;

            return;
        }
        echo $text;
    }

    private function logInFile($level, string $message): void
    {
        file_put_contents(
            self::CRITICAL_LOG_PATH,
            date('d-m-Y H:i:s') . " : $level : " . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
