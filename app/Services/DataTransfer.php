<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Models\Board;
use App\Models\User;
use DateTimeImmutable;

/**
 * A user's data as one JSON document, and back. The document names nobody: no ids, emails,
 * passwords, tokens or calendar credentials; people appear only as the names written in logs
 * and notes. So it can be imported into any account, also of another Kalban.
 *
 * Exported: profile preferences, the user's labels, the boards they own (archived too) with
 * their lists, cards, labels on cards, log, notes and, optionally, attachments (base64), and
 * the calendars' names and settings (to be connected again: no credentials).
 * Imported: always as new boards; labels with the same name and colour are reused.
 */
final class DataTransfer
{
    public const VERSION = 1;

    private Database $db;

    public function __construct(private readonly User $user)
    {
        $this->db = Database::connection();
    }

    public function export(bool $withAttachments): array
    {
        $labels = $this->db->select('SELECT id, name, color FROM labels WHERE user_id = ? ORDER BY id', [$this->user->id]);
        $labelKey = [];
        foreach ($labels as $i => $label) {
            $labelKey[(int) $label['id']] = 'l' . ($i + 1);
        }

        $boards = [];
        $owned = $this->db->select(
            'SELECT b.*, m.is_favorite FROM boards b JOIN board_members m ON m.board_id = b.id AND m.user_id = ? WHERE b.owner_id = ? ORDER BY b.id',
            [$this->user->id, $this->user->id]
        );
        $files = new AttachmentService();
        foreach ($owned as $board) {
            $lists = [];
            foreach ($this->db->select('SELECT * FROM lists WHERE board_id = ? ORDER BY position, id', [$board['id']]) as $list) {
                $cards = [];
                foreach ($this->db->select('SELECT * FROM cards WHERE list_id = ? ORDER BY position, id', [$list['id']]) as $card) {
                    $cardLabels = array_column($this->db->select('SELECT label_id FROM card_labels WHERE card_id = ?', [$card['id']]), 'label_id');
                    $attachments = [];
                    foreach ($this->db->select('SELECT * FROM attachments WHERE card_id = ? ORDER BY id', [$card['id']]) as $file) {
                        $entry = ['name' => $file['name'], 'mime' => $file['mime'], 'size' => (int) $file['size'], 'created_at' => $file['created_at']];
                        $path = $withAttachments ? $files->path((int) $board['id'], (string) $file['file']) : null;
                        if ($path) {
                            $entry['content'] = base64_encode((string) file_get_contents($path));
                        }
                        $attachments[] = $entry;
                    }
                    $cards[] = [
                        'title' => $card['title'], 'description' => $card['description'],
                        'start_date' => $card['start_date'], 'end_date' => $card['end_date'],
                        'archived_at' => $card['archived_at'], 'created_at' => $card['created_at'], 'updated_at' => $card['updated_at'],
                        // only the user's own labels can travel (other members' are theirs)
                        'labels' => array_values(array_filter(array_map(static fn ($id) => $labelKey[(int) $id] ?? null, $cardLabels))),
                        'attachments' => $attachments,
                        'log' => array_map(static fn (array $row): array => [
                            'by' => $row['user_name'], 'field' => $row['field'], 'old' => $row['old_value'], 'new' => $row['new_value'],
                            'at' => $row['created_at'], 'edited_at' => $row['updated_at'],
                        ], $this->db->select('SELECT * FROM card_activity WHERE card_id = ? ORDER BY id', [$card['id']])),
                    ];
                }
                $lists[] = ['name' => $list['name'], 'archived_at' => $list['archived_at'], 'created_at' => $list['created_at'], 'cards' => $cards];
            }
            $boards[] = [
                'name' => $board['name'], 'favorite' => (bool) $board['is_favorite'],
                'background' => [
                    'type' => $board['bg_type'], 'value' => $board['bg_value'], 'thumb' => $board['bg_thumb'], 'tint' => $board['bg_tint'],
                    'credit_name' => $board['bg_credit_name'], 'credit_url' => $board['bg_credit_url'],
                ],
                'archived_at' => $board['archived_at'], 'created_at' => $board['created_at'], 'lists' => $lists,
            ];
        }

        $calendars = array_map(static fn (array $row): array => [
            'name' => $row['name'], 'provider' => $row['provider'], 'server_url' => $row['provider'] === 'icloud' ? null : $row['server_url'],
            'new_events_all_day' => (bool) $row['all_day_default'],
        ], $this->db->select('SELECT name, provider, server_url, all_day_default FROM calendar_accounts WHERE user_id = ? ORDER BY name', [$this->user->id]));

        return [
            'kalban' => self::VERSION,
            'exported_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'attachments_included' => $withAttachments,
            'preferences' => [
                'locale' => $this->user->locale, 'theme' => $this->user->theme(), 'color' => $this->user->color(),
                'timezone' => $this->user->timezone()->getName(), 'event_minutes' => $this->user->eventMinutes(),
            ],
            'labels' => array_map(static fn (array $l, int $i): array => ['key' => 'l' . ($i + 1), 'name' => $l['name'], 'color' => $l['color']], $labels, array_keys($labels)),
            'boards' => $boards,
            'calendars' => $calendars,
        ];
    }

