<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use Exception;
use GuzzleHttp\Client;

class SMS
{
    /**
     * @var Client
     */
    private $client;

    /**
     * SMS constructor.
     *
     * @param string $message
     *
     * @throws Exception
     */
    public function __construct(?string $message = null)
    {
        $this->client = new Client();
        if (!getenv('FREE_SMS_URL')) {
            throw new Exception('Pas d\'URL free mobile configurée');
        }
        if (!empty($message)) {
            $this->send($message);
        }
    }

    /**
     * @param string $message
     *
     * @return bool
     *
     * @throws Exception
     */
    public function send(string $message): bool
    {
        if (!getenv('FREE_SMS_URL')) {
            throw new Exception('Pas d\'URL free mobile configurée');
        }
        $sender = getenv('BOT_NAME') ?? '';
        $message = sprintf('%s : %s', $sender, $message);
        $url = getenv('FREE_SMS_URL').urlencode($message);

        $response = $this->client->get($url, ['timeout' => 120]);
        if (200 === $response->getStatusCode()) {
            return true;
        }

        return false;
    }
}
