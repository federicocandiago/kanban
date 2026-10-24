<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Palette;

/** A background chosen in the picker (bg_* columns): a palette colour or an Unsplash photo. Boards and calendars. */
trait HasBackground
{
    public function hasBackground(): bool
    {
        return in_array($this->bg_type, ['color', 'photo'], true);
    }

    /** Inline style for the page or a tile: a palette colour or an Unsplash photo. */
    public function backgroundStyle(bool $thumb = false): string
    {
        if ($this->bg_type === 'photo') {
            if ($thumb || !$this->bg_thumb) {
                return 'background-color: #2c3e50; background-image: url(\'' . e($this->bg_thumb ?: $this->bg_value) . '\')';
            }

            // the thumbnail (already seen on the dashboard) shows at once, the full photo covers it when loaded
            return 'background-color: #2c3e50; background-image: url(\'' . e($this->bg_value) . '\'), url(\'' . e($this->bg_thumb) . '\')';
        }

        return 'background-color: ' . Palette::primary((string) $this->bg_value);
    }

    /** The colour the glass of the page is tinted with: the photo's average colour, or the chosen colour. */
    public function tint(): string
    {
        if ($this->bg_type === 'photo') {
            return $this->bg_tint ?: '#2c3e50';
        }

        return Palette::primary((string) $this->bg_value);
    }
}
