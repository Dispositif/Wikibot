<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use PDO;
use Simplon\Mysql\PDOConnector;

require_once __DIR__ . '/../myBootstrap.php';

/**
 * Lightweight schema migration runner — no framework, mirrors this project's existing
 * "plain SQL + simplon/mysql" style (DbAdapter, database_schema.sql).
 *
 * database_schema.sql is the fresh-install snapshot, mounted at
 * docker-entrypoint-initdb.d — MySQL only executes it once, against an empty data
 * volume. It is NOT re-run when the schema changes on an already-initialized database
 * (any dev machine with existing data, and matou once deployed there). This script is
 * what actually applies src/Infrastructure/resources/migrations/*.sql to such a
 * database — run it (`make db-migrate`) after every `git pull` that adds one.
 *
 * Migration files must be idempotent (CREATE TABLE IF NOT EXISTS, etc.) : that makes
 * re-running this script against a freshly initialized container — which already has
 * everything from database_schema.sql — a harmless no-op instead of an error.
 */

const MIGRATIONS_DIR = __DIR__ . '/../../Infrastructure/resources/migrations';

echo "*** DB migration runner ***\n";

$pdoConnector = new PDOConnector(
    getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE')
);
$pdo = $pdoConnector->connect('utf8', ['port' => getenv('MYSQL_PORT')]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `version` varchar(100) NOT NULL,
        `applied_at` datetime NOT NULL,
        PRIMARY KEY (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
);

$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(MIGRATIONS_DIR . '/*.sql');
sort($files); // filenames are zero-padded ("0001_...") so lexical sort == chronological order

if (empty($files)) {
    echo "No migration file found in " . MIGRATIONS_DIR . "\n";
    exit;
}

foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (in_array($version, $applied, true)) {
        echo "  [skip] $version (already applied)\n";
        continue;
    }

    echo "  [run]  $version\n";
    $sql = file_get_contents($file);
    foreach (splitSqlStatements($sql) as $statement) {
        $pdo->exec($statement);
    }

    $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)');
    $stmt->execute(['version' => $version, 'applied_at' => date('Y-m-d H:i:s')]);
    echo "  [done] $version\n";
}

echo "*** Migrations up to date ***\n";

/**
 * Naive split on ";" after stripping "--" line comments — fine for the DDL-only
 * migration files this project writes (no string literal contains ";" or starts a
 * line with "--"). Not a general-purpose SQL parser.
 *
 * @return string[]
 */
function splitSqlStatements(string $sql): array
{
    $withoutComments = preg_replace('/^--.*$/m', '', $sql);
    $statements = array_map('trim', explode(';', $withoutComments));

    return array_values(array_filter($statements, static fn(string $s) => $s !== ''));
}
