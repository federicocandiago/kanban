<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Board;
use App\Models\Card;
use App\Models\Event;
use App\Models\User;

/**
 * Global search, in what the user can see: boards they are a member of (archived ones too),
 * their lists, cards (name, description, label names) and attachments, and the events of
 * their calendars already in the local cache (the months they have opened: the remote calendar is not
 * searched, that would mean a slow request to iCloud for every key pressed).
 */
final class SearchService
{
    public const MIN_LENGTH = 2;

    public function __construct(private readonly User $user)
    {
    }

    /**
     * @return array{boards: Board[], lists: array[], cards: Card[], attachments: array[], events: Event[], boardsById: array<int, Board>}
     *         $limit results per group
     */
    public function search(string $query, int $limit): array
    {
        $query = trim($query);
        $empty = ['boards' => [], 'lists' => [], 'cards' => [], 'attachments' => [], 'events' => []];
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return $empty;
        }
        $like = '%' . addcslashes(mb_strtolower($query), '%_\\') . '%';
        $db = Database::connection();

        $boards = [];
        foreach ([...$this->user->boards(), ...$this->user->boards(true)] as $board) {
            $boards[(int) $board->id] = $board;
        }
        if (!$boards) {
            $cardsAndMore = $empty;
        } else {
            $in = implode(',', array_keys($boards));
            $lists = $db->select(
                "SELECT id, board_id, name, archived_at FROM lists WHERE board_id IN ({$in}) AND LOWER(name) LIKE ?
                 ORDER BY archived_at IS NOT NULL, name LIMIT {$limit}",
                [$like]
            );
            $cards = Card::withDetails(
                "c.board_id IN ({$in}) AND (LOWER(c.title) LIKE ? OR LOWER(COALESCE(c.description, '')) LIKE ?
                 OR EXISTS (SELECT 1 FROM card_labels cl JOIN labels lb ON lb.id = cl.label_id WHERE cl.card_id = c.id AND LOWER(lb.name) LIKE ?))",
                [$like, $like, $like],
                "c.archived_at IS NOT NULL, c.updated_at DESC LIMIT {$limit}" // ORDER BY …, then the limit
            );
            $attachments = $db->select(
                "SELECT a.id, a.name, a.size, a.mime, a.card_id, c.title AS card_title, c.board_id
                 FROM attachments a JOIN cards c ON c.id = a.card_id
                 WHERE c.board_id IN ({$in}) AND LOWER(a.name) LIKE ? ORDER BY a.id DESC LIMIT {$limit}",
                [$like]
            );
            $cardsAndMore = ['lists' => $lists, 'cards' => $cards, 'attachments' => $attachments];
        }

        $events = [];
        $ids = array_column($db->select(
            "SELECT MIN(e.id) AS id FROM calendar_events e JOIN calendars c ON c.id = e.calendar_id
             JOIN calendar_accounts a ON a.id = c.account_id
             WHERE a.user_id = ? AND (LOWER(e.summary) LIKE ? OR LOWER(COALESCE(e.description, '')) LIKE ?)
             GROUP BY e.calendar_id, e.url_hash ORDER BY MIN(e.starts_at) DESC LIMIT {$limit}",
            [$this->user->id, $like, $like]
        ), 'id');
        foreach ($ids as $id) {
            if ($event = Event::ofUser((int) $id, $this->user)) {
                $events[] = $event;
            }
        }

        $matchingBoards = array_values(array_filter($boards, static fn (Board $b): bool => str_contains(mb_strtolower((string) $b->name), mb_strtolower($query))));

        return [
            'boards' => array_slice($matchingBoards, 0, $limit),
            'lists' => $cardsAndMore['lists'] ?? [],
            'cards' => $cardsAndMore['cards'] ?? [],
            'attachments' => $cardsAndMore['attachments'] ?? [],
            'events' => $events,
            'boardsById' => $boards,
        ];
    }

    /** The text with the query marked (<mark>), escaped. */
    public static function highlight(string $text, string $query): string
    {
        $query = trim($query);
        if ($query === '') {
            return e($text);
        }
        $parts = preg_split('/(' . preg_quote($query, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $out = '';
        foreach ($parts as $i => $part) {
            $out .= $i % 2 ? '<mark>' . e($part) . '</mark>' : e($part);
        }

        return $out;
    }

    /** A short piece of $text around the first match (for descriptions). */
    public static function excerpt(?string $text, string $query, int $width = 70): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $at = mb_stripos($text, trim($query));
        if ($at === false) {
            return null;
        }
        $start = max(0, $at - (int) ($width / 3));

        return ($start > 0 ? '…' : '') . mb_substr($text, $start, $width) . (mb_strlen($text) > $start + $width ? '…' : '');
    }
}
