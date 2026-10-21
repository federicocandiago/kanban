<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Counts attempts per key in fixed windows (e.g. "login|name@example.com": 5 in 15 minutes).
 * Keys are stored as SHA-256 hashes: no address or username is kept in clear, and no IP at all.
 * Limits come from .env (THROTTLE_*).
 */
final class Throttle
{
    /** Seconds to wait if $key already reached $max attempts in its window, otherwise null. */
    public static function blocked(string $key, int $max): ?int
    {
        $row = Database::connection()->selectOne('SELECT hits, expires_at FROM throttle WHERE `key` = ?', [hash('sha256', $key)]);
        if (!$row || strtotime((string) $row['expires_at']) <= time() || (int) $row['hits'] < $max) {
            return null;
        }

        return max(1, strtotime((string) $row['expires_at']) - time());
    }

    /** One more attempt; the window starts with the first one. */
    public static function hit(string $key, int $seconds): void
    {
        $db = Database::connection();
        $db->query(
            'INSERT INTO throttle (`key`, hits, expires_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
                hits = IF(expires_at <= NOW(), 1, hits + 1),
                expires_at = IF(expires_at <= NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), expires_at)',
            [hash('sha256', $key), $seconds, $seconds]
        );
        if (random_int(1, 50) === 1) { // now and then, forget the windows that are over
            $db->query('DELETE FROM throttle WHERE expires_at <= NOW()');
        }
    }

    public static function clear(string $key): void
    {
        Database::connection()->query('DELETE FROM throttle WHERE `key` = ?', [hash('sha256', $key)]);
    }

    /** A THROTTLE_* number from .env, never below 1. */
    public static function limit(string $name, int $default): int
    {
        return max(1, (int) env('THROTTLE_' . $name, $default));
    }

    /** "5 minutes" style wait, rounded up, for the messages. */
    public static function minutes(int $seconds): int
    {
        return (int) ceil($seconds / 60);
    }
}
