<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exceptions\ConfigException;
use App\Domain\InfrastructurePorts\GoogleApiQuotaInterface;
use DateTime;
use DateTimeZone;
use Exception;
use Throwable;

/**
 * Count and increment, data saved in json file.
 * Set count to 0 everyday at 00:00 (America/Los_Angeles).
 * No need of SQL/singleton with the single file.
 * increment() serializes concurrent writers with flock() on a dedicated lock file (its
 * identity never changes, unlike the data file), re-reads the live count under that lock
 * (not the possibly-stale in-memory snapshot), and swaps the data file atomically via
 * write-temp-then-rename() so unlocked readers (getCount()/isQuotaReached()) never see a
 * half-written file either.
 * Class GoogleRequestQuota
 *
 * @package App\Infrastructure
 */
class GoogleApiQuota implements GoogleApiQuotaInterface
{
    /** {"date":"2020-03-23T00:19:56-07:00","count":43}  */
    final public const JSON_FILENAME   = __DIR__.'/resources/google_quota.json';
    final public const LOCK_FILENAME   = __DIR__.'/resources/google_quota.lock';
    final public const REBOOT_TIMEZONE = 'America/Los_Angeles';
    final public const REBOOT_HOUR     = 0;
    /** Google's published daily cap for the Books API (default free tier, confirmed nov. 2025). */
    final public const DAILY_QUOTA     = 1000;
    /** Operational ceiling checked by isQuotaReached() : safety margin below DAILY_QUOTA, single source
     *  of truth shared by every consumer (was duplicated as 900/950 literals across the codebase). */
    final public const SAFE_DAILY_QUOTA = 950;

    private DateTime $lastDate;
    private int $count = 0;
    /**
     * @var DateTime Today reboot date/time of the quota
     */
    private readonly DateTime $todayBoot;

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
     * @throws ConfigException
     */
    private function getFileData(): array
    {
        if (!file_exists(static::JSON_FILENAME)) {
            return ['date' => '2020-01-01T00:00:20-07:00', 'count' => 0];
        }

        try {
            $json = file_get_contents(static::JSON_FILENAME);
            $array = (array)json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new ConfigException('Error on Google Quota file : reading or JSON malformed.');
        }

        return $array;
    }

    private function checkNewReboot(): void
    {
        if ($this->isRebootDue($this->lastDate)) {
            $this->setZero();
        }
    }

    private function isRebootDue(DateTime $lastDate): bool
    {
        $now = new DateTime();
        $now->setTimezone(new DateTimeZone(static::REBOOT_TIMEZONE));

        if ($now->diff($lastDate, true)->format('%h') > 24) {
            return true;
        }

        return $lastDate < $this->todayBoot && $now > $this->todayBoot;
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
        $this->atomicSave(
            [
                'type' => 'Google API Quota',
                'date' => $this->lastDate->format('c'),
                'count' => $this->count,
            ]
        );
    }

    /**
     * Write-temp-then-rename() : rename() is atomic on the same filesystem, so a concurrent
     * unlocked reader always sees either the fully-old or fully-new file, never a partial one.
     *
     * @throws ConfigException
     */
    private function atomicSave(array $data): void
    {
        $tmpFile = static::JSON_FILENAME . '.tmp.' . getmypid() . '.' . uniqid('', true);
        if (file_put_contents($tmpFile, json_encode($data, JSON_THROW_ON_ERROR)) === false) {
            throw new ConfigException("Can't write on Google Quota file.");
        }
        if (!rename($tmpFile, static::JSON_FILENAME)) {
            @unlink($tmpFile);
            throw new ConfigException("Can't atomically save Google Quota file.");
        }
    }

    public function getCount(): int
    {
        $this->checkNewReboot();

        return $this->count;
    }

    public function isQuotaReached(): bool
    {
        $this->checkNewReboot();
        return $this->count >= static::SAFE_DAILY_QUOTA;
    }

    /**
     * @throws ConfigException
     */
    public function increment(): void
    {
        $lockHandle = fopen(static::LOCK_FILENAME, 'c');
        if ($lockHandle === false) {
            throw new ConfigException("Can't open Google Quota lock file.");
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new ConfigException("Can't lock Google Quota file.");
            }

            // Re-read the live state under the lock : another process may have incremented
            // (or reset at day rollover) since this object was built. Safe to read unlocked :
            // writes are atomic renames, so this can't observe a half-written file.
            $data = $this->getFileData();
            $lastDate = new DateTime($data['date'], new DateTimeZone(static::REBOOT_TIMEZONE));
            $count = (int)$data['count'];

            if ($this->isRebootDue($lastDate)) {
                $lastDate = new DateTime();
                $lastDate->setTimezone(new DateTimeZone(static::REBOOT_TIMEZONE));
                $count = 0;
            }

            $this->lastDate = $lastDate;
            $this->count = $count + 1;
            $this->saveDateInFile();
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }
}
