<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;

final class Card extends Model
{
    protected static string $table = 'cards';

    /**
     * Cards with what their tile shows: labels and number of attachments.
     * @return Card[]
     */
    public static function withDetails(string $where, array $params = [], string $order = 'c.position ASC, c.id ASC'): array
    {
        $rows = static::db()->select(
            "SELECT c.*, l.name AS list_name, (SELECT COUNT(*) FROM attachments a WHERE a.card_id = c.id) AS attachment_count,
                    (SELECT COUNT(*) FROM card_activity n WHERE n.card_id = c.id AND n.field = 'note') AS note_count,
                    (SELECT COUNT(*) FROM checklist_items k WHERE k.card_id = c.id) AS check_total,
                    (SELECT COUNT(*) FROM checklist_items k WHERE k.card_id = c.id AND k.done = 1) AS check_done
             FROM cards c JOIN lists l ON l.id = c.list_id WHERE {$where} ORDER BY {$order}",
            $params
        );
        $labels = Label::forCards(array_column($rows, 'id'));

        return array_map(static fn (array $row) => new self($row + ['labels' => $labels[(int) $row['id']] ?? []]), $rows);
    }

    /** One card with its tile details (after an update, to redraw the tile). */
    public static function detailed(int $id): ?self
    {
        return self::withDetails('c.id = ?', [$id])[0] ?? null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** @return Label[] */
    public function labels(): array
    {
        return $this->attributes['labels'] ?? Label::forCards([(int) $this->id])[(int) $this->id] ?? [];
    }

    /** @return int[] */
    public function labelIds(): array
    {
        return array_map(static fn (Label $l): int => (int) $l->id, $this->labels());
    }

    /** @return Attachment[] */
    public function attachments(): array
    {
        return Attachment::where('card_id = ?', [$this->id], 'id DESC');
    }

    /** @return Activity[] newest first */
    public function activity(): array
    {
        return Activity::where('card_id = ?', [$this->id], 'id DESC');
    }

    public function log(User $user, string $field, ?string $old = null, ?string $new = null): void
    {
        Activity::create([
            'card_id' => $this->id, 'user_id' => $user->id, 'user_name' => $user->name . (Auth::viaApi() ? ' (API)' : ''),
            'field' => $field, 'old_value' => $old, 'new_value' => $new,
        ]);
    }

    /** 'overdue' when the end date has passed, 'soon' when it is today or tomorrow. */
    public function dueState(): ?string
    {
        if (!$this->end_date) {
            return null;
        }
        $days = (strtotime((string) $this->end_date) - strtotime(date('Y-m-d'))) / 86400;

        return match (true) {
            $days < 0 => 'overdue',
            $days <= 1 => 'soon',
            default => null,
        };
    }
}
