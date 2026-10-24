<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ListService;

/** Lists on the site; the rules are in ListService. */
final class ListController extends Controller
{
    public function store(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, visitors: 'edit');
        $list = (new ListService())->create($board, (string) $request->input('name', ''));

        return $this->ok('', ['append' => ['[data-lists]' => partial('list', ['list' => $list, 'cards' => []])]]);
    }

    /** Drag & drop of lists: ids from left to right. */
    public function order(Request $request, string $uuid): Response
    {
        (new ListService())->reorder($this->board($uuid, visitors: 'edit'), (array) $request->input('ids', []));

        return $this->ok();
    }

    public function update(Request $request, string $id): Response
    {
        [$list] = $this->list((int) $id, visitors: 'edit');
        (new ListService())->rename($list, (string) $request->input('name', ''));

        return $this->ok();
    }

    public function archive(Request $request, string $id): Response
    {
        [$list, $board] = $this->list((int) $id, visitors: 'edit');
        (new ListService())->archive($list);

        return $this->ok(__('List archived.'), ['remove' => ["#list-{$list->id}"], ...$this->archivedPanel($board)]);
    }

    public function restore(Request $request, string $id): Response
    {
        [$list] = $this->list((int) $id, visitors: 'edit');
        (new ListService())->restore($list);

        return $this->ok(__('List restored.'), ['reload' => true]);
    }

    /** Hard delete with all its cards: board owner only. */
    public function destroy(Request $request, string $id): Response
    {
        [$list, $board] = $this->list((int) $id, owner: true);
        (new ListService())->destroy($list);

        return $this->ok(__('List deleted.'), ['remove' => ["#list-{$list->id}"], ...$this->archivedPanel($board)]);
    }
}
