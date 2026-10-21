<?php /* One shared confirmation dialog, driven by [data-confirm] buttons and form[data-ask] in app.js */ ?>
<div class="modal fade" id="confirm-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content pop">
            <div class="pop-head">
                <h2 class="pop-title"><?= e(__('Are you sure?')) ?></h2>
                <button type="button" class="pop-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x']) ?></button>
            </div>
            <div class="pop-body">
                <p data-confirm-text></p>
                <button type="button" class="btn btn-danger w-100" data-confirm-ok><?= e(__('Confirm')) ?></button>
            </div>
        </div>
    </div>
</div>
