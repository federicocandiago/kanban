<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ApiToken;
use App\Services\ApiSpec;

/** The API documentation: the OpenAPI document, and a page drawn from it (public, like the spec). */
final class DocsController extends Controller
{
    public function page(Request $request): Response
    {
        if (!ApiToken::enabled()) {
            abort(404);
        }

        return view('pages/api-docs', ['title' => __('API'), 'doc' => ApiSpec::document(), 'bodyClass' => 'is-docs']);
    }

    public function spec(Request $request): Response
    {
        if (!ApiToken::enabled()) {
            return Response::problem(404, __('Page not found'), __('The API is turned off on this site.'));
        }

        return Response::json(ApiSpec::document())->withHeader('Access-Control-Allow-Origin', '*');
    }
}
