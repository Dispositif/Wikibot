<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

use Codedungeon\PHPCliColors\Color;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class Logger extends AbstractLogger implements LoggerInterface
{
    //    use LoggerTrait;

    public $verbose = false;
    public $debug = false;
    public $colorMode = false;

    /**
     * Ultralight logger.
     *
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        if (is_array($message)) {
            dump($message);
            echo "Envoi de array comme message de log...\n";

            return;
        }
        $message = trim($message);
        $date = date('Y-m-d H:i:s');
        switch ($level) {
            case 'emergency':
            case 'alert':
            case 'critical':
                $this->echoColor("[$level] ".$date.' : '.$message."\n", Color::BG_RED.Color::WHITE);
                if ($context !== []) {
                    dump($context);
                }
                $this->logInFile($level, $message);
                break;
            case 'error':
            case 'warning':
                $this->echoColor("[$level] ".$date.' : '.$message."\n", Color::BG_YELLOW.Color::BLACK);
                if ($context !== []) {
                    dump($context);
                }
                break;
            case 'notice':
                $this->echoColor("[$level] ".$message."\n");
                if ($context !== []) {
                    dump($context);
                }
                break;
            case 'info':
                if ($this->verbose || $this->debug) {
                    $this->echoColor("[$level] ".$message."\n", Color::GRAY);
                    if ($context !== []) {
                        dump($context);
                    }
                }
                break;
            case 'debug':
                if ($this->debug) {
                    $this->echoColor("[$level] ".$message."\n", Color::GRAY);
                    if ($context !== []) {
                        dump($context);
                    }
                }
                break;
        }
    }

    private function logInFile($level, string $message)
    {
        file_put_contents(
            __DIR__.'/resources/critical.log',
            date('d-m-Y H:i:s')." : $level : ".$message.PHP_EOL,
            FILE_APPEND
        );
    }

    private function echoColor(string $text, ?string $color = null)
    {
        if ($this->colorMode && !empty($color)) {
            echo $color.$text.Color::NORMAL;

            return;
        }
        echo $text;
    }

}
