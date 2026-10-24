<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Models\Board;
use App\Models\BoardList;

/** Lists of a board, the same for the site and the API. Access is checked by the caller. */
final class ListService
{
    /** New list at the end, or at $position (0 = first). */
    public function create(Board $board, string $name, ?int $position = null): BoardList
    {
        $board->assertRoom(lists: 1);
        $list = BoardList::create(['board_id' => $board->id, 'name' => $this->name($name), 'position' => $board->nextListPosition()]);
        if ($position !== null) {
            $this->moveTo($board, $list, $position);
        }

        return BoardList::find((int) $list->id);
    }

    public function rename(BoardList $list, string $name): void
    {
        $list->update(['name' => $this->name($name)]);
    }

    /** Drag & drop: the active lists in this order, left to right. */
    public function reorder(Board $board, array $ids): void
    {
        $board->reorderLists($ids);
    }

    /** Moves one list to $position among the active lists of its board (0 = first). */
    public function moveTo(Board $board, BoardList $list, int $position): void
    {
        $ids = array_values(array_filter(
            array_map(static fn (BoardList $l): int => (int) $l->id, $board->lists()),
            static fn (int $id): bool => $id !== (int) $list->id
        ));
        array_splice($ids, max(0, min($position, count($ids))), 0, [(int) $list->id]);
        $board->reorderLists($ids);
    }

    public function archive(BoardList $list): void
    {
        $list->update(['archived_at' => date('Y-m-d H:i:s')]);
    }

    public function restore(BoardList $list): void
    {
        Board::find((int) $list->board_id)->assertRoom(
            (int) Database::connection()->scalar('SELECT COUNT(*) FROM cards WHERE list_id = ? AND archived_at IS NULL', [$list->id]),
            1
        );
        $list->update(['archived_at' => null, 'position' => (int) Database::connection()->scalar(
            'SELECT COALESCE(MAX(position), -1) + 1 FROM lists WHERE board_id = ? AND archived_at IS NULL',
            [$list->board_id]
        )]);
    }

    /** Hard delete with all its cards and their files (board owner). */
    public function destroy(BoardList $list): void
    {
        (new AttachmentService())->deleteForCards('list_id = ?', [$list->id]);
        $list->delete();
    }

    private function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::field('name', __('Give the list a name (up to 100 characters).'));
        }

        return $name;
    }
}
