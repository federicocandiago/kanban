<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use DateTimeZone;

/**
 * One occurrence of a calendar event, as cached from the server. starts_at / ends_at are UTC;
 * start_day / end_day (inclusive) are days in the user's time zone. Loaded with withCalendar()
 * it also carries its sub-calendar: calendar_name, calendar_color, writable, owner_name, account_id.
 */
final class Event extends Model
{
    protected static string $table = 'calendar_events';

    /** @return Event[] events of the account touching [from, to], by start */
    public static function between(CalendarAccount $account, string $from, string $to): array
    {
        return self::withCalendar('c.account_id = ? AND e.start_day <= ? AND e.end_day >= ?', [$account->id, $to, $from]);
    }

    public static function inAccount(int $id, CalendarAccount $account): ?self
    {
        return self::withCalendar('e.id = ? AND c.account_id = ?', [$id, $account->id])[0] ?? null;
    }

    /** An event in any of the user's accounts. */
    public static function ofUser(int $id, User $user): ?self
    {
        return self::withCalendar('e.id = ? AND c.account_id IN (SELECT id FROM calendar_accounts WHERE user_id = ?)', [$id, $user->id])[0] ?? null;
    }

    /** @return Event[] */
    private static function withCalendar(string $where, array $params): array
    {
        $rows = static::db()->select(
            "SELECT e.*, c.name AS calendar_name, c.color AS calendar_color, c.writable, c.owner_name, c.url AS calendar_url, c.account_id
             FROM calendar_events e JOIN calendars c ON c.id = e.calendar_id
             WHERE {$where} ORDER BY e.all_day ASC, e.starts_at ASC, e.id ASC",
            $params
        );

        return array_map(static fn (array $row) => new self($row), $rows);
    }

    /** Only single events of writable calendars can be changed from here. */
    public function isEditable(): bool
    {
        return (bool) $this->writable && !$this->recurring;
    }

    public function spansDays(): bool
    {
        return $this->start_day !== $this->end_day;
    }

    public function start(DateTimeZone $tz): DateTimeImmutable
    {
        return $this->moment((string) $this->starts_at, $tz);
    }

    public function end(DateTimeZone $tz): DateTimeImmutable
    {
        return $this->moment((string) $this->ends_at, $tz);
    }

    /** "10:00 – 11:30", or '' for whole-day events */
    public function timeRange(DateTimeZone $tz): string
    {
        return $this->all_day ? '' : $this->start($tz)->format('H:i') . ' – ' . $this->end($tz)->format('H:i');
    }

    /** Who shared it: the owner of a shared calendar, or the organizer of an invitation. */
    public function sharedBy(): ?string
    {
        return $this->owner_name ?: ($this->organizer ?: null);
    }

    /** @return array<int, array{id: string, name: string, size: int, type: string, url: string}> managed attachments */
    public function attachments(): array
    {
        return json_decode((string) $this->attachments, true) ?: [];
    }

    /** @return EventActivity[] newest first */
    public function activity(int $accountId): array
    {
        return EventActivity::where('account_id = ? AND uid = ?', [$accountId, $this->uid], 'id DESC');
    }

    private function moment(string $utc, DateTimeZone $tz): DateTimeImmutable
    {
        return $this->all_day
            ? new DateTimeImmutable($utc, $tz)    // whole days are stored as the day itself
            : (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz);
    }
}
