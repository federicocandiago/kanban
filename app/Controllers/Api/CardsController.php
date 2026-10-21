<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\CardController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Attachment;
use App\Models\Card;
use App\Models\Label;
use App\Services\CardService;

final class CardsController extends ApiController
{
    private const FIELDS = ['title', 'description', 'start_date', 'end_date'];

    /** Cards of a list, top to bottom: ?archived=true for the archived ones. */
    public function index(Request $request, string $id): Response
    {
        [$list, $board] = $this->list((int) $id);
        $archived = filter_var($request->input('archived', false), FILTER_VALIDATE_BOOLEAN);
        $cards = Card::withDetails('c.list_id = ? AND c.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL'), [$list->id]);

        return $this->data(array_map(static fn ($c) => Resource::card($c, $board->uuid), $cards));
    }

    /** title (required), description, start_date, end_date (YYYY-MM-DD), position (0 = top; default: bottom). */
    public function store(Request $request, string $id): Response
    {
        [$list, $board] = $this->list((int) $id);
        $input = $this->only($request, [...self::FIELDS, 'position']);
        $position = $this->position($input);
        $db = Database::connection();
        $db->pdo()->beginTransaction(); // a card with invalid dates is not half created
        try {
            $card = $this->cards()->create($list, (string) ($input['title'] ?? ''));
            $this->cards()->update($card, $board, array_intersect_key($input, array_flip(['description', 'start_date', 'end_date'])));
            if ($position !== null) {
                $this->cards()->moveTo(Card::detailed((int) $card->id), $board, (int) $list->id, $position);
            }
            $db->pdo()->commit();
        } catch (\Throwable $e) {
            $db->pdo()->rollBack();
            throw $e;
        }

        return $this->created(Resource::card(Card::detailed((int) $card->id), $board->uuid));
    }

    /** One card with its attachments and its change log. */
    public function show(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id);

        return $this->data(Resource::card($card, $board->uuid) + [
            'attachments' => array_map([Resource::class, 'attachment'], $card->attachments()),
            'activity' => array_map([Resource::class, 'activity'], $card->activity()),
        ]);
    }

    /** title, description, start_date, end_date; list_id and/or position move it; archived. */
    public function update(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id);
        $input = $this->only($request, [...self::FIELDS, 'list_id', 'position', 'archived']);
        $archived = $this->flag($input, 'archived');
        $position = $this->position($input);

        if ($archived === false && $card->isArchived()) {
            $this->cards()->restore($card);
        }
        $fields = array_intersect_key($input, array_flip(self::FIELDS));
        if ($position === null && array_key_exists('list_id', $input)) {
            $fields['list_id'] = $input['list_id']; // to the bottom of that list
        }
        $this->cards()->update(Card::detailed((int) $card->id), $board, $fields);
        if ($position !== null) {
            $this->cards()->moveTo(Card::detailed((int) $card->id), $board, (int) ($input['list_id'] ?? $card->list_id), $position);
        }
        if ($archived === true && !$card->isArchived()) {
            $this->cards()->archive($card);
        }

        return $this->data(Resource::card(Card::detailed((int) $card->id), $board->uuid));
    }

    /** Deletes the card and its files for good: board owner only. */
    public function destroy(Request $request, string $id): Response
    {
        [$card] = $this->card((int) $id, owner: true);
        $this->cards()->destroy($card);

        return $this->noContent();
    }

    /** Puts one of the user's own labels on the card (PUT: doing it twice changes nothing). */
    public function addLabel(Request $request, string $id, string $label): Response
    {
        [$card, $board] = $this->card((int) $id);
        $this->cards()->addLabel($card, $this->myLabel((int) $label));

        return $this->data(Resource::card(Card::detailed((int) $card->id), $board->uuid));
    }

    public function removeLabel(Request $request, string $id, string $label): Response
    {
        [$card, $board] = $this->card((int) $id);
        $this->cards()->removeLabel($card, $this->myLabel((int) $label));

        return $this->data(Resource::card(Card::detailed((int) $card->id), $board->uuid));
    }

    public function attachments(Request $request, string $id): Response
    {
        [$card] = $this->card((int) $id);

        return $this->data(array_map([Resource::class, 'attachment'], $card->attachments()));
    }

    /** multipart/form-data with a "file" field (max ATTACHMENT_MAX_MB). */
    public function attach(Request $request, string $id): Response
    {
        [$card, $board] = $this->card((int) $id);
        $file = $request->files('file')[0] ?? null;
        $attachment = $this->cards()->attach($card, $board, $file, $file === null && $request->contentLength() > 0);

        return $this->created(Resource::attachment($attachment));
    }

    public function download(Request $request, string $id): Response
    {
        $attachment = Attachment::find((int) $id) ?? abort(404);
        [, $board] = $this->card((int) $attachment->card_id);

        return CardController::sendAttachment($attachment, $board);
    }

    public function detach(Request $request, string $id): Response
    {
        $attachment = Attachment::find((int) $id) ?? abort(404);
        [$card, $board] = $this->card((int) $attachment->card_id);
        $this->cards()->detach($card, $board, $attachment);

        return $this->noContent();
    }

    public function activity(Request $request, string $id): Response
    {
        [$card] = $this->card((int) $id);

        return $this->data(array_map([Resource::class, 'activity'], $card->activity()));
    }

    private function myLabel(int $id): Label
    {
        $label = Label::find($id);

        return $label && (int) $label->user_id === (int) auth()->id ? $label : abort(404, __('Label not found among yours.'));
    }

    private function cards(): CardService
    {
        return new CardService(auth());
    }
}
