<?php /** "Rename" dialog of a board or calendar (from its ⋯ menu): the name comes selected. @var string $action @var string $name */ ?>
<div class="modal fade" id="rename-modal" tabindex="-1" aria-labelledby="rename-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content pop" method="post" action="<?= e($action) ?>" data-ajax novalidate>
            <?= csrf_field() ?>
            <div class="pop-head">
                <h2 class="pop-title" id="rename-title"><?= e(__('Rename')) ?></h2>
                <button type="button" class="pop-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x']) ?></button>
            </div>
            <div class="pop-body">
                <input class="form-control mb-2" name="name" value="<?= e($name) ?>" maxlength="100" required autocomplete="off" autofocus aria-label="<?= e(__('Name')) ?>">
                <div class="invalid-feedback" data-error-for="name"></div>
                <button class="btn btn-success w-100" type="submit"><?= e(__('Save')) ?></button>
            </div>
        </form>
    </div>
</div>
