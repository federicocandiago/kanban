<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Board;
use App\Services\BoardService;

final class BoardsController extends ApiController
{
    /** Boards the user is a member of: ?archived=true for the archived ones. */
    public function index(Request $request): Response
    {
        $archived = filter_var($request->input('archived', false), FILTER_VALIDATE_BOOLEAN);

        return $this->data(array_map([Resource::class, 'board'], auth()->boards($archived)));
    }

    public function store(Request $request): Response
    {
        $board = $this->boards()->create((string) $request->input('name', ''), $request->input('background'));

        return $this->created(Resource::board($board));
    }

    /** One board with its members, its active lists and their active cards. */
    public function show(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, archived: true);
        $cards = $board->cardsByList();

        return $this->data([
            ...Resource::board($board),
            'members' => array_map(static fn ($u) => Resource::user($u), $board->members()),
            'lists' => array_map(static fn ($list) => Resource::list($list, $board->uuid) + [
                'cards' => array_map(static fn ($card) => Resource::card($card, $board->uuid), $cards[(int) $list->id] ?? []),
            ], $board->lists()),
        ]);
    }

    /** name, background, favorite; archived (owner) archives or restores it. */
    public function update(Request $request, string $uuid): Response
    {
        $input = $this->only($request, ['name', 'background', 'favorite', 'archived']);
        $archived = $this->flag($input, 'archived');
        $favorite = $this->flag($input, 'favorite');
        $board = $this->board($uuid, owner: $archived !== null, archived: true);

        if ($archived === false && $board->isArchived()) {
            $this->boards()->restore($board);
        }
        if ($board->isArchived() && array_intersect_key($input, ['name' => 1, 'background' => 1])) {
            throw new HttpException(409, __('This board is archived.'));
        }
        $this->boards()->update($board, array_intersect_key($input, ['name' => 1, 'background' => 1]));
        if ($favorite !== null) {
            $this->boards()->favorite($board, $favorite);
        }
        if ($archived === true && !$board->isArchived()) {
            $this->boards()->archive($board);
        }

        return $this->data(Resource::board(Board::forMember($uuid, auth())));
    }

    /** Deletes the board for good: owner only, and only once archived. */
    public function destroy(Request $request, string $uuid): Response
    {
        $this->boards()->destroy($this->board($uuid, owner: true, archived: true));

        return $this->noContent();
    }

    private function boards(): BoardService
    {
        return new BoardService(auth());
    }
}
