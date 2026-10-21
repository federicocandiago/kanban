<?php

declare(strict_types=1);

namespace App\Core;

/**
 * gettext-style translations read straight from lang/{locale}/LC_MESSAGES/messages.po
 * (no compiled .mo or system locales needed). msgid = English source string.
 */
final class Translator
{
    private static string $locale = 'en';
    private static array $messages = [];

    public static function boot(): void
    {
        self::setLocale((string) Session::get('locale', env('APP_LOCALE', 'en')));
    }

    public static function available(): array
    {
        return array_map('trim', explode(',', (string) env('APP_LOCALES', 'en')));
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = in_array($locale, self::available(), true) ? $locale : (string) env('APP_LOCALE', 'en');
        $file = BASE_PATH . '/lang/' . self::$locale . '/LC_MESSAGES/messages.po';
        self::$messages = is_file($file) ? self::parsePo($file) : [];
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function get(string $message, array $replace = []): string
    {
        $text = self::$messages[$message] ?? '';
        $text = $text !== '' ? $text : $message;

        foreach ($replace as $key => $value) {
            $text = str_replace(':' . $key, (string) $value, $text);
        }

        return $text;
    }

    /** @return array<string,string> msgid => msgstr */
    public static function parsePo(string $file): array
    {
        $messages = [];
        $entry = ['msgid' => null, 'msgstr' => null];
        $current = null;
        $fuzzy = false;

        $commit = static function () use (&$messages, &$entry, &$fuzzy): void {
            if ($entry['msgid'] !== null && $entry['msgid'] !== '' && !$fuzzy) {
                $messages[$entry['msgid']] = (string) $entry['msgstr'];
            }
            $entry = ['msgid' => null, 'msgstr' => null];
            $fuzzy = false;
        };

        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '') {
                $commit();
                $current = null;
            } elseif (str_starts_with($line, '#,') && str_contains($line, 'fuzzy')) {
                $fuzzy = true;
            } elseif ($line[0] === '#') {
                continue;
            } elseif (preg_match('/^(msgid|msgstr)\s+"(.*)"$/', $line, $m)) {
                if ($m[1] === 'msgid' && $entry['msgid'] !== null) {
                    $commit();
                }
                $current = $m[1];
                $entry[$current] = self::unescape($m[2]);
            } elseif ($current !== null && preg_match('/^"(.*)"$/', $line, $m)) {
                $entry[$current] .= self::unescape($m[1]);
            }
        }
        $commit();

        return $messages;
    }

    private static function unescape(string $value): string
    {
        return stripcslashes($value);
    }
}
