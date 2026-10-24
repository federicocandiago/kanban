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

    /** Shown in the viewer over the card window (images and PDFs); other files are downloaded. */
    public function viewer(): ?string
    {
        return $this->isImage() ? 'image' : ($this->mime === 'application/pdf' ? 'pdf' : null);
    }

    public function url(): string
    {
        return url('/attachments/' . $this->id);
    }
}
