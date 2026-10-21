<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Throttle;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Models\User;
use App\Models\Invite;
use App\Services\Mail;
use App\Services\Palette;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return view('pages/auth/login', ['title' => __('Sign in'), 'bodyClass' => 'is-auth']);
    }

    public function login(Request $request): Response
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        // brute force: THROTTLE_LOGIN_ATTEMPTS wrong passwords per account, then a pause of THROTTLE_LOGIN_MINUTES
        $key = 'login|' . mb_strtolower(trim($email));
        if ($wait = Throttle::blocked($key, Throttle::limit('LOGIN_ATTEMPTS', 5))) {
            $message = __('Too many failed attempts for this account: try again in :min minutes.', ['min' => Throttle::minutes($wait)]);
            return $this->fail($message, ['email' => $message], 429);
        }

        $user = User::findByEmail($email);
        if ($user === null || !password_verify($password, (string) $user->password)) {
            Throttle::hit($key, Throttle::limit('LOGIN_MINUTES', 15) * 60);
            usleep(300_000);
            return $this->fail(__('Wrong email or password.'), ['email' => __('Wrong email or password.')]);
        }
        Throttle::clear($key);
        if (email_verification_enabled() && !$user->isVerified()) {
            return $this->fail(__('Please confirm your email first: open the link we sent you (check the spam folder too).'), [
                'email' => __('Email not confirmed yet.'),
            ]);
        }
        Auth::login($user);

        return $this->ok('', ['redirect' => '/dashboard']);
    }

    /** ?email= comes from an invitation email: that address may sign up even when registration is closed. */
    public function showRegister(Request $request): Response
    {
        $email = mb_strtolower((string) $request->input('email', ''));
        if (!registration_enabled() && !$this->invited($email)) {
            abort(404);
        }

        return view('pages/auth/register', [
            'bodyClass' => 'is-auth',
            'title' => __('Create account'),
            'locales' => Translator::available(),
            'email' => $email,
            'invited' => $this->invited($email),
        ]);
    }

    public function register(Request $request): Response
    {
        $email = mb_strtolower((string) $request->input('email', ''));
        if (!registration_enabled() && !$this->invited($email)) {
            return $this->fail(__('Registration is currently closed.'), [], 403);
        }
        // one counter for the whole site, no IP: THROTTLE_REGISTER_PER_HOUR attempts per hour
        if (Throttle::blocked('register', Throttle::limit('REGISTER_PER_HOUR', 30))) {
            return $this->fail(__('There have been too many sign-up attempts on :app: please try again later.', ['app' => env('APP_NAME')]), [], 429);
        }
        Throttle::hit('register', 3600);

        $name = (string) $request->input('name', '');
        $password = (string) $request->input('password', '');
        $locale = (string) $request->input('locale', locale());
        $color = (string) $request->input('color', Palette::DEFAULT);
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = __('Please enter your name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('Please enter a valid email address.');
        } elseif (User::findByEmail($email)) {
            $errors['email'] = __('This email is already registered.');
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = __('The password must be at least 8 characters.');
        } elseif ($password !== $request->input('password_confirmation')) {
            $errors['password_confirmation'] = __('The passwords do not match.');
        }
        if (!in_array($locale, Translator::available(), true)) {
            $errors['locale'] = __('Invalid language.');
        }
        if (!Palette::has($color)) {
            $errors['color'] = __('Choose a colour.');
        }
        if (!$request->input('terms')) {
            $errors['terms'] = __('You must accept the terms of service.');
        }
        if ($errors) {
            return $this->fail(__('Please check the form.'), $errors);
        }

        $user = User::register([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'email_verified_at' => email_verification_enabled() ? null : date('Y-m-d H:i:s'),
            'locale' => $locale,
            'color' => $color,
        ]);

        if (email_verification_enabled()) {
            Translator::setLocale($locale);
            try {
                $this->sendVerification($user);
            } catch (\Throwable $e) {
                error_log('Kalban: verification email not sent: ' . $e->getMessage());
                return $this->ok(__('Account created, but the confirmation email could not be sent. Try again from «Need a new link?».'), ['redirect' => '/verify/resend']);
            }

            return $this->ok(__('Almost done! We sent a confirmation link to :email. Check the spam folder too.', ['email' => $email]), ['redirect' => '/login']);
        }

        Auth::login($user);
        Translator::setLocale($locale); // the welcome message already in the chosen language

        return $this->ok(__('Welcome aboard, :name!', ['name' => $name]), ['redirect' => '/dashboard']);
    }

    /** Link from the confirmation email. */
    public function verify(Request $request, string $token): Response
    {
        $user = User::findByVerificationToken($token);
        if ($user === null) {
            Session::flash('error', __('This confirmation link is not valid or has already been used.'));
            return redirect('/login');
        }

        $user->update(['email_verified_at' => date('Y-m-d H:i:s'), 'verification_token' => null]);
        Auth::login($user);
        Session::flash('success', __('Email confirmed. Welcome aboard, :name!', ['name' => $user->name]));

        return redirect('/dashboard');
    }

    public function showResend(Request $request): Response
    {
        return view('pages/auth/resend', ['title' => __('Confirm your email'), 'bodyClass' => 'is-auth']);
    }

    /** Sends a new link. The answer is the same whether the address exists or not (no account probing). */
    public function resend(Request $request): Response
    {
        $email = mb_strtolower((string) $request->input('email', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail(__('Please check the form.'), ['email' => __('Please enter a valid email address.')]);
        }

        $key = 'resend|' . $email;
        if ($wait = Throttle::blocked($key, Throttle::limit('RESEND_PER_HOUR', 3))) {
            return $this->fail(__('Too many requests for this address: try again in :min minutes.', ['min' => Throttle::minutes($wait)]), [], 429);
        }
        Throttle::hit($key, 3600);

        $user = User::findByEmail($email);
        $recent = $user?->verification_sent_at && strtotime((string) $user->verification_sent_at) > time() - 60;
        if ($user !== null && !$user->isVerified() && !$recent) {
            try {
                $this->sendVerification($user);
            } catch (\Throwable $e) {
                error_log('Kalban: verification email not sent: ' . $e->getMessage());
                return $this->fail(__('The email could not be sent right now. Please try again later.'));
            }
        }

        return $this->ok(__('If :email is waiting for confirmation, a new link is on its way. Check the spam folder too.', ['email' => $email]));
    }

    /** The "confirm your email" message, in the user's language. */
    private function sendVerification(User $user): void
    {
        Mail::send((string) $user->email, $user->locale, static fn (array $d): string => __('Confirm your email for :app', $d), 'verify', [
            'name' => (string) $user->name,
            'color' => $user->color(),
            'link' => absolute_url('/verify/' . $user->newVerificationToken()),
        ]);
    }

    private function invited(string $email): bool
    {
        return $email !== '' && Invite::count("email = ? AND status = 'pending'", [$email]) > 0;
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        return redirect('/');
    }
}
