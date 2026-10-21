<?php /** Unsplash results for the background picker. @var array $photos @var string $preview selector of the preview element, or '' */ ?>
<?php if (!$photos): ?>
    <span class="bg-loading"><?= e(__('No photos found.')) ?></span>
<?php endif ?>
<?php foreach ($photos as $photo): ?>
    <label class="bg-option is-photo" style="background-color: <?= e($photo['color']) ?>; background-image: url('<?= e($photo['thumb']) ?>')">
        <input type="radio" name="background" value="photo:<?= e($photo['id']) ?>"
            <?php if ($preview !== ''): ?>data-preview="<?= e($preview) ?>" data-preview-style="background-image: url('<?= e($photo['thumb']) ?>')"<?php endif ?>>
        <?= component('icon', ['name' => 'check', 'size' => 14]) ?>
        <a class="bg-author" href="<?= e($photo['author_url']) ?>" target="_blank" rel="noopener"><?= e($photo['author']) ?></a>
    </label>
<?php endforeach ?>
