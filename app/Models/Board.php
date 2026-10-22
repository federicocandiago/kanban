<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AttachmentService;
use App\Services\Palette;

/**
 * A board. Loaded through User::boards() or Board::forMember() it also carries the
 * viewer's own membership: role (owner | member) and is_favorite.
 */
final class Board extends Model
{
    protected static string $table = 'boards';

    public static function findByUuid(string $uuid): ?self
    {
        return self::first('uuid = ?', [$uuid]);
    }

    /** The board (by uuid, or by id) with the user's membership, or null if they are not a member. */
    public static function forMember(string|int $key, User $user): ?self
    {
        $row = static::db()->selectOne(
            'SELECT b.*, m.role, m.is_favorite FROM boards b
             JOIN board_members m ON m.board_id = b.id AND m.user_id = ? WHERE b.' . (is_int($key) ? 'id' : 'uuid') . ' = ?',
            [$user->id, $key]
        );

        return $row ? new self($row) : null;
    }

    /** New board, with its creator as owner. */
    public static function open(User $owner, string $name, array $background): self
    {
        $board = self::create(['uuid' => uuid(), 'owner_id' => $owner->id, 'name' => $name, ...$background]);
        static::db()->insert('board_members', [
            'board_id' => $board->id, 'user_id' => $owner->id, 'role' => 'owner',
            'last_opened_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'),
        ]);

        return self::forMember((string) $board->uuid, $owner);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * How the board is shared, for the dashboard tile: "Shared with me" (somebody else's), "Shared with
     * Giulia" (mine, one other member), "Shared" (mine, more members), null (only mine). Needs User::boards().
     */
    public function shareLabel(): ?string
    {
        if (!$this->isOwner()) {
            return __('Shared with me');
        }
        $others = (int) $this->member_count - 1;

        return match (true) {
            $others <= 0 => null,
            $others === 1 => __('Shared with :name', ['name' => strtok((string) $this->other_name, ' ') ?: (string) $this->other_name]), // first name: it fits the tile
            default => __('Shared'),
        };
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function url(string $path = ''): string
    {
        return url('/boards/' . $this->uuid . $path);
    }

    /** Inline style for the board page or a tile: a palette colour or an Unsplash photo. */
    public function backgroundStyle(bool $thumb = false): string
    {
        if ($this->bg_type === 'photo') {
            if ($thumb || !$this->bg_thumb) {
                return 'background-color: #2c3e50; background-image: url(\'' . e($this->bg_thumb ?: $this->bg_value) . '\')';
            }

            // the thumbnail (already seen on the dashboard) shows at once, the full photo covers it when loaded
            return 'background-color: #2c3e50; background-image: url(\'' . e($this->bg_value) . '\'), url(\'' . e($this->bg_thumb) . '\')';
        }

        return 'background-color: ' . Palette::primary((string) $this->bg_value);
    }

    /** The colour the glass of the board page is tinted with: the photo's average colour, or the board's colour. */
    public function tint(): string
    {
        if ($this->bg_type === 'photo') {
            return $this->bg_tint ?: '#2c3e50';
        }

        return Palette::primary((string) $this->bg_value);
    }

    /** @return BoardList[] */
    public function lists(bool $archived = false): array
    {
        return BoardList::where('board_id = ? AND archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL'), [$this->id], $archived ? 'archived_at DESC' : 'position ASC, id ASC');
    }

    /** @return array<int, Card[]> active cards of the active lists, by list id */
    public function cardsByList(): array
    {
        $byList = [];
        foreach (Card::withDetails('c.board_id = ? AND c.archived_at IS NULL', [$this->id]) as $card) {
            $byList[(int) $card->list_id][] = $card;
        }

        return $byList;
    }

    /** @return Card[] archived cards, newest first */
    public function archivedCards(): array
    {
        return Card::withDetails('c.board_id = ? AND c.archived_at IS NOT NULL', [$this->id], 'c.archived_at DESC');
    }

    /** @return User[] with `role`, owner first */
    public function members(): array
    {
        $rows = static::db()->select(
            "SELECT u.*, m.role FROM users u JOIN board_members m ON m.user_id = u.id
             WHERE m.board_id = ? ORDER BY m.role = 'owner' DESC, u.name",
            [$this->id]
        );

        return array_map(static fn (array $row) => new User($row), $rows);
    }

    public function hasMember(int $userId): bool
    {
        return (bool) static::db()->scalar('SELECT 1 FROM board_members WHERE board_id = ? AND user_id = ?', [$this->id, $userId]);
    }

    /** @return Invite[] */
    public function pendingInvites(): array
    {
        return Invite::where("board_id = ? AND status = 'pending'", [$this->id], 'id ASC');
    }

    public function removeMember(int $userId): void
    {
        static::db()->query("DELETE FROM board_members WHERE board_id = ? AND user_id = ? AND role <> 'owner'", [$this->id, $userId]);
    }

    /** Remembers when the user last opened the board (dashboard order). */
    public function touch(User $user): void
    {
        static::db()->query('UPDATE board_members SET last_opened_at = NOW() WHERE board_id = ? AND user_id = ?', [$this->id, $user->id]);
    }

    public function setFavorite(User $user, bool $on): void
    {
        static::db()->query('UPDATE board_members SET is_favorite = ? WHERE board_id = ? AND user_id = ?', [(int) $on, $this->id, $user->id]);
    }

    /** Hard delete: lists, cards, labels on cards, log and attachments. Owner only. */
    public function destroy(): void
    {
        (new AttachmentService())->destroyBoard((int) $this->id);
        $this->delete();
    }

    public function nextListPosition(): int
    {
        return (int) static::db()->scalar('SELECT COALESCE(MAX(position), -1) + 1 FROM lists WHERE board_id = ?', [$this->id]);
    }

    /** Saves the order of the lists: $ids from left to right (ids of other boards are ignored). */
    public function reorderLists(array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            static::db()->query('UPDATE lists SET position = ? WHERE id = ? AND board_id = ?', [$position, (int) $id, $this->id]);
        }
    }
}
