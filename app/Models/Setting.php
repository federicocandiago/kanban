<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Key/value settings editable from the back-office. A missing key falls back to .env.
 */
final class Setting
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            $rows = Database::connection()->select('SELECT `key`, `value` FROM settings');
            self::$cache = array_column($rows, 'value', 'key');
        }

        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::connection()->query(
            'INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
            [$key, $value]
        );
        self::$cache = null;
    }

    public static function registrationEnabled(): bool
    {
        return (bool) self::get('registration_enabled', env('REGISTRATION_ENABLED', true) ? '1' : '0');
    }
}
