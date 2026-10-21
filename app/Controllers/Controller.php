<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;

abstract class Controller
{
    /**
     * Standard JSON answer for fetch() calls. Besides `message`, app.js understands:
     *  redirect (url) · reload (bool) · close (bool: hide the open modal)
     *  html {selector: markup} replaces those elements · append {selector: markup} adds to them
     *  remove [selector, ...] removes them.
     */
    protected function ok(string $message = '', array $extra = []): Response
    {
        if ($message !== '' && (isset($extra['redirect']) || !empty($extra['reload']))) {
            Session::flash('success', $message);
        }

        if (isset($extra['redirect']) && str_starts_with($extra['redirect'], '/')) {
            $extra['redirect'] = url($extra['redirect']);
        }

        return Response::json(['ok' => true, 'message' => $message, ...$extra]);
    }

    protected function fail(string $message, array $errors = [], int $status = 422): Response
    {
        return Response::json(['ok' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    /**
     * A board the signed-in user is a member of (404 otherwise, so boards of others don't even "exist").
     * Archived boards can only be restored or deleted; $owner restricts the action to the owner.
     */
    protected function board(string|int $key, bool $owner = false, bool $archived = false): Board
    {
        $board = Board::forMember($key, auth()) ?? abort(404);
        if ($owner && !$board->isOwner()) {
            abort(403, __('Only the owner of the board can do this.'));
        }
        if (!$archived && $board->isArchived()) {
            abort(409, __('This board is archived.'));
        }

        return $board;
    }

    /** Answer part that redraws the «Archived items» panel of the board menu. */
    protected function archivedPanel(Board $board): array
    {
        return ['html' => ['#archived-items' => partial('archived', ['board' => $board])]];
    }

    /** @return array{0: BoardList, 1: Board} */
    protected function list(int $id, bool $owner = false): array
    {
        $list = BoardList::find($id) ?? abort(404);

        return [$list, $this->board((int) $list->board_id, $owner)];
    }

    /** @return array{0: Card, 1: Board} */
    protected function card(int $id, bool $owner = false): array
    {
        $card = Card::detailed($id) ?? abort(404);

        return [$card, $this->board((int) $card->board_id, $owner)];
    }
}
