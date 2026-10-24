<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Board;
use App\Models\CalendarAccount;
use App\Services\BoardService;
use App\Services\Unsplash;

final class BoardController extends Controller
{
    /** Dashboard: calendars, favourites, the other boards — or the archived ones (?archived=1). */
    public function index(Request $request): Response
    {
        if ($request->input('shared')) {
            return $this->shared();
        }
        $archived = (bool) $request->input('archived');
        $boards = auth()->boards($archived);

        return view('pages/dashboard', [
            'title' => $archived ? __('Archived boards') : __('Boards'),
            'bodyClass' => 'is-dashboard',
            'expect' => 'dashboard-end', // first paint once the tiles are there: they take part in the view transition
            'archived' => $archived,
            'boards' => $boards,
            'calendars' => $archived ? [] : CalendarAccount::forUser(auth()),
        ]);
    }

    /** From the side menu only: the shared boards, in two sections (with me / by me). */
    private function shared(): Response
    {
        $boards = auth()->boards();

        return view('pages/shared', [
            'title' => __('Shared boards'),
            'bodyClass' => 'is-dashboard',
            'withMe' => array_values(array_filter($boards, static fn (Board $b): bool => !$b->isOwner())),
            'byMe' => array_values(array_filter($boards, static fn (Board $b): bool => $b->isOwner() && (int) $b->member_count > 1)),
        ]);
    }

    public function show(Request $request, string $uuid): Response
    {
        if (!auth() && !Board::forVisitor($uuid)) {
            return redirect('/login'); // not public: as for any page that needs signing in
        }
        $board = $this->board($uuid, archived: true, visitors: 'read');
        if ($board->isArchived()) {
            Session::flash('error', __('The board “:name” is archived: restore it to open it.', ['name' => $board->name]));
            return redirect('/dashboard?archived=1');
        }
        if (!$board->isVisitor()) {
            $board->touch(auth());
        }

        return view('pages/board', [
            'title' => $board->name,
            'board' => $board,
            'lists' => $board->lists(),
            'cards' => $board->cardsByList(),
            'members' => $board->members(),
        ], 'layouts/board');
    }

    public function store(Request $request): Response
    {
        $board = $this->boards()->create((string) $request->input('name', ''), $request->input('background'));

        return $this->ok('', ['redirect' => '/boards/' . $board->uuid]);
    }

    /** Rename and/or change background (any member, like on Trello). */
    public function update(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid);
        $changes = array_intersect_key($request->all(), array_flip(['name', 'background']));
        $this->boards()->update($board, $changes);
        if (isset($changes['background'])) {
            return $this->ok('', ['reload' => true]);
        }

        return $this->ok('', isset($changes['name']) ? $this->renamed((string) $board->name) : []);
    }

    public function favorite(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, archived: true);
        $on = !$board->is_favorite;
        $this->boards()->favorite($board, $on);
        if ($request->input('dashboard')) { // the board moves between "Favourites" and "My boards"
            return $this->ok('', ['html' => ['#dashboard-boards' => partial('dashboard-boards', ['boards' => auth()->boards()])]]);
        }

        return $this->ok('', ['toggle' => ['on' => $on, 'class' => 'is-on']]);
    }

    public function archive(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, owner: true);
        $this->boards()->archive($board);

        return $this->ok(__('Board archived. You can find it under «Archived boards».'), ['redirect' => '/dashboard']);
    }

    public function restore(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, owner: true, archived: true);
        $this->boards()->restore($board);

        return $this->ok(__('Board restored.'), ['redirect' => '/boards/' . $board->uuid]);
    }

    /** Hard delete, owner only, and only from the archive. */
    public function destroy(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, owner: true, archived: true);
        $this->boards()->destroy($board);

        return $this->ok(__('Board deleted.'), ['reload' => true]);
    }

    public function leave(Request $request, string $uuid): Response
    {
        $board = $this->board($uuid, archived: true);
        $this->boards()->leave($board);

        return $this->ok(__('You left the board “:name”.', ['name' => $board->name]), ['redirect' => '/dashboard']);
    }

    /** Unsplash results for the background picker, as ready-made markup. */
    public function photos(Request $request): Response
    {
        if (!Unsplash::enabled()) {
            return Response::json(['ok' => false, 'html' => '']);
        }
        try {
            $photos = Unsplash::search((string) $request->input('q', ''));
        } catch (\RuntimeException $e) {
            error_log('Kalban: ' . $e->getMessage());
            return Response::json(['ok' => false, 'message' => __('Unsplash is not answering right now.')]);
        }

        return Response::json(['ok' => true, 'html' => partial('photos', [
            'photos' => $photos,
            'preview' => preg_match('/^#[\w-]+$/', (string) $request->input('preview', '')) ? (string) $request->input('preview') : '',
        ])]);
    }

    private function boards(): BoardService
    {
        return new BoardService(auth());
    }
}
