<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private static ?User $user = null;
    private static ?string $tokenScope = null;

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

    public static function login(User $user): void
    {
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
        self::$user = null;
        Session::destroy();
    }
}
