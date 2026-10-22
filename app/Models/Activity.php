<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Palette;

/** One line of a card's change log: who, when, field, old value → new value. */
class Activity extends Model
{
    protected static string $table = 'card_activity';

    public static function fields(): array
    {
        return [
            'created' => __('Created'),
            'title' => __('Name'),
            'description' => __('Description'),
            'start_date' => __('Start date'),
            'end_date' => __('End date'),
            'list' => __('List'),
            'label_added' => __('Label added'),
            'label_removed' => __('Label removed'),
            'attachment_added' => __('Attachment added'),
            'attachment_removed' => __('Attachment removed'),
            'check_added' => __('Checklist item added'),
            'check_done' => __('Checklist item done'),
            'check_undone' => __('Checklist item not done'),
            'check_renamed' => __('Checklist item renamed'),
            'check_removed' => __('Checklist item removed'),
            'archived' => __('Archived'),
            'restored' => __('Restored'),
        ];
    }

    public function fieldName(): string
    {
        return self::fields()[$this->field] ?? (string) $this->field;
    }

    /** Dates are stored as Y-m-d and shown in the reader's format; long texts are shortened. */
    public function value(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (in_array($this->field, ['start_date', 'end_date'], true)) {
            return format_day($value);
        }
        if (str_starts_with($value, 'color:')) {
            return Palette::names()[substr($value, 6)] ?? $value;
        }

        return mb_strlen($value) > 80 ? mb_substr($value, 0, 80) . '…' : $value;
    }
}
