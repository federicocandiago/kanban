<?php
/**
 * New card from the + in the top bar, in the card window: nothing is saved until "Create card".
 * @var App\Models\Board $board @var App\Models\BoardList[] $lists @var int $listId the preselected list
 */
$form = 'card-new-form'; ?>
<div class="card-back" id="card-panel">
    <form id="<?= $form ?>" method="post" action="<?= e($board->url('/cards')) ?>" data-ajax novalidate><?= csrf_field() ?></form>
    <button type="button" class="pop-close card-back-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>

    <div class="card-back-head">
        <?= component('icon', ['name' => 'card', 'size' => 20]) ?>
        <div class="flex-grow-1">
            <textarea class="card-back-title" name="title" form="<?= $form ?>" rows="1" maxlength="255" required data-enter-submit autofocus
                      placeholder="<?= e(__('Enter a title for this card…')) ?>" aria-label="<?= e(__('Card title')) ?>"></textarea>
            <div class="invalid-feedback" data-error-for="title"></div>
            <p class="card-back-list"><?= e(__('New card: it is saved when you create it.')) ?></p>
        </div>
    </div>

    <div class="card-back-grid">
        <div class="card-back-main">
            <details class="card-dates-box">
                <summary><?= component('icon', ['name' => 'clock', 'size' => 16]) ?> <span><?= e(__('Dates')) ?></span>
                    <small class="card-dates-sum" data-none="<?= e(__('none')) ?>"><?= e(__('none')) ?></small></summary>
                <div class="card-dates">
                    <label>
                        <span class="card-back-label"><?= e(__('Start date')) ?></span>
                        <input class="form-control form-control-sm" type="date" name="start_date" form="<?= $form ?>">
                        <span class="invalid-feedback" data-error-for="start_date"></span>
                    </label>
                    <label>
                        <span class="card-back-label"><?= e(__('End date')) ?></span>
                        <input class="form-control form-control-sm" type="date" name="end_date" form="<?= $form ?>">
                        <span class="invalid-feedback" data-error-for="end_date"></span>
                    </label>
                </div>
            </details>

            <section class="card-section">
                <h3><?= component('icon', ['name' => 'text']) ?> <?= e(__('Description')) ?></h3>
                <textarea class="form-control card-description" name="description" form="<?= $form ?>" rows="7" placeholder="<?= e(__('Add a more detailed description…')) ?>"></textarea>
            </section>
            <button class="btn btn-success side-confirm confirm-inline" type="submit" form="<?= $form ?>"><?= component('icon', ['name' => 'plus']) ?> <?= e(__('Create card')) ?></button><?php /* phones only: right under the description */ ?>
        </div>

        <aside class="card-back-side">
            <h3><?= e(__('List')) ?></h3>
            <label class="side-select">
                <?= component('icon', ['name' => 'list']) ?>
                <select name="list_id" form="<?= $form ?>" aria-label="<?= e(__('List')) ?>">
                    <?php foreach ($lists as $list): ?>
                        <option value="<?= (int) $list->id ?>" <?= (int) $list->id === $listId ? 'selected' : '' ?>><?= e($list->name) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <div class="invalid-feedback" data-error-for="list_id"></div>
            <div class="side-bottom">
                <button class="btn btn-success side-confirm" type="submit" form="<?= $form ?>"><?= component('icon', ['name' => 'plus']) ?> <?= e(__('Create card')) ?></button>
            </div>
        </aside>
    </div>
</div>