    /**
     * Creates what the document holds in this account. Returns what was created.
     * @return array{boards: int, cards: int, attachments: int, calendars: int}
     */
    public function import(array $doc): array
    {
        if (($doc['kalban'] ?? null) !== self::VERSION || !is_array($doc['boards'] ?? null)) {
            throw ValidationException::field('file', __('This is not a Kalban export (or it comes from a newer version).'));
        }
        $count = ['boards' => 0, 'cards' => 0, 'attachments' => 0, 'calendars' => 0];
        $files = new AttachmentService();
        $written = [];
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            // labels: reuse the same name + colour, otherwise create
            $labelIds = [];
            foreach ((array) ($doc['labels'] ?? []) as $label) {
                $name = mb_substr(trim((string) ($label['name'] ?? '')), 0, 40);
                $color = Palette::has((string) ($label['color'] ?? '')) ? (string) $label['color'] : 'green';
                $id = $this->db->scalar('SELECT id FROM labels WHERE user_id = ? AND name = ? AND color = ?', [$this->user->id, $name, $color]);
                $labelIds[(string) ($label['key'] ?? '')] = $id ? (int) $id
                    : $this->db->insert('labels', ['user_id' => $this->user->id, 'name' => $name, 'color' => $color, 'created_at' => $this->now(), 'updated_at' => $this->now()]);
            }

            $existing = array_map(static fn (Board $b): string => mb_strtolower((string) $b->name), [...$this->user->boards(), ...$this->user->boards(true)]);
            foreach ($doc['boards'] as $b) {
                $name = mb_substr(trim((string) ($b['name'] ?? '')), 0, 100) ?: __('Imported board');
                if (in_array(mb_strtolower($name), $existing, true)) {
                    $name = mb_substr($name, 0, 85) . ' ' . __('(imported)');
                }
                $bg = (array) ($b['background'] ?? []);
                $photo = ($bg['type'] ?? '') === 'photo' && is_string($bg['value'] ?? null) && str_starts_with($bg['value'], 'https://images.unsplash.com/');
                $background = $photo
                    ? ['bg_type' => 'photo', 'bg_value' => mb_substr($bg['value'], 0, 500), 'bg_thumb' => $this->url($bg['thumb'] ?? null),
                       'bg_tint' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($bg['tint'] ?? '')) ? strtolower($bg['tint']) : null,
                       'bg_credit_name' => mb_substr((string) ($bg['credit_name'] ?? ''), 0, 100) ?: null, 'bg_credit_url' => $this->url($bg['credit_url'] ?? null)]
                    : ['bg_type' => 'color', 'bg_value' => Palette::has((string) ($bg['value'] ?? '')) ? $bg['value'] : $this->user->color(),
                       'bg_thumb' => null, 'bg_tint' => null, 'bg_credit_name' => null, 'bg_credit_url' => null];
                $boardId = $this->db->insert('boards', ['uuid' => uuid(), 'owner_id' => $this->user->id, 'name' => $name, ...$background,
                    'archived_at' => $this->date($b['archived_at'] ?? null), 'created_at' => $this->date($b['created_at'] ?? null) ?? $this->now(), 'updated_at' => $this->now()]);
                $this->db->insert('board_members', ['board_id' => $boardId, 'user_id' => $this->user->id, 'role' => 'owner',
                    'is_favorite' => (int) !empty($b['favorite']), 'last_opened_at' => $this->now(), 'created_at' => $this->now()]);
                $count['boards']++;

                foreach (array_values((array) ($b['lists'] ?? [])) as $lp => $l) {
                    $listId = $this->db->insert('lists', ['board_id' => $boardId, 'name' => mb_substr(trim((string) ($l['name'] ?? '')), 0, 100) ?: '—',
                        'position' => $lp, 'archived_at' => $this->date($l['archived_at'] ?? null),
                        'created_at' => $this->date($l['created_at'] ?? null) ?? $this->now(), 'updated_at' => $this->now()]);
                    foreach (array_values((array) ($l['cards'] ?? [])) as $cp => $c) {
                        $cardId = $this->db->insert('cards', ['board_id' => $boardId, 'list_id' => $listId,
                            'title' => mb_substr(trim((string) ($c['title'] ?? '')), 0, 255) ?: '—',
                            'description' => isset($c['description']) ? mb_substr((string) $c['description'], 0, 20000) : null,
                            'start_date' => $this->day($c['start_date'] ?? null), 'end_date' => $this->day($c['end_date'] ?? null),
                            'position' => $cp, 'created_by' => $this->user->id, 'archived_at' => $this->date($c['archived_at'] ?? null),
                            'created_at' => $this->date($c['created_at'] ?? null) ?? $this->now(), 'updated_at' => $this->date($c['updated_at'] ?? null) ?? $this->now()]);
                        $count['cards']++;
                        foreach (array_unique((array) ($c['labels'] ?? [])) as $key) {
                            if (isset($labelIds[(string) $key])) {
                                $this->db->insert('card_labels', ['card_id' => $cardId, 'label_id' => $labelIds[(string) $key]]);
                            }
                        }
                        foreach ((array) ($c['attachments'] ?? []) as $a) {
                            $content = isset($a['content']) ? base64_decode((string) $a['content'], true) : false;
                            if ($content === false) {
                                continue; // exported without the files
                            }
                            $dir = storage_path('attachments/' . $boardId);
                            if (!is_dir($dir)) {
                                mkdir($dir, 0775, true);
                            }
                            $file = bin2hex(random_bytes(16));
                            file_put_contents("{$dir}/{$file}", $content);
                            $written[] = "{$dir}/{$file}";
                            $this->db->insert('attachments', ['card_id' => $cardId, 'user_id' => $this->user->id,
                                'name' => mb_substr(basename(str_replace('\\', '/', (string) ($a['name'] ?? 'file'))), 0, 200) ?: 'file', 'file' => $file,
                                'mime' => mb_substr((string) ($a['mime'] ?? 'application/octet-stream'), 0, 100), 'size' => strlen($content),
                                'created_at' => $this->date($a['created_at'] ?? null) ?? $this->now(), 'updated_at' => $this->now()]);
                            $count['attachments']++;
                        }
                        // log and notes keep their names; nobody's account is attached to them any more
                        foreach ((array) ($c['log'] ?? []) as $e) {
                            $field = (string) ($e['field'] ?? '');
                            if (!preg_match('/^[a-z_]{1,20}$/', $field)) {
                                continue;
                            }
                            $this->db->insert('card_activity', ['card_id' => $cardId, 'user_id' => null,
                                'user_name' => mb_substr((string) ($e['by'] ?? ''), 0, 100), 'field' => $field,
                                'old_value' => isset($e['old']) ? (string) $e['old'] : null, 'new_value' => isset($e['new']) ? (string) $e['new'] : null,
                                'created_at' => $this->date($e['at'] ?? null) ?? $this->now(), 'updated_at' => $this->date($e['edited_at'] ?? ($e['at'] ?? null)) ?? $this->now()]);
                        }
                    }
                }
                $existing[] = mb_strtolower($name);
            }
            $count['calendars'] = count((array) ($doc['calendars'] ?? []));
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            array_map('unlink', array_filter($written, 'is_file'));
            throw $e;
        }

        return $count;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function date(mixed $value): ?string
    {
        $time = is_string($value) && $value !== '' ? strtotime($value) : false;

        return $time ? date('Y-m-d H:i:s', $time) : null;
    }

    private function day(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function url(mixed $value): ?string
    {
        return is_string($value) && preg_match('#^https://#', $value) ? mb_substr($value, 0, 500) : null;
    }
}
