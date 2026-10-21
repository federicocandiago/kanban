<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Items of the bell dropdown next to the user menu.
 * type: invite | invite_accepted | invite_declined | removed | left — data: JSON with names and ids.
 */
final class Notification extends Model
{
    protected static string $table = 'notifications';

    public static function send(int $userId, string $type, array $data): void
    {
        self::create(['user_id' => $userId, 'type' => $type, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
    }

    /** @return Notification[] */
    public static function latest(User $user, int $limit = 15): array
    {
        $rows = static::db()->select("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT {$limit}", [$user->id]);

        return array_map(static fn (array $row) => new self($row), $rows);
    }

    public static function unread(User $user): int
    {
        return self::count('user_id = ? AND read_at IS NULL', [$user->id]);
    }

    public static function markAllRead(User $user): void
    {
        static::db()->query('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$user->id]);
    }

    public function data(string $key): mixed
    {
        return (json_decode((string) $this->data, true) ?: [])[$key] ?? null;
    }

    /** The invitation still waiting for an answer (invite notifications only). */
    public function pendingInvite(): ?Invite
    {
        $invite = $this->type === 'invite' ? Invite::find((int) $this->data('invite_id')) : null;

        return $invite?->isPending() ? $invite : null;
    }

    public function text(): string
    {
        $vars = ['name' => (string) $this->data('by'), 'board' => (string) $this->data('board')];

        return match ($this->type) {
            'invite' => __(':name invited you to the board “:board”.', $vars),
            'invite_accepted' => __(':name joined the board “:board”.', $vars),
            'invite_declined' => __(':name declined the invitation to “:board”.', $vars),
            'removed' => __(':name removed you from the board “:board”.', $vars),
            'left' => __(':name left the board “:board”.', $vars),
            'token_expiring' => __('Your API token expires on :date: generate a new one from Settings.', ['date' => format_day((string) $this->data('date'))]),
            default => '',
        };
    }

    /** Where clicking the notification goes, if anywhere. */
    public function link(): ?string
    {
        if ($this->type === 'token_expiring') {
            return url('/account#api');
        }

        return in_array($this->type, ['invite_accepted', 'invite_declined', 'left'], true) && $this->data('uuid')
            ? url('/boards/' . $this->data('uuid'))
            : null;
    }
}
