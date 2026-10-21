<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Models\Board;
use App\Models\Notification;
use App\Models\User;

/**
 * What can be done to boards, the same for the site and the API. Access (member / owner)
 * is checked by the caller; invalid input throws ValidationException.
 */
final class BoardService
{
    public function __construct(private readonly User $user)
    {
    }

    /** $background: "color:{palette key}" or "photo:{unsplash id}" (null: the user's colour). */
    public function create(string $name, ?string $background = null): Board
    {
        $name = $this->name($name);
        $columns = $this->background($background ?? 'color:' . $this->user->color());

        return Board::open($this->user, $name, $columns);
    }

    /** @param array{name?: string, background?: string} $changes */
    public function update(Board $board, array $changes): Board
    {
        $data = [];
        if (array_key_exists('name', $changes)) {
            $data['name'] = $this->name((string) $changes['name']);
        }
        if (array_key_exists('background', $changes)) {
            $data += $this->background((string) $changes['background']);
        }
        if ($data) {
            $board->update($data);
        }

        return $board;
    }

    public function favorite(Board $board, bool $on): void
    {
        $board->setFavorite($this->user, $on);
    }

    /** Soft delete (owner). */
    public function archive(Board $board): void
    {
        $board->update(['archived_at' => date('Y-m-d H:i:s')]);
    }

    public function restore(Board $board): void
    {
        $board->update(['archived_at' => null]);
    }

    /** Hard delete (owner), only from the archive. */
    public function destroy(Board $board): void
    {
        if (!$board->isArchived()) {
            throw ValidationException::field('archived', __('Archive the board first.'));
        }
        $board->destroy();
    }

    public function leave(Board $board): void
    {
        if ($board->isOwner()) {
            throw ValidationException::field('board', __('You own this board: archive it instead.'));
        }
        $board->removeMember((int) $this->user->id);
        Notification::send((int) $board->owner_id, 'left', ['board' => $board->name, 'uuid' => $board->uuid, 'by' => $this->user->name]);
    }

    private function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::field('name', __('Give the board a name (up to 100 characters).'));
        }

        return $name;
    }

    private function background(string $value): array
    {
        try {
            $columns = Unsplash::background($value);
        } catch (\RuntimeException $e) {
            error_log('Kalban: ' . $e->getMessage());
            $columns = null;
        }
        if ($columns === null) {
            throw ValidationException::field('background', __('Choose a background.'));
        }

        return $columns;
    }
}
