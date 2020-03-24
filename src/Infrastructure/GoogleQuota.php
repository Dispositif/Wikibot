<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exceptions\ConfigException;
use Throwable;

/**
 * Count and increment, data saved in json file.
 * Set count to 0 everyday at 08:00 (America/Los_Angeles)
 * {"date":"2020-03-23T00:19:56-07:00","count":43}
 * Class GoogleQuota
 *
 * @package App\Infrastructure
 */
class GoogleQuota
{
    const FILENAME        = __DIR__.'/resources/googlequota.json';
    const REBOOT_TIMEZONE = 'America/Los_Angeles';

    /**
     * @var \DateTime
     */
    private $lastDate;
    /**
     * @var int
     */
    private $count = 0;
    /**
     * @var \DateTime
     */
    private $todayBoot;

    /**
     * GoogleQuota constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $data = $this->getFileData();
        $this->lastDate = new \DateTime($data['date'], new \DateTimeZone(self::REBOOT_TIMEZONE));
        $this->count = (int) $data['count'];

        // Today reboot date/time of the quota
        $todayBoot = new \DateTime();
        $todayBoot->setTimezone(new \DateTimeZone('America/Los_Angeles'))->setTime(8, 0);
        $this->todayBoot = $todayBoot;

        $this->checkNewDay();
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        $this->checkNewDay();

        return $this->count;
    }

    /**
     *
     */
    public function increment(): void
    {
        $this->checkNewDay();
        $this->count = $this->count + 1;
        $this->saveFile();
    }

    private function checkNewDay(): void
    {
        $now = new \DateTime();
        $now->setTimezone(new \DateTimeZone(self::REBOOT_TIMEZONE));

        if ($now->diff($this->lastDate, true)->format('%h') > 24) {
            $this->setZero();

            return;
        }
        if ($this->lastDate < $this->todayBoot && $now > $this->todayBoot) {
            $this->setZero();
        }
    }

    private function setZero()
    {
        $now = new \DateTime();
        $now->setTimezone(new \DateTimeZone(self::REBOOT_TIMEZONE));
        $this->lastDate = $now;
        $this->count = 0;
        $this->saveFile();
    }

    private function saveFile(): void
    {
        $data = [
            'date' => $this->lastDate->format('c'),
            'count' => $this->count,
        ];
        file_put_contents(static::FILENAME, json_encode($data));
    }

    /**
     * @return array
     * @throws ConfigException
     */
    private function getFileData(): array
    {
        if (!file_exists(static::FILENAME)) {
            // todo create file ?
            throw new ConfigException('No GoogleQuota file found.');
        }

        try {
            $json = file_get_contents(self::FILENAME);
            $array = json_decode($json, true);
        } catch (Throwable $e) {
            throw new ConfigException('file malformed.');
        }

        return $array;
    }
}
