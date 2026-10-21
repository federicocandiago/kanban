<?php

/**
 * Usage:
 *   php bin/migrate.php           run pending database/migrations/*.sql + create first admin
 *   php bin/migrate.php --fresh   drop every table, then migrate again
 * The site does the same automatically when it finds a missing table or column.
 */

declare(strict_types=1);

use App\Core\Database;
use App\Core\DatabaseException;
use App\Core\Migrator;

require __DIR__ . '/../bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}

$out = static fn (string $msg, string $color = '0') => fwrite(STDOUT, "\033[{$color}m{$msg}\033[0m\n");

try {
    $db = Database::fromEnv();
} catch (DatabaseException $e) {
    $out('✗ ' . $e->getMessage(), '31');
    $out(sprintf('  host=%s port=%s db=%s user=%s', env('DB_HOST'), env('DB_PORT'), env('DB_DATABASE'), env('DB_USERNAME')), '90');
    exit(1);
}

$out('✓ Connected to MySQL (' . $db->scalar('SELECT VERSION()') . ')', '32');
$migrator = new Migrator($db, BASE_PATH . '/database/migrations');

if (in_array('--fresh', $argv, true)) {
    $migrator->dropAll();
    $out('• All tables dropped', '33');
}

try {
    $ran = $migrator->migrate();
    $out($ran ? '✓ Migrated: ' . implode(', ', $ran) : '• Nothing to migrate', '32');

    if ($email = $migrator->seedAdmin()) {
        $out("✓ Administrator ready: {$email} (password from ADMIN_PASSWORD in .env)", '32');
    }
} catch (\Throwable $e) {
    $out('✗ Migration failed: ' . $e->getMessage(), '31');
    exit(1);
}
