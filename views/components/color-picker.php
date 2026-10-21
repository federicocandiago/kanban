<?php
/** The user's interface colour: twelve swatches (radio "color"); choosing one previews it in the top bar. @var string $value */
use App\Services\Palette; ?>
<div class="mb-3">
    <span class="form-label"><?= e(__('Colour')) ?></span>
    <div class="color-picker" role="radiogroup" aria-label="<?= e(__('Colour')) ?>">
        <?php foreach (Palette::names() as $key => $name): ?>
            <label class="color-option" style="background-color: <?= e(Palette::primary($key)) ?>" title="<?= e($name) ?>">
                <input type="radio" name="color" value="<?= e($key) ?>" <?= $key === $value ? 'checked' : '' ?> aria-label="<?= e($name) ?>"
                       data-preview="#topbar" data-preview-style="background-color: <?= e(Palette::secondary($key)) ?>">
                <?= component('icon', ['name' => 'check', 'size' => 14]) ?>
            </label>
        <?php endforeach ?>
    </div>
    <div class="form-text"><?= e(__('Used for the top bar, the boards page and as the starting background of new boards.')) ?></div>
    <div class="invalid-feedback" data-error-for="color"></div>
</div>
