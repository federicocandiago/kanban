<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;

/**
 * Cards, the same for the site and the API: every change goes to the card's log
 * (who, when, field, old → new). Access to the board is checked by the caller.
 */
final class CardService
{
    public function __construct(private readonly User $user)
    {
    }

    /** New card at the bottom of $list (only the name, as the quick add). */
    public function create(BoardList $list, string $title): Card
    {
        Board::find((int) $list->board_id)->assertRoom(cards: 1);
        $card = Card::create([
            'board_id' => $list->board_id, 'list_id' => $list->id, 'title' => $this->title($title),
            'position' => $list->nextCardPosition(), 'created_by' => $this->user->id,
        ]);
        $card->log($this->user, 'created', null, $list->name);

        return Card::detailed((int) $card->id);
    }

    /**
     * New card from the "New card" window: name, list, description and dates in one go, at the
     * bottom of the list; the log only says it was created.
     * @param array{title?: string, list_id?: int, description?: ?string, start_date?: ?string, end_date?: ?string} $input
     */
    public function add(Board $board, array $input): Card
    {
        $errors = [];
        $title = $this->title((string) ($input['title'] ?? ''), $errors);
        $list = $this->list($board, (int) ($input['list_id'] ?? 0), $errors);
        [$start, $end] = [$this->date($input['start_date'] ?? null), $this->date($input['end_date'] ?? null)];
        if ($start === false) {
            $errors['start_date'] = __('Invalid date.');
        }
        if ($end === false) {
            $errors['end_date'] = __('Invalid date.');
        } elseif ($start && $end && $end < $start) {
            $errors['end_date'] = __('The end date comes before the start date.');
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        $board->assertRoom(cards: 1);

        $card = Card::create([
            'board_id' => $board->id, 'list_id' => $list->id, 'title' => $title, 'position' => $list->nextCardPosition(), 'created_by' => $this->user->id,
            'description' => trim(str_replace("\r\n", "\n", (string) ($input['description'] ?? ''))) ?: null,
            'start_date' => $start ?: null, 'end_date' => $end ?: null,
        ]);
        $card->log($this->user, 'created', null, $list->name);

        return Card::detailed((int) $card->id);
    }

    /**
     * Changes name, description, dates and/or list; only the keys present are touched.
     * A new list puts the card at its bottom. Returns the id of the new list, or null if it didn't move.
     * @param array{title?: string, description?: ?string, start_date?: ?string, end_date?: ?string, list_id?: int} $input
     */
    public function update(Card $card, Board $board, array $input): ?int
    {
        $errors = [];
        $title = array_key_exists('title', $input) ? $this->title((string) $input['title'], $errors) : $card->title;
        $start = array_key_exists('start_date', $input) ? $this->date($input['start_date']) : $card->start_date;
        $end = array_key_exists('end_date', $input) ? $this->date($input['end_date']) : $card->end_date;
        if ($start === false) {
            $errors['start_date'] = __('Invalid date.');
        }
        if ($end === false) {
            $errors['end_date'] = __('Invalid date.');
        } elseif ($start && $end && $end < $start) {
            $errors['end_date'] = __('The end date comes before the start date.');
        }
        $list = array_key_exists('list_id', $input) ? $this->list($board, (int) $input['list_id'], $errors) : null;
        if ($errors) {
            throw new ValidationException($errors);
        }

        $changes = ['title' => $title, 'start_date' => $start ?: null, 'end_date' => $end ?: null];
        if (array_key_exists('description', $input)) {
            $changes['description'] = trim(str_replace("\r\n", "\n", (string) $input['description'])) ?: null;
        }
        foreach ($changes as $field => $value) {
            if ((string) $card->{$field} !== (string) $value) {
                $card->log($this->user, $field, $card->{$field}, $value);
            } else {
                unset($changes[$field]);
            }
        }

        $moved = $list && (int) $list->id !== (int) $card->list_id;
        if ($moved) {
            $card->log($this->user, 'list', $card->list_name, $list->name);
            $changes += ['list_id' => $list->id, 'position' => $list->nextCardPosition()];
        }
        if ($changes) {
            $card->update($changes);
        }

        return $moved ? (int) $list->id : null;
    }

    /** Drag & drop: the card is now in $list, whose cards are ordered as $ids (top to bottom). */
    public function reorder(Card $card, BoardList $list, array $ids): void
    {
        if ((int) $list->id !== (int) $card->list_id) {
            $card->log($this->user, 'list', $card->list_name, $list->name);
        }
        $list->reorderCards($ids);
    }

    /** Moves the card to $position (0 = top) of $list, which may be its own. */
    public function moveTo(Card $card, Board $board, int $listId, int $position): void
    {
        $errors = [];
        $list = $this->list($board, $listId, $errors);
        if ($errors) {
            throw new ValidationException($errors);
        }
        $ids = array_map('intval', array_column(Database::connection()->select(
            'SELECT id FROM cards WHERE list_id = ? AND archived_at IS NULL AND id <> ? ORDER BY position, id',
            [$list->id, $card->id]
        ), 'id'));
        array_splice($ids, max(0, min($position, count($ids))), 0, [(int) $card->id]);
        $this->reorder($card, $list, $ids);
    }

    /** The user's own labels on this card: $labelIds is the complete new selection (labels of others stay). */
    public function setLabels(Card $card, array $labelIds): void
    {
        $mine = [];
        foreach ($this->user->labels() as $label) {
            $mine[(int) $label->id] = $label;
        }
        $wanted = array_intersect(array_map('intval', $labelIds), array_keys($mine));
        $current = array_intersect($card->labelIds(), array_keys($mine));

        $db = Database::connection();
        foreach (array_diff($wanted, $current) as $labelId) {
            $db->insert('card_labels', ['card_id' => $card->id, 'label_id' => $labelId]);
            $card->log($this->user, 'label_added', null, $mine[$labelId]->logValue());
        }
        foreach (array_diff($current, $wanted) as $labelId) {
            $db->query('DELETE FROM card_labels WHERE card_id = ? AND label_id = ?', [$card->id, $labelId]);
            $card->log($this->user, 'label_removed', $mine[$labelId]->logValue(), null);
        }
    }

    public function addLabel(Card $card, Label $label): void
    {
        $this->setLabels($card, [...$card->labelIds(), (int) $label->id]);
    }

    public function removeLabel(Card $card, Label $label): void
    {
        $this->setLabels($card, array_diff($card->labelIds(), [(int) $label->id]));
    }

    /** @param array{name: string, tmp_name: string, size: int, error: int}|null $file an uploaded file */
    public function attach(Card $card, Board $board, ?array $file, bool $sizeExceeded = false): Attachment
    {
        $tooBig = __('The file is too large (max :size).', ['size' => format_bytes(AttachmentService::maxBytes())]);
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            $big = $sizeExceeded || in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            throw ValidationException::field('file', $big ? $tooBig : __('Please choose a file.'));
        }
        try {
            $stored = (new AttachmentService())->store((int) $board->id, $file['tmp_name']);
        } catch (\LengthException) {
            throw ValidationException::field('file', $tooBig);
        }
        $name = mb_substr(basename(str_replace('\\', '/', $file['name'])), 0, 255) ?: 'file';
        $attachment = Attachment::create(['card_id' => $card->id, 'user_id' => $this->user->id, 'name' => $name, ...$stored]);
        $card->log($this->user, 'attachment_added', null, $name);

        return $attachment;
    }

