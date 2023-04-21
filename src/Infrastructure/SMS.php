<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\SMSInterface;
use Exception;
use GuzzleHttp\Client;

class SMS implements SMSInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
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
     * @throws Exception
     */
    public function send(string $message): bool
    {
        $sender = getenv('BOT_NAME') ?? '';
        $message = sprintf('%s : %s', $sender, $message);
        $url = getenv('FREE_SMS_URL') . urlencode($message);

        $response = $this->client->get($url, ['timeout' => 120]);

        return 200 === $response->getStatusCode();
    }
}
