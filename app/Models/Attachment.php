<?php

declare(strict_types=1);

namespace App\Models;

final class Attachment extends Model
{
    protected static string $table = 'attachments';

    public function isImage(): bool
    {
        return in_array($this->mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    public function url(): string
    {
        return url('/attachments/' . $this->id);
    }
}
