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
 * /!\ Will fail with too many concurrent requests.
 * Class GoogleRequestQuota
 *
 * @package App\Infrastructure
 */
class GoogleApiQuota
{
    /** {"date":"2020-03-23T00:19:56-07:00","count":43}  */
    const JSON_FILENAME   = __DIR__.'/resources/google_quota.json';
    const REBOOT_TIMEZONE = 'America/Los_Angeles';
    const REBOOT_HOUR     = 0;
    const DAILY_QUOTA     = 1000;

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
        if (!file_exists(static::JSON_FILENAME)) {
            return ['date' => '2020-01-01T00:00:20-07:00', 'count' => 0];
        }

        try {
            $json = file_get_contents(static::JSON_FILENAME);
            $array = (array)json_decode($json, true);
        } catch (Throwable $e) {
            throw new ConfigException('Error on Google Quota file : reading or JSON malformed.');
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

    private function setZero(): void
    {
        $now = new DateTime();
        $now->setTimezone(new DateTimeZone(static::REBOOT_TIMEZONE));
        $this->lastDate = $now;
        $this->count = 0;
        $this->saveDateInFile();
    }

    /**
     * @throws ConfigException
     */
    private function saveDateInFile(): void
    {
        $data = [
            'type' => 'Google API Quota',
            'date' => $this->lastDate->format('c'),
            'count' => $this->count,
        ];
        $result = file_put_contents(static::JSON_FILENAME, json_encode($data));
        if ($result === false) {
            throw new ConfigException("Can't write on Google Quota file.");
        }
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        $this->checkNewReboot();

        return $this->count;
    }

    public function isQuotaReached(): bool
    {
        $this->checkNewReboot();

        if ($this->count >= static::DAILY_QUOTA) {
            return true;
        }

        return false;
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
