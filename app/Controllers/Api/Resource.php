<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;
use App\Services\Palette;

/** How each object looks in the API (documented in App\Services\ApiSpec). */
final class Resource
{
    public static function board(Board $board): array
    {
        $photo = $board->bg_type === 'photo';

        return [
            'id' => (string) $board->uuid,
            'name' => (string) $board->name,
            'background' => $photo
                ? ['type' => 'photo', 'url' => (string) $board->bg_value, 'author' => $board->bg_credit_name, 'author_url' => $board->bg_credit_url]
                : ['type' => 'color', 'color' => (string) $board->bg_value, 'hex' => Palette::primary((string) $board->bg_value)],
            'role' => (string) $board->role,
            'favorite' => (bool) $board->is_favorite,
            'archived' => $board->isArchived(),
            'url' => absolute_url('/boards/' . $board->uuid),
            'created_at' => self::time($board->created_at),
            'updated_at' => self::time($board->updated_at),
        ];
    }

    public static function list(BoardList $list, string $boardId): array
    {
        return [
            'id' => (int) $list->id,
            'board_id' => $boardId,
            'name' => (string) $list->name,
            'position' => (int) $list->position,
            'archived' => $list->isArchived(),
            'created_at' => self::time($list->created_at),
            'updated_at' => self::time($list->updated_at),
        ];
    }

    public static function card(Card $card, string $boardId): array
    {
        return [
            'id' => (int) $card->id,
            'board_id' => $boardId,
            'list_id' => (int) $card->list_id,
            'title' => (string) $card->title,
            'description' => $card->description,
            'start_date' => $card->start_date,
            'end_date' => $card->end_date,
            'position' => (int) $card->position,
            'labels' => array_map([self::class, 'label'], $card->labels()),
            'attachment_count' => (int) $card->attachment_count,
            'archived' => $card->isArchived(),
            'created_at' => self::time($card->created_at),
            'updated_at' => self::time($card->updated_at),
        ];
    }

    public static function label(Label $label): array
    {
        return [
            'id' => (int) $label->id,
            'name' => (string) $label->name,
            'color' => (string) $label->color,
            'hex' => Palette::secondary((string) $label->color),
            'mine' => (int) $label->user_id === (int) auth()?->id,
        ];
    }

    public static function attachment(Attachment $attachment): array
    {
        return [
            'id' => (int) $attachment->id,
            'name' => (string) $attachment->name,
            'size' => (int) $attachment->size,
            'mime' => (string) $attachment->mime,
            'download_url' => absolute_url('/api/v1/attachments/' . $attachment->id . '/download'),
            'created_at' => self::time($attachment->created_at),
        ];
    }

    public static function activity(Activity $entry): array
    {
        return [
            'id' => (int) $entry->id,
            'user' => (string) $entry->user_name,
            'field' => (string) $entry->field,
            'old_value' => $entry->old_value,
            'new_value' => $entry->new_value,
            'created_at' => self::time($entry->created_at),
        ];
    }

    public static function user(User $user, bool $withEmail = true): array
    {
        return ['id' => (int) $user->id, 'name' => (string) $user->name] + ($withEmail ? ['email' => (string) $user->email] : [])
            + ($user->role ? ['role' => (string) $user->role] : []);
    }

    /** ISO 8601 with the offset of the server's time zone. */
    public static function time(?string $datetime): ?string
    {
        return $datetime ? date('c', strtotime($datetime)) : null;
    }
}
