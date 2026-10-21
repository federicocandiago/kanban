<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Translator;
use App\Core\View;
use App\Models\User;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

/** Translate an English source string; ":name" placeholders are replaced. */
function __(string $message, array $replace = []): string
{
    return Translator::get($message, $replace);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function view(string $name, array $data = [], ?string $layout = 'layouts/app', int $status = 200): Response
{
    return Response::html(View::render($name, $data, $layout), $status);
}

function component(string $name, array $data = []): string
{
    return View::partial('components/' . $name, $data);
}

/** Pieces of a page that the server also sends alone, to be redrawn by app.js (views/partials). */
function partial(string $name, array $data = []): string
{
    return View::partial('partials/' . $name, $data);
}

function redirect(string $to): Response
{
    return Response::redirect($to);
}

function abort(int $status, string $message = ''): never
{
    throw new HttpException($status, $message);
}

function auth(): ?User
{
    return Auth::user();
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * Private data folder (attachments, avatars, cache, logs): STORAGE_PATH in .env, or storage/ in the project.
 * A relative STORAGE_PATH is resolved from the project folder (e.g. ../kalban-data).
 * Keep it outside the web root on shared hosting.
 */
function storage_path(string $path = ''): string
{
    $root = (string) env('STORAGE_PATH', 'storage');
    if (!str_starts_with($root, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $root)) {
        $root = BASE_PATH . '/' . $root;
    }
    $root = rtrim($root, '/');

    return $path === '' ? $root : $root . '/' . ltrim($path, '/');
}

/**
 * Path the site lives under, taken from APP_URL: "" for https://example.com,
 * "/kalban" for https://example.com/kalban.
 */
function base_url(): string
{
    static $base = null;

    return $base ??= rtrim((string) parse_url((string) env('APP_URL', ''), PHP_URL_PATH), '/');
}

/** Site-relative URL: url('/login') → /kalban/login */
function url(string $path = '/'): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = (defined('PUBLIC_PATH') ? PUBLIC_PATH : BASE_PATH . '/public') . '/' . ltrim($path, '/');
    $version = is_file($file) ? '?v=' . filemtime($file) : '';

    return url($path) . $version;
}

function locale(): string
{
    return Translator::locale();
}

function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }

    $number = $i === 0 || $size >= 100 || floor($size) == $size ? number_format($size) : number_format($size, 1, locale() === 'it' ? ',' : '.', '');

    return $number . ' ' . $units[$i];
}

function format_date(?string $datetime): string
{
    return $datetime ? date(locale() === 'it' ? 'd/m/Y H:i' : 'Y-m-d H:i', strtotime($datetime)) : '—';
}

/**
 * <time> for logs: within a day relative ("just now", "5 min ago", "3 h ago"), with the full
 * date on hover; from a day on, the full date.
 */
function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '<time>—</time>';
    }
    $time = strtotime($datetime);
    $seconds = time() - $time;
    $full = format_date($datetime);
    $text = match (true) {
        $seconds < 0 || $seconds >= 86400 => $full,
        $seconds < 60 => __('just now'),
        $seconds < 3600 => __(':n min ago', ['n' => intdiv($seconds, 60)]),
        default => __(':n h ago', ['n' => intdiv($seconds, 3600)]),
    };

    return '<time datetime="' . e(date('c', $time)) . '"' . ($text !== $full ? ' title="' . e($full) . '"' : '') . '>' . e($text) . '</time>';
}

/** Short day for card badges: "21 ott" / "Oct 21" (the year only when it is not the current one). */
function format_day(?string $date): string
{
    if (!$date) {
        return '';
    }
    $time = strtotime($date);
    $month = mb_substr(month_name((int) date('n', $time)), 0, 3);   // Oct / ott
    $month = locale() === 'it' ? mb_strtolower($month) : $month;
    $day = locale() === 'it' ? date('j', $time) . ' ' . $month : $month . ' ' . date('j', $time);

    return date('Y', $time) === date('Y') ? $day : $day . ' ' . date('Y', $time);
}

/** Random RFC 4122 version 4 UUID. */
function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
    $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function month_name(int $month): string
{
    return [__('January'), __('February'), __('March'), __('April'), __('May'), __('June'), __('July'),
        __('August'), __('September'), __('October'), __('November'), __('December')][$month - 1];
}

/** Short weekday, 1 = Monday … 7 = Sunday (ISO-8601, as date('N')). */
function weekday(int $day): string
{
    return [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')][$day - 1];
}

/** Inline JSON for data-* attributes and <script type="application/json">. */
function json(mixed $value): string
{
    return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}

/** Legal details from .env, used by the Privacy and Cookie policies. */
function legal(string $key): string
{
    return (string) match ($key) {
        'controller' => env('LEGAL_CONTROLLER_NAME', env('APP_NAME')),
        'address' => env('LEGAL_CONTROLLER_ADDRESS', ''),
        'email' => env('LEGAL_CONTACT_EMAIL', ''),
        'location' => ($l = (string) env('LEGAL_DATA_LOCATION', 'the European Union')) === 'the European Union' ? __('the European Union') : $l,
        'log_days' => env('LEGAL_LOG_RETENTION_DAYS', 30),
        'date' => date(locale() === 'it' ? 'd/m/Y' : 'F j, Y', strtotime((string) env('LEGAL_POLICY_DATE', '2026-10-16'))),
        default => '',
    };
}

/** EMAIL_VERIFICATION in .env: new users must confirm their address before signing in. */
function email_verification_enabled(): bool
{
    return (bool) env('EMAIL_VERIFICATION', false);
}

/** Absolute URL (for emails): APP_URL scheme + host + url(). */
function absolute_url(string $path): string
{
    $app = (string) env('APP_URL', '');
    $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', $app) ?: '';

    return $origin . url($path);
}

/** Back-office setting, falling back to REGISTRATION_ENABLED in .env (also when the DB is down). */
function registration_enabled(): bool
{
    try {
        return App\Models\Setting::registrationEnabled();
    } catch (App\Core\DatabaseException) { // a missing table must reach App: it triggers the auto-install
        return (bool) env('REGISTRATION_ENABLED', true);
    }
}
