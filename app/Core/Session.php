<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        session_name((string) env('SESSION_NAME', 'kalban_session'));
        // in storage/sessions, kept SESSION_MINUTES: on shared hosting the common folder is often
        // emptied after ~24 idle minutes by any site's clean-up (the "keep me signed in" cookie covers the rest)
        $dir = BASE_PATH . '/storage/sessions';
        if (is_dir($dir) || @mkdir($dir, 0700, true)) {
            if (is_writable($dir)) {
                session_save_path($dir);
                ini_set('session.gc_maxlifetime', (string) (max(30, (int) env('SESSION_MINUTES', 720)) * 60));
            }
        }
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => ($_SERVER['HTTPS'] ?? 'off') !== 'off',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Store a message for the next page view. */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $messages;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
