<?php

declare(strict_types=1);

namespace App\Models;

/** One line of a card's checklist. */
final class ChecklistItem extends Model
{
    protected static string $table = 'checklist_items';

    /** @return self[] in order */
    public static function forCard(int $cardId): array
    {
        return self::where('card_id = ?', [$cardId], 'position ASC, id ASC');
    }
}
