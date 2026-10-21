<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Palette;

final class Label extends Model
{
    protected static string $table = 'labels';

    /** Starting set for every new user (same as migration 2026_10_21_000002). Colours: App\Services\Palette. */
    public const DEFAULTS = ['green', 'yellow', 'orange', 'red', 'purple', 'blue'];

    public static function seed(int $userId): void
    {
        foreach (self::DEFAULTS as $color) {
            self::create(['user_id' => $userId, 'name' => '', 'color' => $color]);
        }
    }

    /** Name, or the colour name for a label without one. */
    public function display(): string
    {
        return $this->name !== '' ? (string) $this->name : (Palette::names()[$this->color] ?? (string) $this->color);
    }

    /** How the change log stores it: the name, or "color:{key}" (translated when the log is read). */
    public function logValue(): string
    {
        return $this->name !== '' ? (string) $this->name : 'color:' . $this->color;
    }

    /** @return array<int, Label[]> labels of the given cards, by card id */
    public static function forCards(array $cardIds): array
    {
        if (!$cardIds) {
            return [];
        }
        $rows = static::db()->select(
            'SELECT l.*, cl.card_id FROM labels l JOIN card_labels cl ON cl.label_id = l.id
             WHERE cl.card_id IN (' . implode(',', array_map('intval', $cardIds)) . ') ORDER BY l.id',
        );
        $byCard = [];
        foreach ($rows as $row) {
            $byCard[(int) $row['card_id']][] = new self($row);
        }

        return $byCard;
    }
}
