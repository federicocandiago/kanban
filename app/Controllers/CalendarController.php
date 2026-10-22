<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Crypt;
use App\Core\Throttle;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\Event;
use App\Models\EventActivity;
use App\Services\AttachmentService;
use App\Services\CalDav;
use App\Services\CalDavException;
use App\Services\CalendarService;
use DateTimeImmutable;

/**
 * A calendar: a month shown like a board, one list per day. Events come from one of the user's
 * CalDAV accounts (see CalendarService); every answer redraws the month from the cache.
 */
final class CalendarController extends Controller
{
    /** Old address (/calendar): the user's first calendar, or Settings when there is none. */
    public function first(Request $request): Response
    {
        $account = CalendarAccount::forUser(auth())[0] ?? null;
        $query = $_SERVER['QUERY_STRING'] ?? '';

        return redirect($account ? '/calendars/' . $account->id . ($query !== '' ? '?' . $query : '') : '/account#calendar');
    }

    public function show(Request $request, string $id): Response
    {
        $account = $this->account($id);
        $month = $this->month($request);

        return view('pages/calendar', [
            'title' => $account->name,
            'account' => $account,
            'month' => $month,
            'days' => $this->days($account, $month),
            'tz' => auth()->timezone(),
        ], 'layouts/calendar');
    }

    /** Called by the page after it is drawn (and every few minutes): redraws the days if something changed. */
    public function sync(Request $request, string $id): Response
    {
        $account = $this->account($id);
        $month = $this->month($request);

        return $this->remote(function () use ($account, $month): Response {
            $changed = (new CalendarService($account, auth()))->sync($month);

            return $this->ok('', $changed ? $this->redraw($account, $month) : []);
        });
    }

    /** Settings › Calendars › Add a calendar. */
    public function connect(Request $request): Response
    {
        return $this->save($request, null);
    }

    /** Settings › Calendars › Edit: name and how quick add works, or new credentials (checked with the server). */
    public function edit(Request $request, string $id): Response
    {
        $account = $this->account($id);
        $default = Calendar::find((int) $request->input('default_calendar_id'));
        $account->update([
            'all_day_default' => (int) (bool) $request->input('all_day_default'), // a switch: absent when off
            'default_calendar_id' => $default && (int) $default->account_id === (int) $account->id && $default->writable ? $default->id : null,
        ]);
        $password = (string) $request->input('password', '');
        $sameServer = (string) $request->input('username', $account->username) === $account->username
            && ($account->provider === 'icloud' || (string) $request->input('server_url', $account->server_url) === $account->server_url);
        if ($password === '' && $sameServer) {
            $name = $this->name($request);
            if ($name === null) {
                return $this->fail(__('Please check the form.'), ['name' => __('Give the calendar a name (up to 100 characters).')]);
            }
            $account->update(['name' => $name]);

            return $this->ok(__('Calendar saved.'), ['redirect' => '/account#calendar']);
        }

        return $this->save($request, $account);
    }

    public function disconnect(Request $request, string $id): Response
    {
        $this->account($id)->delete(); // cache and log go too (ON DELETE CASCADE)

        return $this->ok(__('Calendar disconnected.'), ['redirect' => '/account#calendar']);
    }

    /** New account, or new credentials for $account. */
    private function save(Request $request, ?CalendarAccount $account): Response
    {
        if (!Crypt::ready()) {
            return $this->fail(__('Set APP_KEY in .env first (php bin/key.php).'));
        }
        $provider = $account ? (string) $account->provider : ($request->input('provider') === 'caldav' ? 'caldav' : 'icloud');
        $url = $provider === 'icloud' ? CalDav::ICLOUD : trim((string) $request->input('server_url', ''));
        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $name = $this->name($request);

        $errors = [];
        if ($name === null) {
            $errors['name'] = __('Give the calendar a name (up to 100 characters).');
        }
        if ($provider === 'caldav' && !preg_match('#^https?://#i', $url)) {
            $errors['server_url'] = __('Enter the address of the CalDAV server (https://…).');
        }
        if ($username === '') {
            $errors['username'] = __('Enter the username.');
        }
        if ($password === '') {
            $errors['password'] = $account ? __('Enter the password again to change the account.') : __('Enter the password.');
        }
        foreach (CalendarAccount::forUser(auth()) as $other) {
            if ((int) $other->id !== (int) ($account->id ?? 0) && $other->server_url === $url && $other->username === $username) {
                $errors['username'] = __('This account is already connected as “:name”.', ['name' => $other->name]);
            }
        }
        if ($errors) {
            return $this->fail(__('Please check the form.'), $errors);
        }

        // each try is a request to the calendar server: THROTTLE_CALENDAR_CONNECT_PER_HOUR per user
        $key = 'calendar|' . auth()->id;
        if ($wait = Throttle::blocked($key, Throttle::limit('CALENDAR_CONNECT_PER_HOUR', 10))) {
            return $this->fail(__('Too many connection attempts: try again in :min minutes.', ['min' => Throttle::minutes($wait)]), [], 429);
        }
        Throttle::hit($key, 3600);

        return $this->remote(function () use ($name, $provider, $url, $username, $password, $account): Response {
            $saved = CalendarService::connect(auth(), $name, $provider, $url, $username, preg_replace('/\s+/', '', $password), $account);

            return $this->ok(__('Calendar connected: :n calendars found.', ['n' => count($saved->calendars())]), ['redirect' => '/account#calendar']);
        });
    }