    public function detach(Card $card, Board $board, Attachment $attachment): void
    {
        (new AttachmentService())->delete((int) $board->id, (string) $attachment->file);
        $attachment->delete();
        $card->log($this->user, 'attachment_removed', $attachment->name, null);
    }

    public function archive(Card $card): void
    {
        $card->update(['archived_at' => date('Y-m-d H:i:s')]);
        $card->log($this->user, 'archived');
    }

    /** Back at the bottom of its list, if the list itself is not archived. */
    public function restore(Card $card): BoardList
    {
        $list = BoardList::find((int) $card->list_id);
        if ($list->isArchived()) {
            throw ValidationException::field('list_id', __('Its list “:name” is archived: restore the list first.', ['name' => $list->name]));
        }
        Board::find((int) $list->board_id)->assertRoom(cards: 1);
        $card->update(['archived_at' => null, 'position' => $list->nextCardPosition()]);
        $card->log($this->user, 'restored');

        return $list;
    }

    /** Hard delete with its files (board owner). */
    public function destroy(Card $card): void
    {
        (new AttachmentService())->deleteForCards('id = ?', [$card->id]);
        $card->delete();
    }

    private function title(string $title, ?array &$errors = null): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
        if ($title === '') {
            if ($errors === null) {
                throw ValidationException::field('title', __('Give the card a name.'));
            }
            $errors['title'] = __('Give the card a name.');
        }

        return mb_substr($title, 0, 255);
    }

    /** An active list of $board, or an error under list_id. */
    private function list(Board $board, int $id, array &$errors): ?BoardList
    {
        $list = BoardList::find($id);
        if (!$list || (int) $list->board_id !== (int) $board->id || $list->isArchived()) {
            $errors['list_id'] = __('Choose a list of this board.');
            return null;
        }

        return $list;
    }

    /** '' / null → null (no date), a valid Y-m-d → itself, anything else → false. */
    private function date(mixed $value): string|false|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : false;
    }
}
