<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\ChecklistItem;
use App\Services\AttachmentService;
use App\Services\CardService;

/**
 * Cards on the site. The rules (validation, log) are in CardService; the answers carry the
 * markup to redraw: the tile on the board and the open card's panels.
 */
final class CardController extends Controller
{
    /** Quick create from the bottom of a list: only the name. */
    public function store(Request $request, string $id): Response
    {
        [$list] = $this->list((int) $id, visitors: 'edit');
        $card = $this->cards()->create($list, (string) $request->input('title', ''));

        return $this->ok('', ['append' => ["#list-{$list->id} [data-cards]" => $this->tile((int) $card->id)]]);
    }

    /** "New card" window (the + in the top bar): nothing is saved until Create. */
    public function new(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, visitors: 'edit');
        $lists = $board->lists();
        if (!$lists) {
            return $this->fail(__('Add a list first: cards live in lists.'));
        }
        $list = (int) $request->input('list', 0);

        return Response::html(partial('card-new', ['board' => $board, 'lists' => $lists, 'listId' => $list ?: (int) $lists[0]->id]));
    }

    /** Creates the card of the "New card" window and adds it at the bottom of its list. */
    public function add(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, visitors: 'edit');
        $card = $this->cards()->add($board, array_intersect_key($request->all(), array_flip(['title', 'list_id', 'description', 'start_date', 'end_date'])));

        return $this->ok('', ['close' => true, 'append' => ["#list-{$card->list_id} [data-cards]" => $this->tile((int) $card->id)]]);
    }

    /** The card "back", loaded into the card modal. */
    public function show(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, visitors: 'read');

        return Response::html(partial('card', $this->details($card, $board)));
    }

    /** Name, description, dates and list: the form saves itself on every change. */
    public function update(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, visitors: 'edit');
        $input = array_intersect_key($request->all(), array_flip(['title', 'description', 'start_date', 'end_date', 'list_id']));
        $movedTo = $this->cards()->update($card, $board, $input);

        return $this->ok('', $this->redraw((int) $card->id, $board, $movedTo));
    }

    /** Drag & drop: the card lands in list_id, whose cards are now ordered as ids. */
    public function move(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, visitors: 'edit');
        $list = BoardList::find((int) $request->input('list_id'));
        if (!$list || (int) $list->board_id !== (int) $board->id) {
            return $this->fail(__('Choose a list of this board.'));
        }
        $this->cards()->reorder($card, $list, (array) $request->input('ids', []));

        return $this->ok();
    }

    /** The user's own labels on this card: label_ids[] is the complete new selection. */
    public function labels(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, visitors: 'edit');
        $this->cards()->setLabels($card, (array) $request->input('label_ids', []));

        return $this->ok('', $this->redraw((int) $card->id, $board));
    }

    public function attach(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, visitors: 'edit');
        $file = $request->files('file')[0] ?? null;
        $this->cards()->attach($card, $board, $file, $file === null && $request->contentLength() > 0);

        return $this->ok('', $this->redraw((int) $card->id, $board));
    }

    public function download(Request $request, string $id): Response
    {
        $attachment = Attachment::find((int) $id) ?? abort(404);
        [, $board] = $this->card((int) $attachment->card_id, visitors: 'read');

        return self::sendAttachment($attachment, $board, $attachment->viewer() !== null && $request->input('inline'));
    }

    public function detach(Request $request, string $id): Response
    {
        $attachment = Attachment::find((int) $id) ?? abort(404);
        [$card, $board] = $this->card((int) $attachment->card_id);
        $this->cards()->detach($card, $board, $attachment);

        return $this->ok('', $this->redraw((int) $card->id, $board));
    }

    public function archive(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, visitors: 'edit');
        $this->cards()->archive($card);

        return $this->ok(__('Card archived.'), ['remove' => ["#card-{$card->id}"], 'close' => true, ...$this->archivedPanel($board)]);
    }

    public function restore(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, visitors: 'edit');
        $list = $this->cards()->restore($card);

        return $this->ok(__('Card restored.'), [
            'append' => ["#list-{$list->id} [data-cards]" => $this->tile((int) $card->id)],
            ...$this->archivedPanel($board),
        ]);
    }

    /** Hard delete: board owner only. */
    public function destroy(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, owner: true);
        $this->cards()->destroy($card);

        return $this->ok(__('Card deleted.'), ['remove' => ["#card-{$card->id}"], 'close' => true, ...$this->archivedPanel($board)]);
    }

    /** The stored file, as a download (or shown, for images asked inline). Also used by the API. */
    public static function sendAttachment(Attachment $attachment, Board $board, bool $inline = false): Response
    {
        $path = (new AttachmentService())->path((int) $board->id, (string) $attachment->file) ?? abort(404);
        $name = str_replace(['"', "\r", "\n"], '', (string) $attachment->name);

        return Response::file($path, $inline ? (string) $attachment->mime : 'application/octet-stream')
            ->withHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name))
            ->withHeader('Cache-Control', 'private, max-age=86400')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    /** A note in the card's log (any member). */
    public function addNote(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, visitors: 'note');
        $text = $this->noteText($request) ?? abort(422, __('Write the note first.'));
        $card->log($this->actor(), 'note', null, $text);
        $card->update([]); // the board counts as changed (dashboard order)

        return $this->ok('', $this->redraw((int) $card->id, $board));
    }

    /** Its author changes the text. */
    public function updateNote(Request $request, string $id): Response
    {
        [$note, $card, $board] = $this->note((int) $id, 'note');
        if ($note->user_id === null || (int) $note->user_id !== (int) auth()?->id) {
            abort(403, __('Only its author can change a note.'));
        }
        $text = $this->noteText($request) ?? abort(422, __('Write the note first.'));
        $note->update(['new_value' => $text]);

        return $this->ok('', $this->redraw((int) $card->id, $board));
    }

    /** Its author, or the board owner, removes it. */
    public function deleteNote(Request $request, string $id): Response
    {
        [$note, $card, $board] = $this->note((int) $id);
        if ((int) $note->user_id !== (int) auth()?->id && !$board->isOwner()) {
            abort(403, __('Only its author or the board owner can delete a note.'));
        }
        $note->delete();

        return $this->ok('', $this->redraw((int) $card->id, $board));
    }

    /** Checklist: a new item at the bottom. */
    public function addCheck(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id, visitors: 'edit');
        $text = $this->checkText($request) ?? abort(422, __('Write the item first.'));
        $position = (int) Database::connection()->scalar('SELECT COALESCE(MAX(position) + 1, 0) FROM checklist_items WHERE card_id = ?', [$card->id]);
        ChecklistItem::create(['card_id' => $card->id, 'text' => $text, 'done' => 0, 'position' => $position]);
        $card->log($this->actor(), 'check_added', null, $text);
        $card->update([]);

        return $this->ok('', $this->redraw((int) $card->id, $board));
    }

    /** Checklist: tick / untick (done) or rename (text). */
    public function updateCheck(Request $request, string $id): Response
    {
        [$item, $card, $board] = $this->check((int) $id, 'edit');
        if ($request->input('text') !== null) {
            $text = $this->checkText($request) ?? abort(422, __('Write the item first.'));
            if ($text !== $item->text) {
                $card->log($this->actor(), 'check_renamed', (string) $item->text, $text);
                $item->update(['text' => $text]);
            }
        } else {
            $done = (bool) $request->input('done');
            if ($done !== (bool) $item->done) {
                $item->update(['done' => (int) $done]);
                $card->log($this->actor(), $done ? 'check_done' : 'check_undone', null, (string) $item->text);
            }
        }
        $card->update([]);

        return $this->ok('', $this->redraw((int) $card->id, $board));
    }

    public function deleteCheck(Request $request, string $id): Response
    {
        [$item, $card, $board] = $this->check((int) $id);
        $item->delete();
        $card->log($this->actor(), 'check_removed', (string) $item->text, null);
        $card->update([]);

        return $this->ok('', $this->redraw((int) $card->id, $board));
    }

    /** Checklist: the items in their new order (drag & drop). */
    public function orderChecks(Request $request, string $id): Response
    {
        [$card] = $this->card((int) $id, visitors: 'edit');
        foreach (array_values((array) $request->input('ids', [])) as $position => $itemId) {
            Database::connection()->query('UPDATE checklist_items SET position = ? WHERE id = ? AND card_id = ?', [$position, (int) $itemId, $card->id]);
        }

        return $this->ok();
    }

    /** @return array{0: ChecklistItem, 1: Card, 2: Board} */
    private function check(int $id, ?string $visitors = null): array
    {
        $item = ChecklistItem::find($id) ?? abort(404);
        [$card, $board] = $this->card((int) $item->card_id, visitors: $visitors);

        return [$item, $card, $board];
    }

    private function checkText(Request $request): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $request->input('text', '')) ?? '');

        return $text === '' ? null : mb_substr($text, 0, 500);
    }

    /** @return array{0: Activity, 1: Card, 2: Board} */
    private function note(int $id, ?string $visitors = null): array
    {
        $note = Activity::find($id);
        if (!$note || $note->field !== 'note') {
            abort(404);
        }
        [$card, $board] = $this->card((int) $note->card_id, visitors: $visitors);

        return [$note, $card, $board];
    }

    private function noteText(Request $request): ?string
    {
        $text = trim(str_replace("\r\n", "\n", (string) $request->input('text', '')));

        return $text === '' ? null : mb_substr($text, 0, 5000);
    }

    private function cards(): CardService
    {
        return new CardService($this->actor());
    }

    /** Everything the card back shows. */
    private function details(Card $card, Board $board): array
    {
        return [
            'card' => $card,
            'board' => $board,
            'lists' => $board->lists(),
            'myLabels' => auth()?->labels() ?? [],
        ];
    }

    private function tile(int $id): string
    {
        return partial('card-tile', ['card' => Card::detailed($id)]);
    }

    /**
     * Answer that redraws the tile and the read-only parts of the open card (labels, attachments,
     * log): never its form, so a field being edited keeps focus and text. When the card changed
     * list ($movedTo) the tile leaves the old list and joins the bottom of the new one.
     */
    private function redraw(int $id, Board $board, ?int $movedTo = null): array
    {
        $data = $this->details(Card::detailed($id), $board);
        $panels = [];
        foreach (['labels', 'checklist', 'attachments', 'activity'] as $part) {
            $panels["#card-{$part}"] = partial("card-{$part}", $data);
        }

        return $movedTo === null
            ? ['html' => ["#card-{$id}" => $this->tile($id), ...$panels]]
            : ['remove' => ["#card-{$id}"], 'append' => ["#list-{$movedTo} [data-cards]" => $this->tile($id)], 'html' => $panels];
    }
}
