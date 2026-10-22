<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AttachmentService;
use App\Services\AvatarService;
use App\Services\Palette;

final class User extends Model
{
    protected static string $table = 'users';

    public const THEMES = ['auto', 'light', 'dark'];

    public static function findByEmail(string $email): ?self
    {
        return self::first('email = ?', [mb_strtolower(trim($email))]);
    }

    /** New account: the six starting labels, and the boards it was invited to before signing up. */
    public static function register(array $data): self
    {
        $user = self::create($data);
        Label::seed((int) $user->id);
        Invite::claim($user);

        return $user;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /** New confirmation link: only the hash of the token is stored. Returns the token for the email. */
    public function newVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update(['verification_token' => hash('sha256', $token), 'verification_sent_at' => date('Y-m-d H:i:s')]);

        return $token;
    }

    public static function findByVerificationToken(string $token): ?self
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) ? self::first('verification_token = ?', [hash('sha256', $token)]) : null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function theme(): string
    {
        return in_array($this->theme, self::THEMES, true) ? (string) $this->theme : 'auto';
    }

    /** Colour of the interface: top bar, dashboard, default background of new boards. */
    public function color(): string
    {
        return Palette::has($this->color) ? (string) $this->color : Palette::DEFAULT;
    }

    /** The user's time zone (Settings), or APP_TIMEZONE. */
    public function timezone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone($this->timezone ?: (string) env('APP_TIMEZONE', 'UTC'));
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }

    /** Calendar: when the start of an event changes, the end is set this many minutes later. */
    public function eventMinutes(): int
    {
        return (int) ($this->event_minutes ?: max(5, (int) env('EVENT_DEFAULT_MINUTES', 30)));
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [''];

        return mb_strtoupper(mb_substr($words[0], 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));
    }

    /**
     * Boards this user can open, with their own role and favourite flag, and touched_at: the
     * last time they opened it or someone changed it or its cards (the active ones come in that order);
     * owner_name, and other_name: the one other member, when there is exactly one.
     * @return Board[]
     */
    public function boards(bool $archived = false): array
    {
        $rows = static::db()->select(
            'SELECT b.*, m.role, m.is_favorite,
                    (SELECT COUNT(*) FROM board_members x WHERE x.board_id = b.id) AS member_count,
                    (SELECT u.name FROM users u WHERE u.id = b.owner_id) AS owner_name,
                    (SELECT u.name FROM board_members y JOIN users u ON u.id = y.user_id WHERE y.board_id = b.id AND y.user_id <> m.user_id LIMIT 1) AS other_name,
                    GREATEST(COALESCE(m.last_opened_at, b.created_at), b.updated_at,
                             COALESCE((SELECT MAX(c.updated_at) FROM cards c WHERE c.board_id = b.id), b.created_at)) AS touched_at
             FROM boards b JOIN board_members m ON m.board_id = b.id AND m.user_id = ?
             WHERE b.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL') . '
             ORDER BY ' . ($archived ? 'b.archived_at DESC' : 'touched_at DESC, b.id DESC'),
            [$this->id]
        );

        return array_map(static fn (array $row) => new Board($row), $rows);
    }

    /** @return Label[] */
    public function labels(): array
    {
        return Label::where('user_id = ?', [$this->id], 'id ASC');
    }

    /**
     * People this user already works with: members of the boards they share.
     * Offered in the share dialog so their address doesn't have to be typed again.
     * @return User[]
     */
    public function contacts(): array
    {
        $rows = static::db()->select(
            'SELECT DISTINCT u.* FROM users u
             JOIN board_members other ON other.user_id = u.id
             JOIN board_members mine ON mine.board_id = other.board_id AND mine.user_id = ?
             WHERE u.id <> ? ORDER BY u.name',
            [$this->id, $this->id]
        );

        return array_map(static fn (array $row) => new self($row), $rows);
    }

    /** Deletes the account: owned boards (with their attachments) and profile picture go too. */
    public function deleteWithData(): void
    {
        $attachments = new AttachmentService();
        foreach (Board::where('owner_id = ?', [$this->id]) as $board) {
            $attachments->destroyBoard((int) $board->id);
        }
        (new AvatarService())->delete($this->avatar);
        $this->delete(); // boards, memberships, labels and notifications go away via ON DELETE CASCADE
    }
}
