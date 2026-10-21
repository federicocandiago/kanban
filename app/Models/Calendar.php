<?php

declare(strict_types=1);

namespace App\Models;

/** A sub-calendar of the account ("Lavoro", "Casa"…): shown on events the way labels are on cards. */
final class Calendar extends Model
{
    protected static string $table = 'calendars';

    public function color(): string
    {
        return $this->color ?: '#838c91';
    }

    public function monthCtag(string $month): ?string
    {
        $ctag = static::db()->scalar('SELECT ctag FROM calendar_months WHERE calendar_id = ? AND month = ?', [$this->id, $month]);

        return $ctag === false ? null : (string) $ctag;
    }

    public function markMonth(string $month, string $ctag): void
    {
        static::db()->query(
            'INSERT INTO calendar_months (calendar_id, month, ctag, fetched_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE ctag = VALUES(ctag), fetched_at = NOW()',
            [$this->id, $month, $ctag]
        );
    }
}
