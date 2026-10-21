<?php
/**
 * Board background in two collapsible sections: the standard colours and, with UNSPLASH_ACCESS_KEY,
 * Unsplash photos (search included). The colours start open only when there are no photos.
 * Radios named "background": "color:{key}" or "photo:{unsplash id}".
 * @var string $id unique prefix @var string|null $preview selector of the element that previews the choice
 * @var App\Models\Board|null $board current board (preselects its colour)
 */
use App\Services\Palette;
use App\Services\Unsplash;

$current = isset($board) ? ($board->bg_type === 'color' ? $board->bg_value : null) : auth()->color();
$preview ??= null;
$photos = Unsplash::enabled(); ?>
<div class="bg-picker">
    <details class="bg-section" <?= $photos ? '' : 'open' ?>>
        <summary><?= e(__('Background colour')) ?></summary>
        <div class="bg-grid bg-colors">
            <?php foreach (Palette::COLORS as $key => [$hex]): ?>
                <label class="bg-option" style="background-color: <?= e($hex) ?>" title="<?= e(Palette::names()[$key]) ?>">
                    <input type="radio" name="background" value="color:<?= e($key) ?>" <?= $current === $key ? 'checked' : '' ?>
                        <?php if ($preview): ?>data-preview="<?= e($preview) ?>" data-preview-style="background-color: <?= e($hex) ?>"<?php endif ?>>
                    <?= component('icon', ['name' => 'check', 'size' => 14]) ?>
                </label>
            <?php endforeach ?>
        </div>
    </details>

    <?php if ($photos): ?>
        <details class="bg-section" open>
            <summary><?= e(__('Photos from Unsplash')) ?></summary>
            <div class="bg-search">
                <?= component('icon', ['name' => 'search', 'size' => 14]) ?>
                <input class="form-control form-control-sm" type="search" placeholder="<?= e(__('Search Unsplash photos')) ?>" aria-label="<?= e(__('Search Unsplash photos')) ?>"
                       data-load="<?= e(url('/backgrounds?' . ($preview ? 'preview=' . rawurlencode($preview) . '&' : '') . 'q=')) ?>" data-load-into="#<?= e($id) ?>-photos">
            </div>
            <div class="bg-grid bg-photos" id="<?= e($id) ?>-photos" data-load-from="<?= e(url('/backgrounds' . ($preview ? '?preview=' . rawurlencode($preview) : ''))) ?>">
                <span class="bg-loading"><?= e(__('Loading photos…')) ?></span>
            </div>
            <p class="bg-credit"><?= __('Photos from <a href=":url" target="_blank" rel="noopener">Unsplash</a>', ['url' => e(Unsplash::referral('https://unsplash.com/'))]) ?></p>
        </details>
    <?php endif ?>
    <div class="invalid-feedback" data-error-for="background"></div>
</div>
