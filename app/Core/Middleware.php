<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\ApiToken;
use App\Models\User;

/**
 * Route guards. Each returns a Response to stop the request, or null to continue.
 */
final class Middleware
{
    public static function handle(string $name, Request $request): ?Response
    {
        return match ($name) {
            'auth' => self::auth($request),
            // public boards: signed in or not; who may do what is decided by Controller::board()
            'visitor' => Auth::user() ? self::auth($request) : null,
            'guest' => Auth::check() ? Response::redirect('/dashboard') : null,
            'admin' => Auth::user()?->isAdmin() ? null : self::deny($request, 403, '/'),
            'api' => self::api($request),
            'write' => Auth::canWrite() ? null : Response::problem(403, __('Access denied'), __('This token is read-only.')),
            default => throw new \LogicException("Unknown middleware: {$name}"),
        };
    }

    private static function auth(Request $request): ?Response
    {
        $user = Auth::user();
        if ($user === null) {
            return self::deny($request, 401, '/login');
        }
        if ($user->isSuspended()) {
            return $request->wantsJson()
                ? Response::json(['ok' => false, 'message' => __('Your account is suspended.')], 403)
                : view('pages/stop', [
                    'title' => __('Account suspended'),
                    'heading' => __('Account suspended'),
                    'text' => __('Your account has been paused by an administrator. Your data is safe; contact the administrator to know more.'),
                    'logout' => true,
                ], 'layouts/minimal', 403);
        }

        return null;
    }

    /**
     * API calls: "Authorization: Bearer kalban_…". The token must exist, not be expired, belong to an
     * active user who turned the API on; then THROTTLE_API_PER_MINUTE requests a minute per token.
     */
    private static function api(Request $request): ?Response
    {
        if (!ApiToken::enabled()) {
            return Response::problem(404, __('Page not found'), __('The API is turned off on this site.'));
        }
        $secret = (string) preg_replace('/^Bearer\s+/i', '', (string) ($request->header('Authorization') ?? ''), 1, $bearer);
        $token = $bearer ? ApiToken::fromSecret(trim($secret)) : null;
        $user = $token ? User::find((int) $token->user_id) : null;
        $unauthorized = static fn (string $detail): Response => Response::problem(401, __('Unauthorized'), $detail)
            ->withHeader('WWW-Authenticate', 'Bearer realm="api"');

        if (!$token || !$user || !$user->api_enabled) {
            return $unauthorized(__('Send a valid API token in the header "Authorization: Bearer …".'));
        }
        if ($token->isExpired()) {
            return $unauthorized(__('The API token has expired: generate a new one from Settings › API access.'));
        }
        if ($user->isSuspended()) {
            return Response::problem(403, __('Access denied'), __('Your account is suspended.'));
        }

        $key = 'api|' . $token->token_hash;
        if ($wait = Throttle::blocked($key, Throttle::limit('API_PER_MINUTE', 60))) {
            return Response::problem(429, __('Too Many Requests'), __('Too many requests: try again in :s seconds.', ['s' => $wait]))
                ->withHeader('Retry-After', (string) $wait);
        }
        Throttle::hit($key, 60);

        Auth::actingAs($user, (string) $token->scope);
        Translator::setLocale((string) ($user->locale ?: env('APP_LOCALE', 'en')));
        $token->touch();

        return null;
    }

    private static function deny(Request $request, int $status, string $redirect): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'message' => __('You are not allowed to do this.')], $status);
        }
        if ($status === 403) {
            throw new HttpException(403);
        }

        return Response::redirect($redirect);
    }
}
