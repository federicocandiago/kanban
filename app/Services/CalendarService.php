<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Everything between the calendar pages and the CalDAV server.
 *
 * Reading: the month is drawn from the local cache, then sync() asks the server — one PROPFIND
 * for all the sub-calendars (their ctag says which ones changed), then one REPORT per changed
 * sub-calendar for that month only. At most every CALENDAR_SYNC_SECONDS, and never while the
 * server has asked to wait (Retry-After).
 *
 * Writing: always on the server first, on the freshest copy of the event (GET, then PUT with
 * If-Match: a change made meanwhile on another device is never overwritten), then in the cache.
 */
final class CalendarService
{
    private DateTimeZone $tz;

    public function __construct(private readonly CalendarAccount $account, private readonly User $user)
    {
        $this->tz = $user->timezone();
    }

    /**
     * Checks the credentials and saves the account with its sub-calendars: a new one, or
     * $account with new credentials (its cache is kept: sync() fetches what changed).
     */
    public static function connect(User $user, string $name, string $provider, string $url, string $username, string $password, ?CalendarAccount $account = null): CalendarAccount
    {
        $dav = new CalDav($url, $username, $password);
        $found = $dav->discover();

        $data = [
            'user_id' => $user->id, 'name' => $name, 'provider' => $provider, 'server_url' => $url, 'username' => $username,
            'secret' => \App\Core\Crypt::encrypt($password), 'home_url' => $found['home'],
            'display_name' => $found['name'], 'attachments' => (int) $dav->supportsAttachments($found['home']),
            'synced_at' => null, 'retry_at' => null,
        ];
        if ($account) {
            $account->update($data);
        } else {
            $account = CalendarAccount::create($data + ['all_day_default' => (int) filter_var(env('CALENDAR_NEW_ALL_DAY', false), FILTER_VALIDATE_BOOL)]);
        }
        (new self($account, $user))->refreshCalendars($dav);

        return $account;
    }

    /**
     * Brings the month ("2026-10") up to date. Returns false when nothing was asked to the
     * server (too soon) or nothing changed. Throws CalDavException (throttling included).
     */
    public function sync(string $month, bool $force = false): bool
    {
        if ($this->account->isThrottled()) {
            throw new CalDavException('The calendar server asked to wait.', 503, max(1, strtotime((string) $this->account->retry_at) - time()));
        }
        $recent = $this->account->synced_at && strtotime((string) $this->account->synced_at) > time() - (int) env('CALENDAR_SYNC_SECONDS', 60);
        $calendars = $this->account->calendars();
        $missing = array_filter($calendars, static fn (Calendar $c): bool => $c->monthCtag($month) === null);
        if (!$force && $recent && !$missing) {
            return false;
        }

        $dav = $this->account->client();
        try {
            $calendars = $this->refreshCalendars($dav);
            [$from, $to] = self::monthRange($month, $this->tz);
            $changed = false;
            foreach ($calendars as $calendar) {
                if ($calendar->monthCtag($month) === (string) $calendar->ctag && $calendar->ctag !== '') {
                    continue;
                }
                $this->store($calendar, $dav->events((string) $calendar->url, $from, $to), $from, $to);
                $calendar->markMonth($month, (string) $calendar->ctag);
                $changed = true;
            }
            $this->account->update(['synced_at' => date('Y-m-d H:i:s')]);

            return $changed;
        } catch (CalDavException $e) {
            if ($e->isThrottled()) {
                $this->account->update(['retry_at' => date('Y-m-d H:i:s', time() + ($e->retryAfter ?? 120))]);
            }
            throw $e;
        }
    }

    /** First and last moment of a month in $tz. @return DateTimeImmutable[] */
    public static function monthRange(string $month, DateTimeZone $tz): array
    {
        $from = DateTimeImmutable::createFromFormat('!Y-m', $month, $tz) ?: new DateTimeImmutable('first day of this month 00:00', $tz);

        return [$from, $from->modify('+1 month')];
    }

