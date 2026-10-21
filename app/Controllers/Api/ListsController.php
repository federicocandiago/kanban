<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\BoardList;
use App\Services\ListService;

final class ListsController extends ApiController
{
    /** Lists of a board, left to right: ?archived=true for the archived ones. */
    public function index(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, archived: true);
        $archived = filter_var($request->input('archived', false), FILTER_VALIDATE_BOOLEAN);

        return $this->data(array_map(static fn ($l) => Resource::list($l, $board->uuid), $board->lists($archived)));
    }

    /** name, and optionally position (0 = first; default: last). */
    public function store(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid);
        $input = $this->only($request, ['name', 'position']);
        $list = (new ListService())->create($board, (string) ($input['name'] ?? ''), $this->position($input));

        return $this->created(Resource::list($list, $board->uuid));
    }

    /** name, position, archived. */
    public function update(Request $request, string $id): Response
    {
        [$list, $board] = $this->list((int) $id);
        $input = $this->only($request, ['name', 'position', 'archived']);
        $lists = new ListService();
        $archived = $this->flag($input, 'archived');
        $position = $this->position($input);

        if ($archived === false && $list->isArchived()) {
            $lists->restore($list);
        }
        if (array_key_exists('name', $input)) {
            $lists->rename($list, (string) $input['name']);
        }
        if ($position !== null) {
            $lists->moveTo($board, BoardList::find((int) $list->id), $position);
        }
        if ($archived === true && !$list->isArchived()) {
            $lists->archive($list);
        }

        return $this->data(Resource::list(BoardList::find((int) $list->id), $board->uuid));
    }

    /** Deletes the list and all its cards for good: board owner only. */
    public function destroy(Request $request, string $id): Response
    {
        [$list] = $this->list((int) $id, owner: true);
        (new ListService())->destroy($list);

        return $this->noContent();
    }
}
