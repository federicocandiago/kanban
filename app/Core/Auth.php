<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * The signed-in user: from the session, or from the "keep me signed in" cookie when the session has
 * expired (the server drops idle sessions after a while; the cookie lasts REMEMBER_DAYS from its last use).
 */
final class Auth
{
    private const COOKIE = 'kalban_remember';

    private static ?User $user = null;
    private static ?string $tokenScope = null;
    private static bool $cookieTried = false;

    /** The request is authenticated by an API token (no session): $scope is "read" or "write". */
    public static function actingAs(User $user, string $scope): void
    {
        self::$user = $user;
        self::$tokenScope = $scope;
    }

    public static function viaApi(): bool
    {
        return self::$tokenScope !== null;
    }

    public static function canWrite(): bool
    {
        return self::$tokenScope === null || self::$tokenScope === 'write';
    }

    public static function user(): ?User
    {
        if (self::$user === null && self::$tokenScope === null && ($id = Session::get('user_id'))) {
            self::$user = User::find((int) $id);
            if (self::$user === null) {
                Session::forget('user_id');
            }
        }
        if (self::$user === null && self::$tokenScope === null && !self::$cookieTried) {
            self::$cookieTried = true;
            self::$user = self::fromCookie();
        }

        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if ($user === null || !password_verify($password, (string) $user->password)) {
            return false;
        }
        self::login($user);

        return true;
    }

    public static function login(User $user, bool $remember = false): void
    {
        if ($remember) {
            self::remember($user);
        }
        Session::regenerate();
        Session::set('user_id', $user->id);
        if ($user->locale) {
            Session::set('locale', $user->locale);
        }
        $user->update(['last_login_at' => date('Y-m-d H:i:s')]);
        self::$user = $user;
    }

    public static function logout(): void
    {
        if ($token = self::cookie()) {
            Database::connection()->query('DELETE FROM remember_tokens WHERE selector = ?', [$token[0]]);
        }
        self::setCookie('', time() - 3600);
        self::$user = null;
        Session::destroy();
    }

    /** New password: every browser kept signed in has to sign in again, except this one if it was. */
    public static function forgetBrowsers(User $user): void
    {
        $here = self::cookie() !== null;
        Database::connection()->query('DELETE FROM remember_tokens WHERE user_id = ?', [$user->id]);
        if ($here) {
            self::remember($user);
        }
    }

    /** A new token for this browser: the cookie gets selector:validator, the database only the validator's hash. */
    private static function remember(User $user): void
    {
        [$selector, $validator] = [bin2hex(random_bytes(12)), bin2hex(random_bytes(32))];
        $expires = time() + self::days() * 86400;
        $now = date('Y-m-d H:i:s');
        Database::connection()->query('DELETE FROM remember_tokens WHERE expires_at < ?', [$now]); // housekeeping
        Database::connection()->insert('remember_tokens', [
            'user_id' => $user->id, 'selector' => $selector, 'validator_hash' => hash('sha256', $validator),
            'expires_at' => date('Y-m-d H:i:s', $expires), 'used_at' => $now, 'created_at' => $now,
        ]);
        self::setCookie("{$selector}:{$validator}", $expires);
    }

    /**
     * Signs in again from the cookie. The validator is not changed on use: a page sends several requests
     * at once, and a changed one would make the others look stolen. The expiry moves on (once a day).
     */
    private static function fromCookie(): ?User
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !($token = self::cookie())) {
            return null;
        }
        $row = Database::connection()->selectOne('SELECT * FROM remember_tokens WHERE selector = ?', [$token[0]]);
        if (!$row || strtotime((string) $row['expires_at']) < time() || !hash_equals((string) $row['validator_hash'], hash('sha256', $token[1]))) {
            self::setCookie('', time() - 3600);
            return null;
        }
        $user = User::find((int) $row['user_id']);
        if ($user === null) {
            return null;
        }
        if (strtotime((string) $row['used_at']) < time() - 86400) {
            $expires = time() + self::days() * 86400;
            Database::connection()->query('UPDATE remember_tokens SET used_at = ?, expires_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s', $expires), $row['id']]);
            self::setCookie("{$token[0]}:{$token[1]}", $expires);
        }
        Session::regenerate();
        Session::set('user_id', $user->id);
        if ($user->locale) {
            Session::set('locale', $user->locale);
        }

        return $user;
    }

    /** @return array{0: string, 1: string}|null selector and validator from the cookie */
    private static function cookie(): ?array
    {
        return preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', (string) ($_COOKIE[self::COOKIE] ?? ''), $m) ? [$m[1], $m[2]] : null;
    }

    private static function setCookie(string $value, int $expires): void
    {
        if (headers_sent() || ($value === '' && !isset($_COOKIE[self::COOKIE]))) {
            return;
        }
        setcookie(self::COOKIE, $value, [
            'expires' => $expires, 'path' => (base_url() ?: '') . '/', 'httponly' => true, 'samesite' => 'Lax',
            'secure' => ($_SERVER['HTTPS'] ?? 'off') !== 'off',
        ]);
    }

    private static function days(): int
    {
        return max(1, (int) env('REMEMBER_DAYS', 30));
    }
}
