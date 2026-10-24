<?php /* Shared dialogs and the toast area, at the end of every page. */ ?>
<?php if (auth()): ?>
    <?= component('side-menu') ?>
    <?= component('create-board') ?>
    <?= component('search-modal') ?>
<?php endif ?>
<?= component('confirm-modal') ?>
<?= component('viewer') ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts"></div>
