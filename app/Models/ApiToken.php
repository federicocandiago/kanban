<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The user's API token (one per user). The token is shown once when generated: only its SHA-256
 * hash is stored (32 random bytes can't be guessed, so a slow hash isn't needed), with the last
 * four characters to recognise it. It lasts API_TOKEN_DAYS days.
 */
final class ApiToken extends Model
{
    protected static string $table = 'api_tokens';

    public const PREFIX = 'kalban_';
    public const SCOPES = ['read', 'write'];

    public static function enabled(): bool
    {
        return (bool) env('API_ENABLED', true);
    }

    public static function days(): int
    {
        return max(1, (int) env('API_TOKEN_DAYS', 90));
    }

    public static function forUser(User $user): ?self
    {
        return self::first('user_id = ?', [$user->id]);
    }

    /**
     * Creates (or replaces: the old one stops working at once) the user's token.
     * @return string the token in clear, to show this one time
     */
    public static function issue(User $user, string $scope): string
    {
        $token = self::PREFIX . bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        static::db()->query(
            'REPLACE INTO api_tokens (user_id, token_hash, hint, scope, expires_at, last_used_at, reminded_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, ?)',
            [$user->id, hash('sha256', $token), substr($token, -4), in_array($scope, self::SCOPES, true) ? $scope : 'read',
                date('Y-m-d H:i:s', time() + self::days() * 86400), $now, $now]
        );

        return $token;
    }

    public static function revoke(User $user): void
    {
        static::db()->query('DELETE FROM api_tokens WHERE user_id = ?', [$user->id]);
    }

    /** The token sent in "Authorization: Bearer …", if it exists (expired ones too: the caller tells them apart). */
    public static function fromSecret(string $token): ?self
    {
        return str_starts_with($token, self::PREFIX) ? self::first('token_hash = ?', [hash('sha256', $token)]) : null;
    }

    public function isExpired(): bool
    {
        return strtotime((string) $this->expires_at) <= time();
    }

    public function canWrite(): bool
    {
        return $this->scope === 'write';
    }

    /** "kalban_…a3f9" */
    public function masked(): string
    {
        return self::PREFIX . '…' . $this->hint;
    }

    /** Written at most once a minute, not on every call. */
    public function touch(): void
    {
        if (!$this->last_used_at || strtotime((string) $this->last_used_at) < time() - 60) {
            static::db()->query('UPDATE api_tokens SET last_used_at = NOW() WHERE user_id = ?', [$this->user_id]);
        }
    }

    /** A week before it expires, one notification in the bell. */
    public static function remind(User $user): void
    {
        $token = self::forUser($user);
        if (!$token || $token->reminded_at || $token->isExpired() || strtotime((string) $token->expires_at) > time() + 7 * 86400) {
            return;
        }
        Notification::send((int) $user->id, 'token_expiring', ['date' => substr((string) $token->expires_at, 0, 10)]);
        static::db()->query('UPDATE api_tokens SET reminded_at = NOW() WHERE user_id = ?', [$user->id]);
    }
}