    /** A new single event; whole days: $end is the day after the last one. */
    public function create(Calendar $calendar, string $title, DateTimeImmutable $start, DateTimeImmutable $end, bool $allDay, string $description = ''): Event
    {
        $uid = uuid();
        $event = ['name' => 'VEVENT', 'props' => [
            ['UID', [], $uid],
            ['DTSTAMP', [], gmdate('Ymd\THis\Z')],
            ['CREATED', [], gmdate('Ymd\THis\Z')],
        ], 'children' => []];
        $event = $this->dates($event, $start, $end, $allDay);
        $event = ICal::setText($event, 'SUMMARY', $title);
        if ($description !== '') {
            $event = ICal::setText($event, 'DESCRIPTION', $description);
        }
        $ics = ICal::serialize(['name' => 'VCALENDAR', 'props' => [
            ['VERSION', [], '2.0'], ['PRODID', [], '-//' . env('APP_NAME', 'Kalban') . '//EN'], ['CALSCALE', [], 'GREGORIAN'],
        ], 'children' => [$event]]);

        $url = rtrim((string) $calendar->url, '/') . '/' . $uid . '.ics';
        $dav = $this->account->client();
        $etag = $dav->put($url, $ics, null);
        $this->log($uid, 'created', null, (string) $calendar->name);

        return $this->reload($dav, $calendar, $url, $etag !== '' ? ['ics' => $ics, 'etag' => $etag] : null);
    }

    /**
     * Changes title, description, dates and/or sub-calendar of a single event.
     * $changes: title, description, start (DateTimeImmutable), end, all_day, calendar (Calendar)
     */
    public function update(Event $event, array $changes): Event
    {
        // what is already so costs nothing: no request at all when nothing changes
        if (array_key_exists('title', $changes) && $changes['title'] === (string) $event->summary) {
            unset($changes['title']);
        }
        if (array_key_exists('description', $changes) && trim((string) $changes['description']) === trim((string) $event->description)) {
            unset($changes['description']);
        }
        $calendar = Calendar::find((int) $event->calendar_id);
        $target = $changes['calendar'] ?? null;
        $moving = $target && (int) $target->id !== (int) $calendar->id;
        $content = array_key_exists('title', $changes) || array_key_exists('description', $changes) || isset($changes['start'], $changes['end']);
        if (!$content && !$moving) {
            return $event;
        }

        $dav = $this->account->client();
        $url = (string) $event->url;
        $written = null; // the object as just written, when the server confirmed it with its ETag
        $etag = null;
        if ($content) {
            $current = $dav->get($url);
            $tree = ICal::parse($current['ics']);
            $index = $this->master($tree);
            $vevent = $tree['children'][$index];

            $old = $this->describe($vevent);
            if (array_key_exists('title', $changes)) {
                $vevent = ICal::setText($vevent, 'SUMMARY', $changes['title']);
            }
            if (array_key_exists('description', $changes)) {
                $vevent = ICal::setText($vevent, 'DESCRIPTION', $changes['description']);
            }
            if (isset($changes['start'], $changes['end'])) {
                $vevent = $this->dates($vevent, $changes['start'], $changes['end'], (bool) $changes['all_day']);
            }
            $sequence = (int) (ICal::text($vevent, 'SEQUENCE') ?? 0);
            $vevent = ICal::set($vevent, 'SEQUENCE', (string) ($sequence + 1));
            $vevent = ICal::set($vevent, 'DTSTAMP', gmdate('Ymd\THis\Z'));
            $vevent = ICal::set($vevent, 'LAST-MODIFIED', gmdate('Ymd\THis\Z'));
            $tree['children'][$index] = $vevent;
            $ics = ICal::serialize($tree);

            $etag = $dav->put($url, $ics, $current['etag'] ?: (string) $event->etag);
            $written = $etag !== '' ? ['ics' => $ics, 'etag' => $etag] : null;

            foreach ($this->describe($vevent) as $field => $value) {
                if ($value !== $old[$field]) {
                    $this->log((string) $event->uid, $field, $old[$field], $value);
                }
            }
        }

        if ($moving) {
            // to another sub-calendar, as a whole (attachments included); a move doesn't change the content,
            // so without a change just made there is no ETag to hold it to
            $destination = rtrim((string) $target->url, '/') . '/' . basename($url);
            $dav->move($url, $destination, $etag ?: null);
            Database::connection()->query('DELETE FROM calendar_events WHERE calendar_id = ? AND url_hash = ?', [$calendar->id, sha1($url)]);
            $this->log((string) $event->uid, 'calendar', (string) $calendar->name, (string) $target->name);
            [$url, $calendar, $written] = [$destination, $target, null]; // the ETag may change with the address: read it again
        }

        return $this->reload($dav, $calendar, $url, $written);
    }

