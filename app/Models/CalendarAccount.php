<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Crypt;
use App\Services\CalDav;

/** A CalDAV account of the user, with the name they gave it ("Personale", "Lavoro"): a user can have several. */
final class CalendarAccount extends Model
{
    protected static string $table = 'calendar_accounts';

    /** @return self[] by name */
    public static function forUser(User $user): array
    {
        return self::where('user_id = ?', [$user->id], 'name ASC, id ASC');
    }

    public static function ofUser(int $id, User $user): ?self
    {
        return self::first('id = ? AND user_id = ?', [$id, $user->id]);
    }

    public function url(string $suffix = ''): string
    {
        return url('/calendars/' . $this->id . $suffix);
    }

    public function client(): CalDav
    {
        return new CalDav((string) $this->server_url, (string) $this->username, Crypt::decrypt((string) $this->secret));
    }

    /** "iCloud – name@example.com" */
    public function label(): string
    {
        return $this->service() . ' – ' . $this->username;
    }

    /** "iCloud", or the host of the CalDAV server */
    public function service(): string
    {
        return $this->provider === 'icloud' ? 'iCloud' : (parse_url((string) $this->server_url, PHP_URL_HOST) ?: 'CalDAV');
    }

    /** @return Calendar[] */
    public function calendars(): array
    {
        return Calendar::where('account_id = ?', [$this->id], 'position ASC, id ASC');
    }

    /** Where new events go: the one chosen in Settings, or the first writable one. */
    public function defaultCalendar(): ?Calendar
    {
        $writable = array_values(array_filter($this->calendars(), static fn (Calendar $c): bool => (bool) $c->writable));
        foreach ($writable as $calendar) {
            if ((int) $calendar->id === (int) $this->default_calendar_id) {
                return $calendar;
            }
        }

        return $writable[0] ?? null;
    }

    public function isThrottled(): bool
    {
        return $this->retry_at !== null && strtotime((string) $this->retry_at) > time();
    }
}
