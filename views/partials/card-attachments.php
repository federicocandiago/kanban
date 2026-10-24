<?php /** @var App\Models\Card $card */
$attachments = $card->attachments(); ?>
<section class="card-section" id="card-attachments" <?= $attachments ? '' : 'hidden' ?>>
    <h3><?= component('icon', ['name' => 'clip']) ?> <?= e(__('Attachments')) ?></h3>
    <?php foreach ($attachments as $file):
        $open = $file->viewer() ? 'data-viewer="' . $file->viewer() . '" data-viewer-src="' . e($file->url()) . '?inline=1" data-viewer-name="' . e($file->name) . '"' : 'download'; ?>
        <div class="attachment">
            <a class="attachment-thumb" href="<?= e($file->url()) ?>" <?= $open ?>
               <?= $file->isImage() ? 'style="background-image: url(\'' . e($file->url()) . '?inline=1\')"' : '' ?>>
                <?php if (!$file->isImage()): ?><?= e(strtoupper(pathinfo((string) $file->name, PATHINFO_EXTENSION) ?: '?')) ?><?php endif ?>
            </a>
            <div class="min-w-0">
                <a class="attachment-name" href="<?= e($file->url()) ?>" <?= $open ?>><?= e($file->name) ?></a>
                <small class="text-muted d-block"><?= e(format_date($file->created_at)) ?> · <?= e(format_bytes((int) $file->size)) ?>
                    <?php if (($access ?? 'member') === 'member'): /* visitors of a public board never delete */ ?>
                    · <button type="button" class="link-btn" data-action="<?= e(url("/attachments/{$file->id}/delete")) ?>"
                              data-confirm="<?= e(__('The attachment “:name” will be deleted.', ['name' => $file->name])) ?>"><?= e(__('Delete')) ?></button>
                    <?php endif ?></small>
            </div>
        </div>
    <?php endforeach ?>
</section>
