<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\SearchService;

/** Global search: a preview while typing (the modal in the top bar), and the full results page. */
final class SearchController extends Controller
{
    public function page(Request $request): Response
    {
        $query = mb_substr(trim((string) $request->input('q', '')), 0, 100);

        return view('pages/search', [
            'title' => $query !== '' ? __('Search: :q', ['q' => $query]) : __('Search'),
            'query' => $query,
            'results' => (new SearchService(auth()))->search($query, 50),
            'bodyClass' => 'is-dashboard',
        ]);
    }

    public function preview(Request $request): Response
    {
        $query = mb_substr(trim((string) $request->input('q', '')), 0, 100);

        return Response::json(['ok' => true, 'html' => partial('search-results', [
            'query' => $query,
            'results' => (new SearchService(auth()))->search($query, 5),
            'preview' => true,
        ])]);
    }
}
