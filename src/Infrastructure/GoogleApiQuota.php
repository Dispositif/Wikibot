<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exceptions\ConfigException;
use DateTime;
use DateTimeZone;
use Exception;
use Throwable;

/**
 * Count and increment, data saved in json file.
 * Set count to 0 everyday at 00:00 (America/Los_Angeles).
 * No need of SQL/singleton with the single file.
 * Class GoogleRequestQuota
 *
 * @package App\Infrastructure
 */
class GoogleApiQuota
{
    /** {"date":"2020-03-23T00:19:56-07:00","count":43}  */
    const FILENAME        = __DIR__.'/resources/google_quota.json';
    const REBOOT_TIMEZONE = 'America/Los_Angeles';
    const REBOOT_HOUR     = 0;

    /**
     * @var DateTime
     */
    private $lastDate;
    /**
     * @var int
     */
    private $count = 0;
    /**
     * @var DateTime Today reboot date/time of the quota
     */
    private $todayBoot;

    /**
     * GoogleRequestQuota constructor.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $data = $this->getFileData();
        $this->lastDate = new DateTime($data['date'], new DateTimeZone(static::REBOOT_TIMEZONE));
        $this->count = (int)$data['count'];

        // Today reboot date/time of the quota
        $todayBoot = new DateTime();
        $todayBoot->setTimezone(new DateTimeZone('America/Los_Angeles'))->setTime(static::REBOOT_HOUR, 0);
        $this->todayBoot = $todayBoot;

        $this->checkNewReboot();
    }

    /**
     * @return array
     * @throws ConfigException
     */
    private function getFileData(): array
    {
        if (!file_exists(static::FILENAME)) {
            return ['date' => '2020-01-01T00:00:20-07:00', 'count' => 0];
        }

        try {
            $json = file_get_contents(static::FILENAME);
            $array = json_decode($json, true);
        } catch (Throwable $e) {
            throw new ConfigException('file malformed.');
        }

        return $array;
    }

    private function checkNewReboot(): void
    {
        $now = new DateTime();
        $now->setTimezone(new DateTimeZone(static::REBOOT_TIMEZONE));

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
        $now = new DateTime();
        $now->setTimezone(new DateTimeZone(static::REBOOT_TIMEZONE));
        $this->lastDate = $now;
        $this->count = 0;
        $this->saveDateInFile();
    }

    private function saveDateInFile(): void
    {
        $data = [
            'date' => $this->lastDate->format('c'),
            'count' => $this->count,
        ];
        file_put_contents(static::FILENAME, json_encode($data));
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        $this->checkNewReboot();

        return $this->count;
    }

    /**
     *
     */
    public function increment(): void
    {
        $this->checkNewReboot();
        $this->count = $this->count + 1;
        $this->saveDateInFile();
    }
}
