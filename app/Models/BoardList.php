<?php

declare(strict_types=1);

namespace App\Models;

/** A list (column) of a board. "List" is a reserved word in PHP, hence the name. */
final class BoardList extends Model
{
    protected static string $table = 'lists';

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function nextCardPosition(): int
    {
        return (int) static::db()->scalar('SELECT COALESCE(MAX(position), -1) + 1 FROM cards WHERE list_id = ?', [$this->id]);
    }

    /** Saves the order of the cards in this list, top to bottom (cards of other boards are ignored). */
    public function reorderCards(array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            static::db()->query(
                'UPDATE cards SET list_id = ?, position = ? WHERE id = ? AND board_id = ?',
                [$this->id, $position, (int) $id, $this->board_id]
            );
        }
    }
}
