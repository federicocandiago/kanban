<?php /** Files attached to the event, kept on the calendar server (RFC 8607). @var App\Models\Event $event @var string|null $view */
$files = $event->attachments();
$query = !empty($view) ? '?view=' . rawurlencode($view) : ''; ?>
<section class="card-section" id="event-attachments" <?= $files ? '' : 'hidden' ?>>
    <h3><?= component('icon', ['name' => 'clip']) ?> <?= e(__('Attachments')) ?></h3>
    <?php foreach ($files as $i => $file):
        $url = url("/events/{$event->id}/attachments/{$i}");
        $image = str_starts_with($file['type'], 'image/');
        $viewer = $image ? 'image' : ($file['type'] === 'application/pdf' ? 'pdf' : null);
        $open = $viewer ? 'data-viewer="' . $viewer . '" data-viewer-src="' . e($url) . '?inline=1" data-viewer-name="' . e($file['name']) . '"' : 'download'; ?>
        <div class="attachment">
            <a class="attachment-thumb" href="<?= e($url) ?>" <?= $open ?>
               <?= $image ? 'style="background-image: url(\'' . e($url) . '?inline=1\')"' : '' ?>>
                <?php if (!$image): ?><?= e(strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION) ?: '?')) ?><?php endif ?>
            </a>
            <div class="min-w-0">
                <a class="attachment-name" href="<?= e($url) ?>" <?= $open ?>><?= e($file['name']) ?></a>
                <small class="text-muted d-block">
                    <?= $file['size'] ? e(format_bytes((int) $file['size'])) : '' ?>
                    <?php if ($event->isEditable()): ?>
                        · <button type="button" class="link-btn" data-action="<?= e(url("/events/{$event->id}/attachments/{$i}/delete") . $query) ?>"
                                  data-confirm="<?= e(__('The attachment “:name” will be deleted.', ['name' => $file['name']])) ?>"><?= e(__('Delete')) ?></button>
                    <?php endif ?>
                </small>
            </div>
        </div>
    <?php endforeach ?>
</section>
