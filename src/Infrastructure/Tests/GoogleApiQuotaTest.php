<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\GoogleApiQuota;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Not a hermetic unit test : it exercises the real quota file (backed up and restored
 * around the test) with real OS subprocesses, because flock() contention across
 * *processes* can't be observed with in-process mocks/threads. Accepted trade-off.
 */
class GoogleApiQuotaTest extends TestCase
{
    private ?string $backup = null;

    protected function setUp(): void
    {
        if (file_exists(GoogleApiQuota::JSON_FILENAME)) {
            $this->backup = file_get_contents(GoogleApiQuota::JSON_FILENAME);
        }
    }

    protected function tearDown(): void
    {
        if ($this->backup !== null) {
            file_put_contents(GoogleApiQuota::JSON_FILENAME, $this->backup);
        } else {
            @unlink(GoogleApiQuota::JSON_FILENAME);
        }
    }

    public function testConcurrentIncrementsDoNotLoseUpdates()
    {
        $now = new DateTime();
        file_put_contents(
            GoogleApiQuota::JSON_FILENAME,
            json_encode(['type' => 'Google API Quota', 'date' => $now->format('c'), 'count' => 0])
        );

        $autoload = var_export(__DIR__ . '/../../../vendor/autoload.php', true);
        $code = "require {$autoload}; (new App\\Infrastructure\\GoogleApiQuota())->increment();";

        $processCount = 8;
        $running = [];
        for ($i = 0; $i < $processCount; $i++) {
            $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open([PHP_BINARY, '-r', $code], $descriptorSpec, $pipes);
            $this::assertNotFalse($proc, 'Failed to spawn increment subprocess');
            $running[] = ['proc' => $proc, 'pipes' => $pipes];
        }

        $stderrOutput = '';
        foreach ($running as $r) {
            $stderrOutput .= stream_get_contents($r['pipes'][2]);
            fclose($r['pipes'][1]);
            fclose($r['pipes'][2]);
            proc_close($r['proc']);
        }

        $this::assertSame('', trim($stderrOutput), 'Subprocess(es) reported an error');

        $final = json_decode((string)file_get_contents(GoogleApiQuota::JSON_FILENAME), true, 512, JSON_THROW_ON_ERROR);
        $this::assertSame(
            $processCount,
            $final['count'],
            'Lost update(s) : concurrent increment() calls raced on the quota file'
        );
    }
}
