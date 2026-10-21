<?php

declare(strict_types=1);

namespace App\Models;

/** Change log of a calendar event; user_name '' means it was changed outside the app. */
final class EventActivity extends Activity
{
    protected static string $table = 'event_activity';

    public static function fields(): array
    {
        return [
            'created' => __('Created'),
            'title' => __('Name'),
            'description' => __('Description'),
            'start' => __('Start'),
            'end' => __('End'),
            'calendar' => __('Calendar'),
            'attachment_added' => __('Attachment added'),
            'attachment_removed' => __('Attachment removed'),
        ];
    }

    public function fieldName(): string
    {
        return self::fields()[$this->field] ?? (string) $this->field;
    }

    /** Dates are stored as "Y-m-d" (whole day) or "Y-m-d H:i" in the user's time zone. */
    public function value(?string $value): string
    {
        if ($value !== null && preg_match('/^(\d{4}-\d{2}-\d{2})(?: (\d{2}:\d{2}))?$/', $value, $m)) {
            return format_day($m[1]) . (isset($m[2]) ? ' ' . $m[2] : '');
        }

        return parent::value($value);
    }

    public function isOutside(): bool
    {
        return $this->user_name === '';
    }
}
