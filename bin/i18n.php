<?php

/**
 * Translation catalogs.
 *   php bin/i18n.php   scan app/ views/ for __('...') → lang/messages.pot,
 *                      then add new strings to every lang/{locale}/LC_MESSAGES/messages.po
 *                      (existing translations are kept). Edit the .po files with any
 *                      editor or Poedit; no compilation needed.
 */

declare(strict_types=1);

use App\Core\Env;
use App\Core\Translator;

require __DIR__ . '/../bootstrap/app.php';

$sources = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $rel = substr($file->getPathname(), strlen(BASE_PATH) + 1);
    if ($file->getExtension() !== 'php' || !preg_match('#^(app|views|routes)/#', $rel)) {
        continue;
    }
    $code = (string) file_get_contents($file->getPathname());
    preg_match_all('/__\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/', $code, $matches, PREG_OFFSET_CAPTURE);
    foreach ($matches[1] as [$literal, $offset]) {
        $msgid = $literal[0] === "'"
            ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($literal, 1, -1))
            : stripcslashes(substr($literal, 1, -1));
        $line = substr_count($code, "\n", 0, $offset) + 1;
        $sources[$msgid][] = "{$rel}:{$line}";
    }
}

// The description shown on the home page comes from .env: make it translatable too.
Env::load(BASE_PATH . '/.env.example');
if ($description = env('APP_DESCRIPTION')) {
    $sources[$description][] = '.env.example:APP_DESCRIPTION';
}
ksort($sources, SORT_STRING | SORT_FLAG_CASE);

$escape = static fn (string $s): string => '"' . addcslashes($s, "\"\\\n\t") . '"';
$header = static fn (string $lang): string => implode("\n", [
    'msgid ""',
    'msgstr ""',
    '"Project-Id-Version: Kalban\n"',
    '"Language: ' . $lang . '\n"',
    '"MIME-Version: 1.0\n"',
    '"Content-Type: text/plain; charset=UTF-8\n"',
    '"Content-Transfer-Encoding: 8bit\n"',
    '"Plural-Forms: nplurals=2; plural=(n != 1);\n"',
    '',
    '',
]);

$build = static function (string $lang, array $translations) use ($sources, $escape, $header): string {
    $out = $header($lang);
    foreach ($sources as $msgid => $refs) {
        $out .= '#: ' . implode(' ', array_unique($refs)) . "\n";
        $out .= 'msgid ' . $escape((string) $msgid) . "\n";
        $out .= 'msgstr ' . $escape((string) ($translations[$msgid] ?? '')) . "\n\n";
    }
    return $out;
};

file_put_contents(BASE_PATH . '/lang/messages.pot', $build('', []));
echo 'messages.pot: ' . count($sources) . " strings\n";

foreach (Translator::available() as $locale) {
    $po = BASE_PATH . "/lang/{$locale}/LC_MESSAGES/messages.po";
    $existing = is_file($po) ? Translator::parsePo($po) : [];
    @mkdir(dirname($po), 0775, true);
    file_put_contents($po, $build($locale, $existing));
    $missing = count(array_filter(array_keys($sources), static fn ($id) => (string) ($existing[$id] ?? '') === ''));
    echo $locale === 'en'
        ? "en/messages.po: source language (empty msgstr = English text)\n"
        : "{$locale}/messages.po: {$missing} untranslated\n";
}
