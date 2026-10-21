<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Secrets the app must be able to read back (the calendar's app-specific password), encrypted
 * with APP_KEY from .env (libsodium secretbox, built into PHP). Someone who only gets the database
 * can't read them. Create the key with: php bin/key.php
 */
final class Crypt
{
    public static function ready(): bool
    {
        return self::key() !== null;
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key() ?? throw new \RuntimeException('APP_KEY is missing: run php bin/key.php')));
    }

    public static function decrypt(string $sealed): string
    {
        $raw = base64_decode($sealed, true) ?: '';
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key() ?? throw new \RuntimeException('APP_KEY is missing: run php bin/key.php')
        );
        if ($plain === false) {
            throw new \RuntimeException('Cannot decrypt: APP_KEY has changed?');
        }

        return $plain;
    }

    private static function key(): ?string
    {
        $key = base64_decode(preg_replace('/^base64:/', '', (string) env('APP_KEY', '')) ?? '', true);

        return $key !== false && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $key : null;
    }
}
