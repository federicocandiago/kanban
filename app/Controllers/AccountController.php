<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Throttle;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Translator;
use App\Core\Database;
use App\Models\ApiToken;
use App\Models\CalendarAccount;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\AttachmentService;
use App\Services\DataTransfer;
use App\Services\Palette;

final class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        return view('pages/account', [
            'title' => __('My account'),
            'user' => auth(),
            'locales' => Translator::available(),
            'labels' => auth()->labels(),
            'calendars' => CalendarAccount::forUser(auth()),
        ]);
    }

    public function profile(Request $request): Response
    {
        $name = (string) $request->input('name', '');
        $locale = (string) $request->input('locale', '');
        $theme = (string) $request->input('theme', 'auto');
        $color = (string) $request->input('color', auth()->color());
        $timezone = (string) $request->input('timezone', auth()->timezone()->getName());
        $minutes = $request->input('event_minutes', auth()->eventMinutes());

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = __('Please enter your name.');
        }
        if (!in_array($locale, Translator::available(), true)) {
            $errors['locale'] = __('Invalid language.');
        }
        if (!in_array($theme, User::THEMES, true)) {
            $errors['theme'] = __('Invalid theme.');
        }
        if (!Palette::has($color)) {
            $errors['color'] = __('Choose a colour.');
        }
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = __('Choose a time zone.');
        }
        $minutes = filter_var($minutes, FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 1440]]);
        if ($minutes === false) {
            $errors['event_minutes'] = __('Enter a number of minutes between 5 and 1440.');
        }
        if ($errors) {
            return $this->fail(__('Please check the form.'), $errors);
        }

        if ($timezone !== auth()->timezone()->getName()) {
            // days are cached in the old time zone: fetch the months again
            Database::connection()->query(
                'DELETE m FROM calendar_months m JOIN calendars c ON c.id = m.calendar_id JOIN calendar_accounts a ON a.id = c.account_id WHERE a.user_id = ?',
                [auth()->id]
            );
        }
        auth()->update(['name' => $name, 'locale' => $locale, 'theme' => $theme, 'color' => $color, 'timezone' => $timezone, 'event_minutes' => $minutes]);
        Session::set('locale', $locale);

        return $this->ok(__('Profile updated.'), ['redirect' => '/account']);
    }

    /** Quick switch from the user menu: auto → light → dark. */
    public function theme(Request $request): Response
    {
        $theme = (string) $request->input('theme', '');
        if (in_array($theme, User::THEMES, true)) {
            auth()->update(['theme' => $theme]);
        }

        return $this->ok('', ['reload' => true]);
    }

    public function avatar(Request $request): Response
    {
        $file = $request->files('avatar')[0] ?? null;
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->fail(__('Please choose an image.'), ['avatar' => __('Please choose an image.')]);
        }

        $avatars = new AvatarService();
        try {
            $name = $avatars->store((int) auth()->id, $file['tmp_name']);
        } catch (\LengthException) {
            return $this->fail(__('The image is too large (max :size KB).', ['size' => env('AVATAR_MAX_KB', 4096)]));
        } catch (\InvalidArgumentException) {
            return $this->fail(__('Use a JPG, PNG, GIF or WebP image.'));
        }

        $avatars->delete(auth()->avatar);
        auth()->update(['avatar' => $name]);

        return $this->ok(__('Profile picture updated.'), ['redirect' => '/account']);
    }

    public function deleteAvatar(Request $request): Response
    {
        (new AvatarService())->delete(auth()->avatar);
        auth()->update(['avatar' => null]);

        return $this->ok(__('Profile picture removed.'), ['redirect' => '/account']);
    }

    public function avatarFile(Request $request, string $file): Response
    {
        $path = (new AvatarService())->path($file) ?? abort(404);

        return Response::file($path, 'image/png')->withHeader('Cache-Control', 'private, max-age=31536000, immutable');
    }

    public function password(Request $request): Response
    {
        $user = auth();
        $new = (string) $request->input('password', '');
        if ($blocked = $this->passwordBlocked('current_password')) {
            return $blocked;
        }

        $errors = [];
        if (!password_verify((string) $request->input('current_password', ''), (string) $user->password)) {
            $errors['current_password'] = __('The current password is wrong.');
            $this->passwordFailed();
        }
        if (mb_strlen($new) < 8) {
            $errors['password'] = __('The password must be at least 8 characters.');
        } elseif ($new !== $request->input('password_confirmation')) {
            $errors['password_confirmation'] = __('The passwords do not match.');
        }
        if ($errors) {
            return $this->fail(__('Please check the form.'), $errors);
        }

        $user->update(['password' => password_hash($new, PASSWORD_DEFAULT)]);
        Throttle::clear('password|' . $user->id);
        Session::regenerate();

        return $this->ok(__('Password changed.'));
    }

    public function destroy(Request $request): Response
    {
        $user = auth();
        if ($blocked = $this->passwordBlocked('confirm_password')) {
            return $blocked;
        }
        if (!password_verify((string) $request->input('confirm_password', ''), (string) $user->password)) {
            $this->passwordFailed();
            return $this->fail(__('Please check the form.'), ['confirm_password' => __('The current password is wrong.')]);
        }
        if ($user->isAdmin() && User::count('is_admin = 1') <= 1) {
            return $this->fail(__('You are the only administrator: promote another user before deleting your account.'));
        }

        $user->deleteWithData();
        Auth::logout();
        Session::start();
        Session::flash('success', __('Your account has been deleted. Goodbye!'));

        return $this->ok('', ['redirect' => '/']);
    }

    /** Settings › Account and data › Export: everything of the user as one JSON file, naming nobody. */
    public function export(Request $request): Response
    {
        $doc = (new DataTransfer(auth()))->export((bool) $request->input('attachments'));
        $name = 'kalban-' . date('Y-m-d') . '.json';

        return new Response((string) json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Settings › Account and data › Import: the boards of an export, always as new ones. */
    public function import(Request $request): Response
    {
        $file = $request->files('file')[0] ?? null;
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            $error = $file === null || $file['error'] === UPLOAD_ERR_NO_FILE ? __('Please choose a file.') : __('The file is too large (max :size).', ['size' => format_bytes(AttachmentService::uploadBytes())]);
            return $this->fail($error, ['file' => $error]);
        }
        $doc = json_decode((string) file_get_contents($file['tmp_name']), true);
        if (!is_array($doc)) {
            return $this->fail(__('Please check the form.'), ['file' => __('This is not a Kalban export (or it comes from a newer version).')]);
        }
        $count = (new DataTransfer(auth()))->import($doc);
        $message = __('Imported :boards boards, :cards cards and :files attachments.', ['boards' => $count['boards'], 'cards' => $count['cards'], 'files' => $count['attachments']]);
        if ($count['calendars']) {
            $message .= ' ' . __('Calendars are not imported: connect them again from Settings.');
        }

        return $this->ok($message, ['redirect' => '/dashboard']);
    }

    /** Settings › API access: the switch. Turning it off deletes the token. */
    public function api(Request $request): Response
    {
        if (!ApiToken::enabled()) {
            abort(404);
        }
        $on = (bool) $request->input('enabled');
        auth()->update(['api_enabled' => (int) $on]);
        if (!$on) {
            ApiToken::revoke(auth());
        }

        return $this->ok('', ['html' => ['#api-panel' => partial('api-settings', ['user' => auth()])]]);
    }

    /** Generates the token (or a new one: the old one stops working). It is shown this one time. */
    public function apiToken(Request $request): Response
    {
        if (!ApiToken::enabled() || !auth()->api_enabled) {
            abort(404);
        }
        $scope = (string) $request->input('scope', 'read');
        if (!in_array($scope, ApiToken::SCOPES, true)) {
            return $this->fail(__('Please check the form.'), ['scope' => __('Choose what the token can do.')]);
        }
        $secret = ApiToken::issue(auth(), $scope);

        return $this->ok(__('New token created: copy it now, it won\'t be shown again.'), [
            'html' => ['#api-panel' => partial('api-settings', ['user' => auth(), 'secret' => $secret])],
        ]);
    }

    /** Wrong current passwords: THROTTLE_PASSWORD_ATTEMPTS per account, then a pause of THROTTLE_PASSWORD_MINUTES. */
    private function passwordBlocked(string $field): ?Response
    {
        $wait = Throttle::blocked('password|' . auth()->id, Throttle::limit('PASSWORD_ATTEMPTS', 5));
        if ($wait === null) {
            return null;
        }
        $message = __('Too many wrong passwords: try again in :min minutes.', ['min' => Throttle::minutes($wait)]);

        return $this->fail($message, [$field => $message], 429);
    }

    private function passwordFailed(): void
    {
        Throttle::hit('password|' . auth()->id, Throttle::limit('PASSWORD_MINUTES', 15) * 60);
    }
}
