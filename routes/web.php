<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\Api\BoardsController as ApiBoards;
use App\Controllers\Api\CardsController as ApiCards;
use App\Controllers\Api\DocsController as ApiDocs;
use App\Controllers\Api\ListsController as ApiLists;
use App\Controllers\Api\MeController as ApiMe;
use App\Controllers\AuthController;
use App\Controllers\BoardController;
use App\Controllers\CalendarController;
use App\Controllers\CardController;
use App\Controllers\HomeController;
use App\Controllers\LabelController;
use App\Controllers\ListController;
use App\Controllers\NotificationController;
use App\Controllers\PageController;
use App\Controllers\SearchController;
use App\Controllers\ShareController;
use App\Core\Router;

return static function (Router $r): void {
    // Public
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/terms', [PageController::class, 'terms']);
    $r->get('/privacy', [PageController::class, 'privacy']);
    $r->get('/cookies', [PageController::class, 'cookies']);
    $r->get('/about', [PageController::class, 'about']);
    $r->get('/locale/{code}', [PageController::class, 'locale']);

    // Auth
    $r->get('/login', [AuthController::class, 'showLogin'], ['guest']);
    $r->post('/login', [AuthController::class, 'login'], ['guest']);
    $r->get('/register', [AuthController::class, 'showRegister'], ['guest']);
    $r->post('/register', [AuthController::class, 'register'], ['guest']);
    $r->post('/logout', [AuthController::class, 'logout']);
    $r->get('/verify/resend', [AuthController::class, 'showResend'], ['guest']);
    $r->post('/verify/resend', [AuthController::class, 'resend'], ['guest']);
    $r->get('/verify/{token}', [AuthController::class, 'verify']);

    // Global search
    $r->get('/search', [SearchController::class, 'page'], ['auth']);
    $r->get('/search/preview', [SearchController::class, 'preview'], ['auth']);

    // Boards
    $r->get('/dashboard', [BoardController::class, 'index'], ['auth']);
    $r->get('/backgrounds', [BoardController::class, 'photos'], ['auth']);
    $r->post('/boards', [BoardController::class, 'store'], ['auth']);
    $r->get('/boards/{uuid}', [BoardController::class, 'show'], ['auth']);
    $r->post('/boards/{uuid}/update', [BoardController::class, 'update'], ['auth']);
    $r->post('/boards/{uuid}/favorite', [BoardController::class, 'favorite'], ['auth']);
    $r->post('/boards/{uuid}/archive', [BoardController::class, 'archive'], ['auth']);
    $r->post('/boards/{uuid}/restore', [BoardController::class, 'restore'], ['auth']);
    $r->post('/boards/{uuid}/delete', [BoardController::class, 'destroy'], ['auth']);
    $r->post('/boards/{uuid}/leave', [BoardController::class, 'leave'], ['auth']);

    // Sharing (boards only, never single cards)
    $r->post('/boards/{uuid}/invites', [ShareController::class, 'invite'], ['auth']);
    $r->post('/boards/{uuid}/members/{id}/remove', [ShareController::class, 'remove'], ['auth']);
    $r->post('/invites/{id}/cancel', [ShareController::class, 'cancel'], ['auth']);
    $r->post('/invites/{id}/accept', [ShareController::class, 'accept'], ['auth']);
    $r->post('/invites/{id}/decline', [ShareController::class, 'decline'], ['auth']);
    $r->post('/notifications/read', [NotificationController::class, 'read'], ['auth']);

    // Lists
    $r->post('/boards/{uuid}/lists', [ListController::class, 'store'], ['auth']);
    $r->post('/boards/{uuid}/lists/order', [ListController::class, 'order'], ['auth']);
    $r->post('/lists/{id}/update', [ListController::class, 'update'], ['auth']);
    $r->post('/lists/{id}/archive', [ListController::class, 'archive'], ['auth']);
    $r->post('/lists/{id}/restore', [ListController::class, 'restore'], ['auth']);
    $r->post('/lists/{id}/delete', [ListController::class, 'destroy'], ['auth']);

    // Cards
    $r->post('/lists/{id}/cards', [CardController::class, 'store'], ['auth']);
    $r->get('/boards/{uuid}/cards/new', [CardController::class, 'new'], ['auth']);
    $r->post('/boards/{uuid}/cards', [CardController::class, 'add'], ['auth']);
    $r->get('/cards/{id}', [CardController::class, 'show'], ['auth']);
    $r->post('/cards/{id}/update', [CardController::class, 'update'], ['auth']);
    $r->post('/cards/{id}/move', [CardController::class, 'move'], ['auth']);
    $r->post('/cards/{id}/labels', [CardController::class, 'labels'], ['auth']);
    $r->post('/cards/{id}/attachments', [CardController::class, 'attach'], ['auth']);
    $r->post('/cards/{id}/checklist', [CardController::class, 'addCheck'], ['auth']);
    $r->post('/cards/{id}/checklist/order', [CardController::class, 'orderChecks'], ['auth']);
    $r->post('/checklist/{id}/update', [CardController::class, 'updateCheck'], ['auth']);
    $r->post('/checklist/{id}/delete', [CardController::class, 'deleteCheck'], ['auth']);
    $r->post('/cards/{id}/notes', [CardController::class, 'addNote'], ['auth']);
    $r->post('/notes/{id}/update', [CardController::class, 'updateNote'], ['auth']);
    $r->post('/notes/{id}/delete', [CardController::class, 'deleteNote'], ['auth']);
    $r->post('/cards/{id}/archive', [CardController::class, 'archive'], ['auth']);
    $r->post('/cards/{id}/restore', [CardController::class, 'restore'], ['auth']);
    $r->post('/cards/{id}/delete', [CardController::class, 'destroy'], ['auth']);
    $r->get('/attachments/{id}', [CardController::class, 'download'], ['auth']);
    $r->post('/attachments/{id}/delete', [CardController::class, 'detach'], ['auth']);

    // Calendars (the user's CalDAV accounts, any number)
    $r->get('/calendar', [CalendarController::class, 'first'], ['auth']);
    $r->post('/calendars', [CalendarController::class, 'connect'], ['auth']);
    $r->get('/calendars/{id}', [CalendarController::class, 'show'], ['auth']);
    $r->post('/calendars/{id}/sync', [CalendarController::class, 'sync'], ['auth']);
    $r->post('/calendars/{id}/update', [CalendarController::class, 'edit'], ['auth']);
    $r->post('/calendars/{id}/rename', [CalendarController::class, 'rename'], ['auth']);
    $r->post('/calendars/{id}/background', [CalendarController::class, 'background'], ['auth']);
    $r->post('/calendars/{id}/delete', [CalendarController::class, 'disconnect'], ['auth']);
    $r->post('/calendars/{id}/days/{day}/events', [CalendarController::class, 'store'], ['auth']);
    $r->post('/calendars/{id}/events', [CalendarController::class, 'create'], ['auth']);
    $r->get('/events/{id}', [CalendarController::class, 'event'], ['auth']);
    $r->post('/events/{id}/update', [CalendarController::class, 'update'], ['auth']);
    $r->post('/events/{id}/move', [CalendarController::class, 'move'], ['auth']);
    $r->post('/events/{id}/delete', [CalendarController::class, 'destroy'], ['auth']);
    $r->post('/events/{id}/notes', [CalendarController::class, 'addNote'], ['auth']);
    $r->post('/event-notes/{id}/update', [CalendarController::class, 'updateNote'], ['auth']);
    $r->post('/event-notes/{id}/delete', [CalendarController::class, 'deleteNote'], ['auth']);
    $r->post('/events/{id}/attachments', [CalendarController::class, 'attach'], ['auth']);
    $r->get('/events/{id}/attachments/{index}', [CalendarController::class, 'download'], ['auth']);
    $r->post('/events/{id}/attachments/{index}/delete', [CalendarController::class, 'detach'], ['auth']);

    // Account
    $r->get('/account', [AccountController::class, 'show'], ['auth']);
    $r->post('/account/profile', [AccountController::class, 'profile'], ['auth']);
    $r->post('/account/theme', [AccountController::class, 'theme'], ['auth']);
    $r->post('/account/avatar', [AccountController::class, 'avatar'], ['auth']);
    $r->post('/account/avatar/delete', [AccountController::class, 'deleteAvatar'], ['auth']);
    $r->post('/account/password', [AccountController::class, 'password'], ['auth']);
    $r->post('/account/delete', [AccountController::class, 'destroy'], ['auth']);
    $r->get('/account/export', [AccountController::class, 'export'], ['auth']);
    $r->post('/account/import', [AccountController::class, 'import'], ['auth']);
    $r->get('/avatars/{file}', [AccountController::class, 'avatarFile'], ['auth']);
    $r->post('/labels', [LabelController::class, 'store'], ['auth']);
    $r->post('/labels/{id}/update', [LabelController::class, 'update'], ['auth']);
    $r->post('/labels/{id}/delete', [LabelController::class, 'destroy'], ['auth']);

    // API access (Settings › API access)
    $r->post('/account/api', [AccountController::class, 'api'], ['auth']);
    $r->post('/account/api/token', [AccountController::class, 'apiToken'], ['auth']);

    // Back-office
    $r->get('/admin', [AdminController::class, 'index'], ['auth', 'admin']);
    $r->post('/admin/settings', [AdminController::class, 'settings'], ['auth', 'admin']);
    $r->post('/admin/users/{id}', [AdminController::class, 'updateUser'], ['auth', 'admin']);
    $r->post('/admin/users/{id}/suspend', [AdminController::class, 'toggleUser'], ['auth', 'admin']);
    $r->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser'], ['auth', 'admin']);
    $r->post('/admin/users/{id}/api-token/delete', [AdminController::class, 'revokeToken'], ['auth', 'admin']);

    // API v1 — "Authorization: Bearer kalban_…"; documentation at /api/docs (OpenAPI at /api/v1/openapi.json).
    // The calendar is not part of the API.
    $r->get('/api/docs', [ApiDocs::class, 'page']);
    $r->get('/api/v1/openapi.json', [ApiDocs::class, 'spec']);
    $read = ['api'];
    $write = ['api', 'write'];
    $r->get('/api/v1/me', [ApiMe::class, 'me'], $read);
    $r->get('/api/v1/labels', [ApiMe::class, 'labels'], $read);
    $r->get('/api/v1/boards', [ApiBoards::class, 'index'], $read);
    $r->post('/api/v1/boards', [ApiBoards::class, 'store'], $write);
    $r->get('/api/v1/boards/{uuid}', [ApiBoards::class, 'show'], $read);
    $r->patch('/api/v1/boards/{uuid}', [ApiBoards::class, 'update'], $write);
    $r->delete('/api/v1/boards/{uuid}', [ApiBoards::class, 'destroy'], $write);
    $r->get('/api/v1/boards/{uuid}/lists', [ApiLists::class, 'index'], $read);
    $r->post('/api/v1/boards/{uuid}/lists', [ApiLists::class, 'store'], $write);
    $r->patch('/api/v1/lists/{id}', [ApiLists::class, 'update'], $write);
    $r->delete('/api/v1/lists/{id}', [ApiLists::class, 'destroy'], $write);
    $r->get('/api/v1/lists/{id}/cards', [ApiCards::class, 'index'], $read);
    $r->post('/api/v1/lists/{id}/cards', [ApiCards::class, 'store'], $write);
    $r->get('/api/v1/cards/{id}', [ApiCards::class, 'show'], $read);
    $r->patch('/api/v1/cards/{id}', [ApiCards::class, 'update'], $write);
    $r->delete('/api/v1/cards/{id}', [ApiCards::class, 'destroy'], $write);
    $r->put('/api/v1/cards/{id}/labels/{label}', [ApiCards::class, 'addLabel'], $write);
    $r->delete('/api/v1/cards/{id}/labels/{label}', [ApiCards::class, 'removeLabel'], $write);
    $r->get('/api/v1/cards/{id}/activity', [ApiCards::class, 'activity'], $read);
    $r->get('/api/v1/cards/{id}/attachments', [ApiCards::class, 'attachments'], $read);
    $r->post('/api/v1/cards/{id}/attachments', [ApiCards::class, 'attach'], $write);
    $r->get('/api/v1/attachments/{id}/download', [ApiCards::class, 'download'], $read);
    $r->delete('/api/v1/attachments/{id}', [ApiCards::class, 'detach'], $write);
};