    /** Drag & drop to another day: same time of day (or whole days), same length. */
    public function moveTo(Event $event, string $day): Event
    {
        $start = $event->start($this->tz);
        $days = (int) (new DateTimeImmutable((string) $event->start_day))->diff(new DateTimeImmutable($day))->format('%r%a');
        $length = $event->end($this->tz)->getTimestamp() - $start->getTimestamp();
        $newStart = $start->modify(($days >= 0 ? '+' : '') . $days . ' days');

        return $this->update($event, [
            'start' => $newStart,
            'end' => $event->all_day ? $event->end($this->tz)->modify(($days >= 0 ? '+' : '') . $days . ' days') : $newStart->modify("+{$length} seconds"),
            'all_day' => (bool) $event->all_day,
        ]);
    }

    public function delete(Event $event): void
    {
        $this->account->client()->delete((string) $event->url, (string) $event->etag ?: null);
        Database::connection()->query('DELETE FROM calendar_events WHERE calendar_id = ? AND url_hash = ?', [$event->calendar_id, sha1((string) $event->url)]);
    }

    public function attach(Event $event, string $name, string $mime, string $path): Event
    {
        $dav = $this->account->client();
        $current = $dav->get((string) $event->url);
        $dav->attach((string) $event->url, $current['etag'], $name, $mime, (string) file_get_contents($path));
        $this->log((string) $event->uid, 'attachment_added', null, $name);

        return $this->reload($dav, Calendar::find((int) $event->calendar_id), (string) $event->url);
    }

    public function detach(Event $event, string $managedId): Event
    {
        $dav = $this->account->client();
        $current = $dav->get((string) $event->url);
        $name = '';
        foreach ($event->attachments() as $file) {
            $name = $file['id'] === $managedId ? $file['name'] : $name;
        }
        // RFC 8607 also allows removing it by saving the event without its ATTACH line: iCloud only honours this way
        $tree = ICal::parse($current['ics']);
        $index = $this->master($tree);
        $tree['children'][$index]['props'] = array_values(array_filter(
            $tree['children'][$index]['props'],
            static fn (array $p): bool => !($p[0] === 'ATTACH' && ($p[1]['MANAGED-ID'] ?? null) === $managedId)
        ));
        $dav->put((string) $event->url, ICal::serialize($tree), $current['etag']);
        $this->log((string) $event->uid, 'attachment_removed', $name, null);

        return $this->reload($dav, Calendar::find((int) $event->calendar_id), (string) $event->url);
    }

    /** @return array{body: string, type: string} */
    public function download(string $url): array
    {
        return $this->account->client()->download($url);
    }

    /** Updates the list of sub-calendars (and their ctags) from the server. @return Calendar[] */
    private function refreshCalendars(CalDav $dav): array
    {
        $db = Database::connection();
        $seen = [];
        foreach ($dav->calendars((string) $this->account->home_url) as $position => $remote) {
            $hash = sha1($remote['url']);
            $seen[] = $db->pdo()->quote($hash);
            $data = [
                'name' => mb_substr($remote['name'], 0, 190), 'color' => $remote['color'], 'ctag' => mb_substr($remote['ctag'], 0, 255),
                'writable' => (int) $remote['writable'], 'owner_name' => $remote['owner'], 'position' => $position,
            ];
            $existing = Calendar::first('account_id = ? AND url_hash = ?', [$this->account->id, $hash]);
            if ($existing) {
                $existing->update($data);
            } else {
                Calendar::create(['account_id' => $this->account->id, 'url' => $remote['url'], 'url_hash' => $hash, ...$data]);
            }
        }
        $db->query('DELETE FROM calendars WHERE account_id = ?' . ($seen ? ' AND url_hash NOT IN (' . implode(',', $seen) . ')' : ''), [$this->account->id]);

        return $this->account->calendars();
    }

    /**
     * Replaces the cached occurrences of $calendar in [from, to) with what the server sent.
     * Single events already known that changed on the server get an "outside the app" log line.
     */
    private function store(Calendar $calendar, array $objects, DateTimeImmutable $from, DateTimeImmutable $to): void
    {
        $db = Database::connection();
        $lastDay = $to->modify('-1 day')->format('Y-m-d');
        $cached = [];
        foreach ($db->select(
            'SELECT * FROM calendar_events WHERE calendar_id = ? AND start_day <= ? AND end_day >= ?',
            [$calendar->id, $lastDay, $from->format('Y-m-d')]
        ) as $row) {
            $cached[$row['url_hash'] . '|' . $row['recurrence_id']] = $row;
        }

        $keep = [];
        foreach ($objects as $object) {
            foreach ($this->occurrences($object['ics'], $object['url'], $object['etag']) as $row) {
                $key = $row['url_hash'] . '|' . $row['recurrence_id'];
                $keep[$key] = true;
                if (isset($cached[$key]) && !$row['recurring']) {
                    $this->logOutside($cached[$key], $row);
                }
                $this->upsert((int) $calendar->id, $row);
            }
        }
        foreach ($cached as $key => $row) {
            if (!isset($keep[$key])) {
                $db->query('DELETE FROM calendar_events WHERE id = ?', [$row['id']]);
            }
        }
    }

