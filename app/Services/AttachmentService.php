<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Card attachments: storage/attachments/{board id}/{random name}. The original name and
 * type are kept in the database; files are only served to board members, as downloads.
 */
final class AttachmentService
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? storage_path('attachments');
    }

    /**
     * ATTACHMENT_MAX_MB from .env, lowered to what PHP accepts (upload_max_filesize, post_max_size):
     * the limit shown to users is always the real one.
     */
    public static function maxBytes(): int
    {
        $limits = [max(1, (int) env('ATTACHMENT_MAX_MB', 10)) * 1024 * 1024];
        foreach (['upload_max_filesize', 'post_max_size'] as $ini) {
            $value = ini_parse_quantity((string) ini_get($ini));
            if ($value > 0) {
                $limits[] = $value;
            }
        }

        return min($limits);
    }

    /** @return array{file: string, mime: string, size: int} */
    public function store(int $boardId, string $uploadedPath): array
    {
        $size = (int) filesize($uploadedPath);
        if ($size > self::maxBytes()) {
            throw new \LengthException('File too large');
        }
        $dir = "{$this->root}/{$boardId}";
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new \RuntimeException("Cannot create {$dir}");
        }
        $file = bin2hex(random_bytes(16));
        if (!move_uploaded_file($uploadedPath, "{$dir}/{$file}") && !rename($uploadedPath, "{$dir}/{$file}")) {
            throw new \RuntimeException('Cannot store the file');
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type("{$dir}/{$file}") ?: '') : '';

        return ['file' => $file, 'mime' => $mime ?: 'application/octet-stream', 'size' => $size];
    }

    public function path(int $boardId, string $file): ?string
    {
        $path = "{$this->root}/{$boardId}/{$file}";

        return preg_match('/^[a-f0-9]{32}$/', $file) && is_file($path) ? $path : null;
    }

    public function delete(int $boardId, string $file): void
    {
        if ($path = $this->path($boardId, $file)) {
            unlink($path);
        }
    }

    /** Removes the files of the cards matching $where (on the cards table) before the rows are deleted. */
    public function deleteForCards(string $where, array $params): void
    {
        $rows = \App\Core\Database::connection()->select(
            "SELECT c.board_id, a.file FROM attachments a JOIN cards c ON c.id = a.card_id WHERE c.{$where}",
            $params
        );
        foreach ($rows as $row) {
            $this->delete((int) $row['board_id'], (string) $row['file']);
        }
    }

    public function destroyBoard(int $boardId): void
    {
        $dir = "{$this->root}/{$boardId}";
        foreach (glob("{$dir}/*") ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
}
