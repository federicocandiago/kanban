<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Profile pictures: any JPEG/PNG/GIF/WebP is center-cropped, resized to 256x256
 * and re-encoded as PNG (which also strips metadata and anything hidden in the file).
 */
final class AvatarService
{
    private const SIZE = 256;

    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? storage_path('avatars');
    }

    public static function maxBytes(): int
    {
        return (int) env('AVATAR_MAX_KB', 4096) * 1024;
    }

    public function path(string $file): ?string
    {
        if (!preg_match('/^\d+-[a-f0-9]{16}\.png$/', $file)) {
            return null;
        }
        $path = $this->root . '/' . $file;

        return is_file($path) ? $path : null;
    }

    /** Returns the new file name. */
    public function store(int $userId, string $uploadedPath): string
    {
        if (filesize($uploadedPath) > self::maxBytes()) {
            throw new \LengthException('Image too large');
        }
        $info = @getimagesize($uploadedPath);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)
            || $info[0] * $info[1] > 40_000_000) {
            throw new \InvalidArgumentException('Unsupported image');
        }

        $source = @imagecreatefromstring((string) file_get_contents($uploadedPath));
        if ($source === false) {
            throw new \InvalidArgumentException('Unsupported image');
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $side = min($width, $height);
        $avatar = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($avatar, false);
        imagesavealpha($avatar, true);
        imagecopyresampled(
            $avatar, $source, 0, 0,
            intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE, $side, $side
        );

        $file = $userId . '-' . bin2hex(random_bytes(8)) . '.png';
        if (!is_dir($this->root)) {
            mkdir($this->root, 0775, true);
        }
        imagepng($avatar, $this->root . '/' . $file, 6);

        return $file;
    }

    public function delete(?string $file): void
    {
        if ($file && ($path = $this->path($file))) {
            unlink($path);
        }
    }
}
