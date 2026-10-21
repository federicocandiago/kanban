<?php /** @var App\Models\Card $card */
$attachments = $card->attachments(); ?>
<section class="card-section" id="card-attachments" <?= $attachments ? '' : 'hidden' ?>>
    <h3><?= component('icon', ['name' => 'clip']) ?> <?= e(__('Attachments')) ?></h3>
    <?php foreach ($attachments as $file): ?>
        <div class="attachment">
            <a class="attachment-thumb" href="<?= e($file->url()) ?><?= $file->isImage() ? '?inline=1' : '' ?>" target="_blank" rel="noopener"
               <?= $file->isImage() ? 'style="background-image: url(\'' . e($file->url()) . '?inline=1\')"' : '' ?>>
                <?php if (!$file->isImage()): ?><?= e(strtoupper(pathinfo((string) $file->name, PATHINFO_EXTENSION) ?: '?')) ?><?php endif ?>
            </a>
            <div class="min-w-0">
                <a class="attachment-name" href="<?= e($file->url()) ?>"><?= e($file->name) ?></a>
                <small class="text-muted d-block"><?= e(format_date($file->created_at)) ?> · <?= e(format_bytes((int) $file->size)) ?>
                    · <button type="button" class="link-btn" data-action="<?= e(url("/attachments/{$file->id}/delete")) ?>"
                              data-confirm="<?= e(__('The attachment “:name” will be deleted.', ['name' => $file->name])) ?>"><?= e(__('Delete')) ?></button></small>
            </div>
        </div>
    <?php endforeach ?>
</section>
