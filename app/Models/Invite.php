<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Invitation to a board, by email. status: pending | accepted | declined.
 * user_id is filled as soon as the address belongs to an account.
 */
final class Invite extends Model
{
    protected static string $table = 'board_invites';

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function board(): ?Board
    {
        return Board::find((int) $this->board_id);
    }

    /** A new account takes over the invitations sent to its address and is notified of each. */
    public static function claim(User $user): void
    {
        foreach (self::where("email = ? AND status = 'pending' AND user_id IS NULL", [$user->email], 'id ASC') as $invite) {
            $invite->update(['user_id' => $user->id]);
            $invite->notifyInvitee();
        }
    }

    public function notifyInvitee(): void
    {
        $board = $this->board();
        $by = User::find((int) $this->invited_by);
        if ($board && $this->user_id) {
            Notification::send((int) $this->user_id, 'invite', [
                'invite_id' => (int) $this->id, 'board' => $board->name, 'by' => $by?->name,
            ]);
        }
    }

    public function accept(User $user): Board
    {
        $board = $this->board();
        static::db()->query(
            "INSERT IGNORE INTO board_members (board_id, user_id, role, created_at) VALUES (?, ?, 'member', NOW())",
            [$board->id, $user->id]
        );
        $this->respond('accepted', $user);

        return $board;
    }

    public function decline(User $user): void
    {
        $this->respond('declined', $user);
    }

    private function respond(string $status, User $user): void
    {
        $this->update(['status' => $status, 'responded_at' => date('Y-m-d H:i:s')]);
        $board = $this->board();
        Notification::send((int) $this->invited_by, 'invite_' . $status, [
            'board' => $board?->name, 'uuid' => $board?->uuid, 'by' => $user->name,
        ]);
    }
}