    /**
     * Stores the occurrences of one object after a change: $known is what was just written with the ETag
     * the server gave it (stored as it is, so no need to ask), otherwise the object is fetched again.
     * @param array{ics: string, etag: string}|null $known
     */
    private function reload(CalDav $dav, Calendar $calendar, string $url, ?array $known = null): Event
    {
        $current = $known ?? $dav->get($url);
        $rows = $this->occurrences($current['ics'], $url, $current['etag']);
        $db = Database::connection();
        foreach ($rows as $row) {
            $this->upsert((int) $calendar->id, $row); // in place: the id stays, the open window keeps working
        }
        $kept = array_map(static fn (array $r): string => $db->pdo()->quote($r['recurrence_id']), $rows) ?: ["''"];
        $db->query('DELETE FROM calendar_events WHERE calendar_id = ? AND url_hash = ? AND recurrence_id NOT IN (' . implode(',', $kept) . ')', [$calendar->id, sha1($url)]);
        $id = (int) Database::connection()->scalar('SELECT id FROM calendar_events WHERE calendar_id = ? AND url_hash = ? ORDER BY id LIMIT 1', [$calendar->id, sha1($url)]);

        return Event::inAccount($id, $this->account);
    }

    /** Cache rows for the VEVENTs of one object. */
    private function occurrences(string $ics, string $url, string $etag): array
    {
        $tree = ICal::parse($ics);
        $vevents = ICal::events($tree);
        $recurring = count($vevents) > 1 || array_filter($vevents, static fn (array $v): bool => ICal::prop($v, 'RRULE') || ICal::prop($v, 'RECURRENCE-ID'));
        $rows = [];
        foreach ($vevents as $vevent) {
            $start = ICal::date(ICal::prop($vevent, 'DTSTART'), $this->tz);
            if ($start === null) {
                continue;
            }
            [$startAt, $allDay] = $start;
            $end = ICal::date(ICal::prop($vevent, 'DTEND'), $this->tz)[0]
                ?? ($allDay ? $startAt->modify('+1 day') : $startAt->add(self::duration(ICal::text($vevent, 'DURATION'))));
            if ($end <= $startAt) {
                $end = $allDay ? $startAt->modify('+1 day') : $startAt;
            }
            // the last day it covers: an end at midnight (or the exclusive end of whole days) belongs to the day before
            $lastDay = $end > $startAt && $end->format('H:i:s') === '00:00:00' ? $end->modify('-1 second') : $end;
            $organizer = ICal::prop($vevent, 'ORGANIZER');
            $organizerName = $organizer ? ($organizer[1]['CN'] ?? null) : null;
            $mine = $organizer && stripos($organizer[2], (string) $this->account->username) !== false;

            $rows[] = [
                'url' => $url, 'url_hash' => sha1($url),
                'recurrence_id' => (string) (ICal::prop($vevent, 'RECURRENCE-ID')[2] ?? ''),
                'uid' => (string) ICal::text($vevent, 'UID'),
                'etag' => $etag,
                'summary' => mb_substr((string) ICal::text($vevent, 'SUMMARY'), 0, 500),
                'description' => ICal::text($vevent, 'DESCRIPTION'),
                'organizer' => $mine || !$organizerName ? null : mb_substr($organizerName, 0, 190),
                'starts_at' => $allDay ? $startAt->format('Y-m-d 00:00:00') : $startAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'ends_at' => $allDay ? $end->format('Y-m-d 00:00:00') : $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'start_day' => $startAt->format('Y-m-d'),
                'end_day' => $lastDay->format('Y-m-d'),
                'all_day' => (int) $allDay,
                'recurring' => (int) (bool) $recurring,
                'attachments' => json_encode(self::attachments($vevent), JSON_UNESCAPED_UNICODE),
            ];
        }

        return $rows;
    }

