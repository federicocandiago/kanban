<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Runs database/migrations/*.sql in name order, once each (tracked in the `migrations` table).
 * Used by bin/migrate.php and, automatically, by App when a table or column is missing.
 */
final class Migrator
{
    public function __construct(private readonly Database $db, private readonly string $path)
    {
    }

    public static function make(): self
    {
        return new self(Database::connection(), BASE_PATH . '/database/migrations');
    }

    /**
     * Brings the database up to date and creates the first admin from .env.
     * @return string[] names of the migrations just executed
     */
    public static function install(): array
    {
        $migrator = self::make();
        $ran = $migrator->migrate();
        $migrator->seedAdmin();

        return $ran;
    }

    /** @return string[] names of the migrations not executed yet */
    public function pending(): array
    {
        $this->ensureTable();
        $done = array_column($this->db->select('SELECT migration FROM migrations'), 'migration');
        $files = array_map(static fn (string $f): string => basename($f, '.sql'), glob($this->path . '/*.sql') ?: []);
        sort($files);

        return array_values(array_diff($files, $done));
    }

    /** @return string[] names of the migrations just executed */
    public function migrate(): array
    {
        $ran = [];
        foreach ($this->pending() as $name) {
            foreach ($this->statements((string) file_get_contents("{$this->path}/{$name}.sql")) as $sql) {
                $this->db->exec($sql);
            }
            // IGNORE: two first requests arriving together may both get here
            $this->db->query('INSERT IGNORE INTO migrations (migration, ran_at) VALUES (?, ?)', [$name, date('Y-m-d H:i:s')]);
            $ran[] = $name;
        }

        return $ran;
    }

    /** Creates the first administrator from .env if there is no admin yet. */
    public function seedAdmin(): ?string
    {
        $email = mb_strtolower((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');
        if ($email === '' || $password === '' || User::count('is_admin = 1') > 0) {
            return null;
        }

        if ($existing = User::findByEmail($email)) {
            $existing->update(['is_admin' => 1]);
        } else {
            User::register([
                'name' => (string) env('ADMIN_NAME', 'Admin'),
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'email_verified_at' => date('Y-m-d H:i:s'),
                'is_admin' => 1,
            ]);
        }

        return $email;
    }

    public function dropAll(): void
    {
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->db->select('SHOW TABLES') as $row) {
            $this->db->exec('DROP TABLE IF EXISTS `' . reset($row) . '`');
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function ensureTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(190) NOT NULL UNIQUE,
                ran_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** Splits a .sql file into statements: "--" comment lines removed, ";" at end of line separates. */
    private function statements(string $sql): array
    {
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = preg_split('/;\s*$/m', $sql) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== ''));
    }
}
