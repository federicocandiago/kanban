<?php /* Attachment viewer (images and PDFs), shown by app.js over the window the attachment is in. */ ?>
<template id="viewer-template">
    <div class="viewer" role="dialog" aria-modal="true" tabindex="-1">
        <div class="viewer-bar">
            <span class="viewer-name"></span>
            <a class="viewer-btn" href="#" download data-viewer-download title="<?= e(__('Download')) ?>" aria-label="<?= e(__('Download')) ?>"><?= component('icon', ['name' => 'download', 'size' => 20]) ?></a>
            <button class="viewer-btn" type="button" data-viewer-close title="<?= e(__('Close')) ?>" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>
        </div>
        <div class="viewer-stage"></div>
        <button class="viewer-nav is-prev" type="button" data-viewer-step="-1" aria-label="<?= e(__('Previous')) ?>">‹</button>
        <button class="viewer-nav is-next" type="button" data-viewer-step="1" aria-label="<?= e(__('Next')) ?>">›</button>
    </div>
</template>
