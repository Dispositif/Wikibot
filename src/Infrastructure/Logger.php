<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

use App\Application\Color;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class Logger implements LoggerInterface
{
    use LoggerTrait;

    public $verbose = false;
    public $debug = false;

    /**
     * Ultralight logger.
     *
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        if (is_array($message)) {
            dump($message);
            echo "Envoi de array comme $message de log...\n";

            return;
        }
        switch ($level) {
            case 'emergency':
            case 'alert':
            case 'critical':
                echo Color::BG_RED.Color::WHITE.">>> ".$message."\n".Color::NORMAL;
                if (!empty($context)) {
                    dump($context);
                }
                $this->logInFile($level, $message);
                break;
            case 'error':
            case 'warning':
                echo Color::BG_YELLOW.Color::BLACK.">>> ".$message."\n".Color::NORMAL;
                if (!empty($context)) {
                    dump($context);
                }
                break;
            case 'notice':
                echo ">>> ".$message."\n".Color::NORMAL;
                if (!empty($context)) {
                    dump($context);
                }
                break;
            case 'info':
                if ($this->verbose || $this->debug) {
                    echo Color::GRAY."> ";
                    if (!empty($context)) {
                        dump($context);
                    }
                }
                break;
            case 'debug':
                if ($this->debug) {
                    echo Color::GRAY."> ";
                    if (!empty($context)) {
                        dump($context);
                    }
                }
                break;
        }
    }

    private function logInFile($level, string $message)
    {
        file_put_contents(__DIR__.'/resources/critical.log', date('d-m-Y H:i')." : $level : ".$message, FILE_APPEND);
    }

}
