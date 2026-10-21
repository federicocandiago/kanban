<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Throttle;
use App\Core\Request;
use App\Core\Response;
use App\Models\Board;
use App\Models\Invite;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mail;

/**
 * Sharing of whole boards (never single cards). The owner invites by email: an existing
 * account gets a notification, an unknown address gets an email with a sign-up link and
 * finds the invitation waiting once registered. Only the owner invites and removes people.
 */
final class ShareController extends Controller
{
    public function invite(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, owner: true);
        $email = mb_strtolower((string) $request->input('email', ''));
        $user = User::findByEmail($email);
        // invitations can send emails to anyone: THROTTLE_INVITES_PER_HOUR per user
        $key = 'invite|' . auth()->id;
        if ($wait = Throttle::blocked($key, Throttle::limit('INVITES_PER_HOUR', 30))) {
            $message = __('You have sent many invitations: try again in :min minutes.', ['min' => Throttle::minutes($wait)]);
            return $this->fail($message, ['email' => $message], 429);
        }

        $error = match (true) {
            !filter_var($email, FILTER_VALIDATE_EMAIL) => __('Please enter a valid email address.'),
            $user && $board->hasMember((int) $user->id) => __(':email is already a member of this board.', ['email' => $email]),
            Invite::count("board_id = ? AND email = ? AND status = 'pending'", [$board->id, $email]) > 0 => __(':email has already been invited.', ['email' => $email]),
            default => null,
        };
        if ($error) {
            return $this->fail($error, ['email' => $error]);
        }

        Throttle::hit($key, 3600);
        $invite = Invite::create([
            'board_id' => $board->id, 'email' => $email, 'user_id' => $user?->id, 'invited_by' => auth()->id,
        ]);
        $message = __('Invitation sent to :email.', ['email' => $email]);

        if ($user) {
            $invite->notifyInvitee();
        } else {
            try {
                Mail::send($email, locale(), static fn (array $d): string => __(':name shared a board with you on :app', $d), 'invite', [
                    'name' => (string) auth()->name,
                    'color' => auth()->color(),
                    'board' => (string) $board->name,
                    'link' => absolute_url('/register?email=' . rawurlencode($email)),
                ]);
            } catch (\Throwable $e) {
                error_log('Kalban: invitation email not sent: ' . $e->getMessage());
                $message = __('The invitation is saved, but the email to :email could not be sent: it will be waiting when they sign up with this address.', ['email' => $email]);
            }
        }

        return $this->ok($message, $this->panels($board));
    }

    public function remove(Request $request, string $uuid, string $id): Response
    {
        $board = $this->board($uuid, owner: true);
        $user = User::find((int) $id);
        if ($user && $board->hasMember((int) $user->id) && (int) $user->id !== (int) $board->owner_id) {
            $board->removeMember((int) $user->id);
            Notification::send((int) $user->id, 'removed', ['board' => $board->name, 'by' => auth()->name]);
        }

        return $this->ok(__('Access removed.'), $this->panels($board));
    }

    public function cancel(Request $request, string $id): Response
    {
        $invite = Invite::find((int) $id) ?? abort(404);
        $board = $this->board((int) $invite->board_id, owner: true);
        if ($invite->isPending()) {
            if ($invite->user_id) { // its notification would otherwise still say "invited you"
                Database::connection()->query(
                    "DELETE FROM notifications WHERE user_id = ? AND type = 'invite' AND data LIKE ?",
                    [$invite->user_id, '{"invite_id":' . (int) $invite->id . ',%']
                );
            }
            $invite->delete();
        }

        return $this->ok(__('Invitation withdrawn.'), $this->panels($board));
    }

    public function accept(Request $request, string $id): Response
    {
        $invite = $this->pending((int) $id);
        $board = $invite->accept(auth());

        return $this->ok(__('Welcome to “:name”!', ['name' => $board->name]), ['redirect' => '/boards/' . $board->uuid]);
    }

    public function decline(Request $request, string $id): Response
    {
        $this->pending((int) $id)->decline(auth());

        return $this->ok(__('Invitation declined.'), ['reload' => true]);
    }

    private function pending(int $id): Invite
    {
        $invite = Invite::find($id);
        if (!$invite || (int) $invite->user_id !== (int) auth()->id) {
            abort(404);
        }
        if (!$invite->isPending()) {
            abort(409, __('This invitation has already been answered or withdrawn.'));
        }

        return $invite;
    }

    /** Redraws the share dialog and the avatars in the board header. */
    private function panels(Board $board): array
    {
        $data = ['board' => $board, 'members' => $board->members()];

        return ['html' => [
            '#share-panel' => partial('share', $data),
            '#board-members' => partial('board-members', $data),
        ]];
    }
}