    private function upsert(int $calendarId, array $row): void
    {
        $columns = ['calendar_id' => $calendarId, ...$row, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        $names = array_keys($columns);
        $updates = implode(', ', array_map(static fn (string $c): string => "`{$c}` = VALUES(`{$c}`)", array_diff($names, ['created_at'])));
        Database::connection()->query(
            'INSERT INTO calendar_events (`' . implode('`, `', $names) . '`) VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ")
             ON DUPLICATE KEY UPDATE {$updates}",
            array_values($columns)
        );
    }

    /** The event's editable fields as the log writes them (dates in the user's time zone). */
    private function describe(array $vevent): array
    {
        $start = ICal::date(ICal::prop($vevent, 'DTSTART'), $this->tz);
        $end = ICal::date(ICal::prop($vevent, 'DTEND'), $this->tz);
        if ($end && $end[1]) {
            $end[0] = $end[0]->modify('-1 day'); // whole days: the last day, as the user sees it
        }
        $format = static fn (?array $d): ?string => $d ? $d[0]->format($d[1] ? 'Y-m-d' : 'Y-m-d H:i') : null;

        return [
            'title' => ICal::text($vevent, 'SUMMARY'),
            'description' => ICal::text($vevent, 'DESCRIPTION') ?: null,
            'start' => $format($start),
            'end' => $format($end),
        ];
    }

    private function logOutside(array $before, array $after): void
    {
        $tz = $this->tz;
        $local = static fn (array $r, string $col, bool $end = false): string => $r['all_day']
            ? date('Y-m-d', strtotime(substr($r[$col], 0, 10) . ($end ? ' -1 day' : '')))
            : (new DateTimeImmutable($r[$col], new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d H:i');
        $pairs = [
            'title' => [$before['summary'], $after['summary']],
            'description' => [$before['description'] ?: null, $after['description'] ?: null],
            'start' => [$local($before, 'starts_at'), $local($after, 'starts_at')],
            'end' => [$local($before, 'ends_at', true), $local($after, 'ends_at', true)],
        ];
        foreach ($pairs as $field => [$old, $new]) {
            if ((string) $old !== (string) $new) {
                EventActivity::create(['account_id' => $this->account->id, 'uid' => $after['uid'], 'user_name' => '', 'field' => $field, 'old_value' => $old, 'new_value' => $new]);
            }
        }
    }

    private function log(string $uid, string $field, ?string $old, ?string $new): void
    {
        EventActivity::create([
            'account_id' => $this->account->id, 'uid' => $uid, 'user_id' => $this->user->id, 'user_name' => $this->user->name,
            'field' => $field, 'old_value' => $old, 'new_value' => $new,
        ]);
    }

    /** Index of the VEVENT to edit (the one without RECURRENCE-ID). */
    private function master(array $tree): int
    {
        foreach ($tree['children'] as $i => $child) {
            if ($child['name'] === 'VEVENT' && !ICal::prop($child, 'RECURRENCE-ID')) {
                return $i;
            }
        }
        throw new CalDavException('This event cannot be edited here.');
    }

    private function dates(array $vevent, DateTimeImmutable $start, DateTimeImmutable $end, bool $allDay): array
    {
        [$params, $value] = ICal::formatDate($start, $allDay);
        $vevent = ICal::set($vevent, 'DTSTART', $value, $params);
        [$params, $value] = ICal::formatDate($end, $allDay);
        $vevent = ICal::set($vevent, 'DTEND', $value, $params);

        return ICal::set($vevent, 'DURATION', null);
    }

    private static function duration(?string $value): \DateInterval
    {
        try {
            return new \DateInterval(ltrim((string) $value, '+') ?: 'PT0S');
        } catch (\Exception) {
            return new \DateInterval('PT0S');
        }
    }

    /** RFC 8607 attachments (ATTACH;MANAGED-ID=…;FILENAME=…;SIZE=…;FMTTYPE=…:url). */
    private static function attachments(array $vevent): array
    {
        $files = [];
        foreach (ICal::props($vevent, 'ATTACH') as [, $params, $value]) {
            if (isset($params['MANAGED-ID']) && preg_match('#^https?://#', $value)) {
                $files[] = [
                    'id' => $params['MANAGED-ID'],
                    'name' => $params['FILENAME'] ?? basename(parse_url($value, PHP_URL_PATH) ?: 'file'),
                    'size' => (int) ($params['SIZE'] ?? 0),
                    'type' => $params['FMTTYPE'] ?? 'application/octet-stream',
                    'url' => $value,
                ];
            }
        }

        return $files;
    }
}
