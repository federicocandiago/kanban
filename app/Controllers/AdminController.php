<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\ApiToken;
use App\Models\Board;
use App\Models\Card;
use App\Models\Setting;
use App\Models\User;

final class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::paginate((int) $request->input('page', 1), 15, 'name ASC');

        // How many boards each user on this page can open (own and shared): the boards themselves stay private
        $counts = [];
        $ids = array_map(static fn (User $u): int => (int) $u->id, $users['items']);
        if ($ids) {
            $rows = Database::connection()->select(
                'SELECT user_id, COUNT(*) AS boards FROM board_members WHERE user_id IN (' . implode(',', $ids) . ') GROUP BY user_id'
            );
            $counts = array_column($rows, null, 'user_id');
        }

        return view('pages/admin/index', [
            'title' => __('Back-office'),
            'users' => $users,
            'counts' => $counts,
            'tokens' => array_column(Database::connection()->select('SELECT user_id, scope, expires_at FROM api_tokens'), null, 'user_id'),
            'stats' => [
                __('Users') => $users['total'],
                __('Boards') => Board::count(),
                __('Cards') => Card::count(),
                __('Paused accounts') => User::count('suspended_at IS NOT NULL'),
            ],
            'registrationEnabled' => Setting::registrationEnabled(),
        ]);
    }

    public function settings(Request $request): Response
    {
        Setting::set('registration_enabled', $request->input('registration_enabled') ? '1' : '0');

        return $this->ok(__('Settings saved.'), ['reload' => true]);
    }

    public function updateUser(Request $request, string $id): Response
    {
        $user = User::find((int) $id) ?? abort(404);
        $isAdmin = (int) (bool) $request->input('is_admin');
        if ((int) $user->id === (int) auth()->id && !$isAdmin) {
            return $this->fail(__('You cannot remove your own admin role.'));
        }
        $user->update(['is_admin' => $isAdmin]);

        return $this->ok(__('User updated.'));
    }

    public function deleteUser(Request $request, string $id): Response
    {
        $user = User::find((int) $id) ?? abort(404);
        if ((int) $user->id === (int) auth()->id) {
            return $this->fail(__('You cannot delete yourself.'));
        }
        $user->deleteWithData();

        return $this->ok(__('User deleted.'), ['reload' => true]);
    }

    /** Revokes a user's API token (they can generate a new one, unless API access is turned off). */
    public function revokeToken(Request $request, string $id): Response
    {
        $user = User::find((int) $id) ?? abort(404);
        ApiToken::revoke($user);

        return $this->ok(__('API token revoked.'), ['reload' => true]);
    }

    public function toggleUser(Request $request, string $id): Response
    {
        $user = User::find((int) $id) ?? abort(404);
        if ((int) $user->id === (int) auth()->id) {
            return $this->fail(__('You cannot pause yourself.'));
        }
        $user->update(['suspended_at' => $user->isSuspended() ? null : date('Y-m-d H:i:s')]);

        return $this->ok($user->isSuspended() ? __('User paused.') : __('User reactivated.'), ['reload' => true]);
    }
}
