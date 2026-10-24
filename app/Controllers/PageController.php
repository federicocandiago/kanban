<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;

final class PageController extends Controller
{
    /** A fresh CSRF token for a page whose session expired meanwhile (app.js asks after a 419). */
    public function csrf(Request $request): Response
    {
        return Response::json(['token' => \App\Core\Csrf::token(), 'signedIn' => Auth::check()])->withHeader('Cache-Control', 'no-store');
    }

    public function terms(Request $request): Response
    {
        return view('pages/legal/terms', ['title' => __('Terms of Service')]);
    }

    public function privacy(Request $request): Response
    {
        return view('pages/legal/privacy', ['title' => __('Privacy Policy')]);
    }

    public function cookies(Request $request): Response
    {
        return view('pages/legal/cookies', ['title' => __('Cookie Policy')]);
    }

    public function about(Request $request): Response
    {
        return view('pages/legal/about', ['title' => __('About')]);
    }

    public function locale(Request $request, string $code): Response
    {
        if (in_array($code, Translator::available(), true)) {
            Session::set('locale', $code);
            Auth::user()?->update(['locale' => $code]);
        }
        $back = (string) ($request->header('Referer') ?? '/');
        $sameHost = parse_url($back, PHP_URL_HOST) === parse_url('//' . $request->header('Host'), PHP_URL_HOST);

        return redirect($sameHost ? $back : '/');
    }
}