    private function name(Request $request): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $request->input('name', '')) ?? '');

        return $name === '' || mb_strlen($name) > 100 ? null : $name;
    }

    /** Quick add at the bottom of a day. */
    public function store(Request $request, string $id, string $day): Response
    {
        $account = $this->account($id);
        $title = trim(preg_replace('/\s+/', ' ', (string) $request->input('title', '')) ?? '');
        if ($title === '' || !$this->isDay($day)) {
            return $this->fail(__('Give the event a name.'));
        }
        $calendar = $account->defaultCalendar() ?? abort(409, __('No calendar of this account can be written.'));
        $view = $this->view($request) ?? substr($day, 0, 7);
        if (!$account->all_day_default) { // timed: nothing is saved yet, the window opens to check it
            $now = new DateTimeImmutable('now', auth()->timezone());
            $start = (new DateTimeImmutable($day, auth()->timezone()))->setTime((int) $now->format('G'), intdiv((int) $now->format('i'), 5) * 5); // now, on that day

            return $this->ok('', ['open' => partial('event-new', [
                'account' => $account, 'title' => mb_substr($title, 0, 500), 'start' => $start,
                'end' => $start->modify('+' . auth()->eventMinutes() . ' minutes'), 'calendar' => $calendar,
                'calendars' => array_filter($account->calendars(), static fn (Calendar $c): bool => (bool) $c->writable), 'view' => $view,
            ])]);
        }
        $start = new DateTimeImmutable($day, auth()->timezone());

        return $this->remote(fn (): Response => $this->ok('', $this->redraw($account, $view, [
            (new CalendarService($account, auth()))->create($calendar, mb_substr($title, 0, 500), $start, $start->modify('+1 day'), true),
        ])));
    }

    /** Saves the event checked in the "new event" window (quick add of timed events). */
    public function create(Request $request, string $id): Response
    {
        $account = $this->account($id);
        [$errors, $title, $allDay, $start, $end, $calendar] = $this->fields($request, $account, '', (int) ($account->defaultCalendar()->id ?? 0));
        if ($errors) {
            return $this->fail(__('Please check the form.'), $errors);
        }
        $description = trim(str_replace("\r\n", "\n", (string) $request->input('description', '')));
        $view = $this->view($request) ?? $start->format('Y-m');

        // the window's nonce: a second Confirm of the same window (double tap, slow network) creates nothing more
        $nonce = substr((string) $request->input('nonce', ''), 0, 64);
        $done = (array) Session::get('event_nonces', []);
        if ($nonce !== '' && in_array($nonce, $done, true)) {
            return $this->ok('', ['close' => true, ...$this->redraw($account, $view)]);
        }
        if ($nonce !== '') {
            Session::set('event_nonces', array_slice([...$done, $nonce], -20));
        }

        // the same title at the same moment in the same calendar is already there: not sent again
        $startsAt = $allDay ? $start->format('Y-m-d 00:00:00') : $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $twin = \App\Core\Database::connection()->scalar(
            'SELECT id FROM calendar_events WHERE calendar_id = ? AND summary = ? AND starts_at = ? AND all_day = ? LIMIT 1',
            [$calendar->id, $title, $startsAt, (int) $allDay]
        );
        if ($twin !== false && $twin !== null) {
            return $this->ok(__('This event is already in the calendar.'), ['close' => true, ...$this->redraw($account, $view)]);
        }

        try {
            $event = (new CalendarService($account, auth()))->create($calendar, $title, $start, $allDay ? $end->modify('+1 day') : $end, $allDay, $description);
        } catch (CalDavException $e) {
            Session::set('event_nonces', array_values(array_diff((array) Session::get('event_nonces', []), [$nonce]))); // not saved: it can be tried again
            return $this->remote(static fn () => throw $e);
        }

        return $this->ok('', ['close' => true, ...$this->redraw($account, $view, [$event])]);
    }

    /**
     * Title, dates and sub-calendar sent by an event window, checked.
     * @return array{0: array<string, string>, 1: string, 2: bool, 3: ?DateTimeImmutable, 4: ?DateTimeImmutable, 5: ?Calendar}
     *         errors, title, all day, start, end (whole days: the last day), calendar
     */
    private function fields(Request $request, CalendarAccount $account, string $title, int $calendarId): array
    {
        $tz = auth()->timezone();
        $title = trim(preg_replace('/\s+/', ' ', (string) $request->input('title', $title)) ?? '');
        $allDay = (bool) $request->input('all_day');
        $start = $this->moment($request->input('start_date'), $allDay ? null : $request->input('start_time'), $tz);
        $end = $this->moment($request->input('end_date'), $allDay ? null : $request->input('end_time'), $tz);
        $calendar = Calendar::find((int) $request->input('calendar_id', $calendarId));

        $errors = [];
        if ($title === '') {
            $errors['title'] = __('Give the event a name.');
        }
        if ($start === null) {
            $errors['start_date'] = __('Invalid date.');
        }
        if ($end === null) {
            $errors['end_date'] = __('Invalid date.');
        } elseif ($start && ($allDay ? $end < $start : $end <= $start)) {
            $errors['end_date'] = __('The end comes before the start.');
        }
        if (!$calendar || (int) $calendar->account_id !== (int) $account->id || !$calendar->writable) {
            $errors['calendar_id'] = __('Choose one of your calendars.');
        }

        return [$errors, mb_substr($title, 0, 500), $allDay, $start, $end, $calendar];
    }

    public function event(Request $request, string $id): Response
    {
        [$account, $event] = $this->find((int) $id);

        return Response::html(partial('event', $this->details($account, $event) + ['view' => $this->view($request)]));
    }

    public function update(Request $request, string $id): Response
    {
        [$account, $event] = $this->find((int) $id, editable: true);
        $tz = auth()->timezone();
        [$errors, $title, $allDay, $start, $end, $calendar] = $this->fields($request, $account, $event->summary, (int) $event->calendar_id);
        if ($errors) {
            return $this->fail(__('Please check the form.'), $errors);
        }

        $changes = ['title' => $title, 'calendar' => $calendar];
        $description = trim(str_replace("\r\n", "\n", (string) $request->input('description', $event->description)));
        if ($description !== trim((string) $event->description)) {
            $changes['description'] = $description;
        }
        $dates = $allDay
            ? [$start->format('Y-m-d'), $end->modify('+1 day')->format('Y-m-d')] // whole days: the end day is included
            : [$start->format('c'), $end->format('c')];
        $current = $event->all_day
            ? [$event->start($tz)->format('Y-m-d'), $event->end($tz)->format('Y-m-d')]
            : [$event->start($tz)->format('c'), $event->end($tz)->format('c')];
        if ($dates !== $current || $allDay !== (bool) $event->all_day) {
            $changes += ['start' => $start, 'end' => $allDay ? $end->modify('+1 day') : $end, 'all_day' => $allDay];
        }

        return $this->remote(function () use ($account, $event, $changes, $request): Response {
            $updated = (new CalendarService($account, auth()))->update($event, $changes);
            $answer = $this->redraw($account, $this->view($request), [$updated], $updated);
            if ((int) $updated->id !== (int) $event->id) { // moved to another sub-calendar: the window shows the new copy
                $answer['html']['#card-panel'] = partial('event', $this->details($account, $updated) + ['view' => $this->view($request)]);
            }

            return $this->ok('', $answer);
        });
    }

    /** Drag & drop onto another day. */
    public function move(Request $request, string $id): Response
    {
        [$account, $event] = $this->find((int) $id, editable: true);
        $day = (string) $request->input('date', '');
        if (!$this->isDay($day)) {
            return $this->fail(__('Invalid date.'));
        }
        if ($day === $event->start_day) {
            return $this->ok('', $this->redraw($account, $this->view($request) ?? substr($day, 0, 7)));
        }

        return $this->remote(fn (): Response => $this->ok('', $this->redraw($account, $this->view($request) ?? substr($day, 0, 7), [
            (new CalendarService($account, auth()))->moveTo($event, $day),
        ])));
    }

    public function destroy(Request $request, string $id): Response
    {
        [$account, $event] = $this->find((int) $id, editable: true);

        return $this->remote(function () use ($account, $event, $request): Response {
            (new CalendarService($account, auth()))->delete($event);

            return $this->ok(__('Event deleted.'), ['close' => true, ...$this->redraw($account, $this->view($request) ?? substr((string) $event->start_day, 0, 7))]);
        });
    }

    public function attach(Request $request, string $id): Response
    {
        [$account, $event] = $this->find((int) $id, editable: true);
        if (!$account->attachments) {
            return $this->fail(__('This calendar server does not accept attachments.'));
        }
        $file = $request->files('file')[0] ?? null;
        $tooBig = __('The file is too large (max :size).', ['size' => format_bytes(AttachmentService::maxBytes())]);
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > AttachmentService::maxBytes()) {
            $error = $file === null || in_array($file['error'], [UPLOAD_ERR_NO_FILE], true) ? __('Please choose a file.') : $tooBig;
            return $this->fail($error, ['file' => $error]);
        }
        $name = mb_substr(basename(str_replace('\\', '/', $file['name'])), 0, 200) ?: 'file';
        $mime = (function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : '') ?: 'application/octet-stream';

        return $this->remote(function () use ($account, $event, $name, $mime, $file, $request): Response {
            $updated = (new CalendarService($account, auth()))->attach($event, $name, $mime, $file['tmp_name']);

            return $this->ok('', $this->redraw($account, $this->view($request), [$updated], $updated));
        });
    }

    public function detach(Request $request, string $id, string $index): Response
    {
        [$account, $event] = $this->find((int) $id, editable: true);
        $file = $event->attachments()[(int) $index] ?? abort(404);

        return $this->remote(function () use ($account, $event, $file, $request): Response {
            $updated = (new CalendarService($account, auth()))->detach($event, $file['id']);

            return $this->ok('', $this->redraw($account, $this->view($request), [$updated], $updated));
        });
    }

    /** Attachments live on the calendar server, behind the account's credentials: we pass them through. */
    public function download(Request $request, string $id, string $index): Response
    {
        [$account, $event] = $this->find((int) $id);
        $file = $event->attachments()[(int) $index] ?? abort(404);
        try {
            $content = (new CalendarService($account, auth()))->download($file['url']);
        } catch (CalDavException $e) {
            abort(502, $this->message($e));
        }
        $name = str_replace(['"', "\r", "\n"], '', $file['name']);
        $inline = $request->input('inline') && str_starts_with($file['type'], 'image/');

        return (new Response($content['body'], 200, [
            'Content-Type' => $inline ? $file['type'] : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'Cache-Control' => 'private, max-age=3600',
        ]));
    }

    /** A note in the event's log: it stays in this app, the calendar server never sees it. */
    public function addNote(Request $request, string $id): Response
    {
        [$account, $event] = $this->find((int) $id);
        $text = $this->noteText($request) ?? abort(422, __('Write the note first.'));
        EventActivity::create([
            'account_id' => $account->id, 'uid' => $event->uid, 'user_id' => auth()->id, 'user_name' => auth()->name,
            'field' => 'note', 'old_value' => null, 'new_value' => $text,
        ]);

        return $this->ok('', $this->redraw($account, $this->view($request) ?? substr((string) $event->start_day, 0, 7), [], $event));
    }

    public function updateNote(Request $request, string $id): Response
    {
        [$note, $account, $event] = $this->note((int) $id);
        $text = $this->noteText($request) ?? abort(422, __('Write the note first.'));
        $note->update(['new_value' => $text]);

        return $this->ok('', $this->redraw($account, $this->view($request) ?? substr((string) $event->start_day, 0, 7), [], $event));
    }

    public function deleteNote(Request $request, string $id): Response
    {
        [$note, $account, $event] = $this->note((int) $id);
        $note->delete();

        return $this->ok('', $this->redraw($account, $this->view($request) ?? substr((string) $event->start_day, 0, 7), [], $event));
    }

    /** A note of one of the user's events (the calendars are the user's own: they are its author). @return array{0: EventActivity, 1: CalendarAccount, 2: Event} */
    private function note(int $id): array
    {
        $note = EventActivity::find($id);
        $account = $note ? CalendarAccount::ofUser((int) $note->account_id, auth()) : null;
        if (!$note || $note->field !== 'note' || !$account) {
            abort(404);
        }
        $event = Event::first('calendar_id IN (SELECT id FROM calendars WHERE account_id = ?) AND uid = ?', [$account->id, $note->uid]) ?? abort(404, __('This event is no longer in the calendar.'));
        $event = Event::inAccount((int) $event->id, $account) ?? abort(404);

        return [$note, $account, $event];
    }

    private function noteText(Request $request): ?string
    {
        $text = trim(str_replace("\r\n", "\n", (string) $request->input('text', '')));

        return $text === '' ? null : mb_substr($text, 0, 5000);
    }

    private function account(string $id): CalendarAccount
    {
        return CalendarAccount::ofUser((int) $id, auth()) ?? abort(404, __('This calendar is not among yours.'));
    }

    /** @return array{0: CalendarAccount, 1: Event} */
    private function find(int $id, bool $editable = false): array
    {
        $event = Event::ofUser($id, auth()) ?? abort(404, __('This event is no longer in the calendar.'));
        $account = CalendarAccount::ofUser((int) $event->account_id, auth()) ?? abort(404);
        if ($editable && !$event->isEditable()) {
            abort(403, $event->recurring ? __('Recurring events can be changed from your calendar app.') : __('This calendar is read-only.'));
        }

        return [$account, $event];
    }

    /** ?month=2026-10, defaulting to the current month (in the user's time zone). */
    private function month(Request $request): string
    {
        $month = (string) $request->input('month', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : (new DateTimeImmutable('now', auth()->timezone()))->format('Y-m');
    }

    /** The month the page is showing (sent by the page as "view"), to redraw that one. */
    private function view(Request $request): ?string
    {
        $view = (string) $request->input('view', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $view) ? $view : null;
    }

    private function isDay(string $day): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        return $date && $date->format('Y-m-d') === $day;
    }

    private function moment(mixed $date, mixed $time, \DateTimeZone $tz): ?DateTimeImmutable
    {
        $date = trim((string) $date);
        $time = trim((string) ($time ?? ''));
        if (!$this->isDay($date) || ($time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time))) {
            return null;
        }

        return new DateTimeImmutable($date . ' ' . ($time ?: '00:00'), $tz);
    }

    /**
     * The days of a month: each one lists timed events by time, then the whole-day ones
     * and the following days of events that started earlier.
     * @return array<string, Event[]> by "Y-m-d"
     */
    private function days(CalendarAccount $account, string $month): array
    {
        [$from, $to] = CalendarService::monthRange($month, auth()->timezone());
        $days = [];
        for ($day = $from; $day < $to; $day = $day->modify('+1 day')) {
            $days[$day->format('Y-m-d')] = [];
        }
        $events = Event::between($account, array_key_first($days), array_key_last($days));
        foreach ($days as $date => $_) {
            $here = array_filter($events, static fn (Event $e): bool => $e->start_day <= $date && $e->end_day >= $date);
            usort($here, static function (Event $a, Event $b) use ($date): int {
                $rank = static fn (Event $e): int => !$e->all_day && $e->start_day === $date ? 0 : 1;
                return [$rank($a), $a->starts_at] <=> [$rank($b), $b->starts_at];
            });
            $days[$date] = $here;
        }

        return $days;
    }

    /** Answer part that redraws the month (and, with $open, the parts of the open event that can change). */
    private function redraw(CalendarAccount $account, ?string $month, array $events = [], ?Event $open = null): array
    {
        $month ??= substr((string) ($events[0]->start_day ?? date('Y-m')), 0, 7);
        $html = ['#calendar-days' => partial('calendar-days', ['account' => $account, 'days' => $this->days($account, $month), 'tz' => auth()->timezone()])];
        if ($open) {
            $data = $this->details($account, $open) + ['view' => $month];
            foreach (['attachments', 'activity'] as $part) {
                $html["#event-{$part}"] = partial("event-{$part}", $data);
            }
        }

        return ['html' => $html];
    }

    private function details(CalendarAccount $account, Event $event): array
    {
        return [
            'event' => $event,
            'account' => $account,
            'calendars' => array_filter($account->calendars(), static fn (Calendar $c): bool => (bool) $c->writable),
            'tz' => auth()->timezone(),
        ];
    }

    /** Runs a call to the calendar server, turning its errors into a readable answer. */
    private function remote(callable $call): Response
    {
        try {
            return $call();
        } catch (CalDavException $e) {
            error_log('Kalban calendar: ' . $e->getMessage());
            return $this->fail($this->message($e), [], $e->isConflict() ? 409 : 502);
        }
    }

    private function message(CalDavException $e): string
    {
        return match (true) {
            $e->isThrottled() => __('The calendar server asks to wait a little (:s seconds): your changes are safe, try again shortly.', ['s' => $e->retryAfter ?? 60]),
            $e->isConflict() => __('This event was changed on another device in the meantime: reload the calendar and try again.'),
            $e->status === 401 || $e->status === 403 => __('The calendar server refused the credentials. For iCloud use an app-specific password.'),
            $e->status === 404 => __('This event is no longer on the calendar server.'),
            default => __('The calendar server is not answering right now (:error).', ['error' => $e->getMessage()]),
        };
    }
}
