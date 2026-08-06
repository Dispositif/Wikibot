<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\ExternLink\ExternLinkCheckVerdict;
use App\Domain\InfrastructurePorts\ExternLinkCheckRepositoryInterface;
use DateInterval;
use DateTimeImmutable;
use Simplon\Mysql\Mysql;

/**
 * MySQL-backed ExternLinkCheckRepositoryInterface, two tables (schema:
 * src/Infrastructure/resources/database_schema.sql) :
 *  - `extern_link_check` : one row per URL, the URL's own facts (status, verdict...).
 *  - `extern_link_check_page` : which pages currently have a problematic citation of it.
 * Split so the URL's status has a single source of truth (a URL shared by several
 * pages can't disagree with itself), and so pages don't duplicate the URL-level facts.
 */
class ExternLinkCheckAdapter implements ExternLinkCheckRepositoryInterface
{
    private const TABLE_CHECK = 'extern_link_check';
    private const TABLE_PAGE = 'extern_link_check_page';

    public function __construct(private readonly Mysql $db)
    {
    }

    public function recordFailure(
        string $page,
        string $url,
        ?string $registrableDomain,
        ?int $httpStatus,
        ?string $errorKind,
        ExternLinkCheckVerdict $verdict
    ): void
    {
        $urlHash = md5($url);
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $existing = $this->db->fetchRow(
            'SELECT id, attempt_count FROM ' . self::TABLE_CHECK . ' WHERE url_hash = :url_hash',
            ['url_hash' => $urlHash]
        );

        if ($existing !== null) {
            $checkId = (int)$existing['id'];
            $this->db->update(
                self::TABLE_CHECK,
                ['id' => $checkId],
                [
                    'http_status' => $httpStatus,
                    'error_kind' => $errorKind,
                    'verdict' => $verdict->value,
                    'attempt_count' => (int)$existing['attempt_count'] + 1,
                    'last_checked_at' => $now,
                ]
            );
        } else {
            $checkId = $this->db->insert(
                self::TABLE_CHECK,
                [
                    'url' => $url,
                    'url_hash' => $urlHash,
                    'registrable_domain' => $registrableDomain,
                    'http_status' => $httpStatus,
                    'error_kind' => $errorKind,
                    'verdict' => $verdict->value,
                    'attempt_count' => 1,
                    'first_seen_at' => $now,
                    'last_checked_at' => $now,
                ]
            );
        }

        $alreadyLinked = $this->db->fetchColumn(
            'SELECT 1 FROM ' . self::TABLE_PAGE . ' WHERE check_id = :check_id AND page = :page',
            ['check_id' => (int)$checkId, 'page' => $page]
        );
        if ($alreadyLinked === null) {
            $this->db->insert(self::TABLE_PAGE, ['check_id' => (int)$checkId, 'page' => $page]);
        }
    }

    public function recordSuccess(string $page, string $url): void
    {
        $checkId = $this->db->fetchColumn(
            'SELECT id FROM ' . self::TABLE_CHECK . ' WHERE url_hash = :url_hash',
            ['url_hash' => md5($url)]
        );
        if ($checkId === null) {
            return;
        }

        $this->db->delete(self::TABLE_PAGE, ['check_id' => (int)$checkId, 'page' => $page]);

        $remaining = $this->db->fetchColumn(
            'SELECT 1 FROM ' . self::TABLE_PAGE . ' WHERE check_id = :check_id',
            ['check_id' => (int)$checkId]
        );
        if ($remaining === null) {
            $this->db->delete(self::TABLE_CHECK, ['id' => (int)$checkId]);
        }
    }

    public function findDueForRecheck(ExternLinkCheckVerdict $verdict, DateInterval $olderThan, int $limit = 500): array
    {
        $threshold = (new DateTimeImmutable())->sub($olderThan)->format('Y-m-d H:i:s');

        $rows = $this->db->fetchRowMany(
            'SELECT c.url AS url, p.page AS page
             FROM ' . self::TABLE_CHECK . ' c
             JOIN ' . self::TABLE_PAGE . ' p ON p.check_id = c.id
             WHERE c.verdict = :verdict AND c.last_checked_at < :threshold
             LIMIT ' . max(1, $limit),
            ['verdict' => $verdict->value, 'threshold' => $threshold]
        );

        return $rows ?? [];
    }
}
