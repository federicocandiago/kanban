<?php /* "Create board" dialog, reachable from the + in the top bar on every page. */ ?>
<div class="modal fade" id="create-board-modal" tabindex="-1" aria-labelledby="create-board-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered create-board">
        <form class="modal-content" method="post" action="<?= base_url() ?>/boards" data-ajax data-reset novalidate>
            <?= csrf_field() ?>
            <h2 class="visually-hidden" id="create-board-title"><?= e(__('Create board')) ?></h2>
            <div class="create-board-body">
                <div class="create-board-preview" id="create-board-preview" style="background-color: <?= e(App\Services\Palette::primary(auth()->color())) ?>">
                    <input class="create-board-name" name="name" maxlength="100" required autocomplete="off"
                           placeholder="<?= e(__('Add board title')) ?>" aria-label="<?= e(__('Board title')) ?>">
                    <button type="button" class="pop-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x']) ?></button>
                    <div class="invalid-feedback" data-error-for="name"></div>
                </div>
                <?= component('background-picker', ['id' => 'create-bg', 'preview' => '#create-board-preview']) ?>
            </div>
            <div class="mt-2">
                <button class="btn btn-success" type="submit"><?= e(__('Create board')) ?></button>
            </div>
        </form>
    </div>
</div>
