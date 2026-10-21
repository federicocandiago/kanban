<?php

/**
 * Usage: php bin/key.php — writes a new APP_KEY into .env, if it has none.
 * The key encrypts the calendar passwords: changing it later means connecting the calendars again.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}

$env = dirname(__DIR__) . '/.env';
$text = is_file($env) ? (string) file_get_contents($env) : '';
if (preg_match('/^APP_KEY=\S+/m', $text)) {
    exit("APP_KEY is already set in .env: nothing to do.\n");
}

$line = 'APP_KEY=base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
$text = preg_match('/^APP_KEY=.*$/m', $text) ? preg_replace('/^APP_KEY=.*$/m', $line, $text) : rtrim($text) . "\n" . $line . "\n";
file_put_contents($env, $text);
echo "✓ APP_KEY written to .env\n";
