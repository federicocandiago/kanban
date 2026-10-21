<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\ApiToken;

final class MeController extends ApiController
{
    /** Who the token belongs to, and what it can do. */
    public function me(Request $request): Response
    {
        $token = ApiToken::forUser(auth());

        return $this->data([
            ...Resource::user(auth()),
            'token' => ['scope' => (string) $token->scope, 'expires_at' => Resource::time($token->expires_at)],
        ]);
    }

    /** The user's own labels: the ones they can put on cards. */
    public function labels(Request $request): Response
    {
        return $this->data(array_map([Resource::class, 'label'], auth()->labels()));
    }
}
