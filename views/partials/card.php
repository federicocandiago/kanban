<?php
/**
 * Card back, loaded into #card-modal. The fields belong to one form (form="…" attribute) that
 * saves itself on every change; labels and attachments are separate forms in the sidebar.
 * @var App\Models\Card $card @var App\Models\Board $board @var App\Models\BoardList[] $lists @var App\Models\Label[] $myLabels
 */
$form = 'card-form-' . (int) $card->id;
$onCard = $card->labelIds(); ?>
<div class="card-back" id="card-panel" data-card-id="<?= (int) $card->id ?>">
    <form id="<?= $form ?>" method="post" action="<?= e(url("/cards/{$card->id}/update")) ?>" data-ajax data-autosubmit novalidate><?= csrf_field() ?></form>
    <button type="button" class="pop-close card-back-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>

    <?php if ($card->isArchived()): ?>
        <div class="card-back-archived"><?= component('icon', ['name' => 'archive']) ?> <?= e(__('This card is archived.')) ?></div>
    <?php endif ?>

    <div class="card-back-head">
        <?= component('icon', ['name' => 'card', 'size' => 20]) ?>
        <div class="flex-grow-1">
            <textarea class="card-back-title" name="title" form="<?= $form ?>" rows="1" maxlength="255" required data-enter-submit aria-label="<?= e(__('Card title')) ?>"><?= e($card->title) ?></textarea>
            <div class="invalid-feedback" data-error-for="title"></div>
            <label class="card-back-list"><?= e(__('in list')) ?>
                <select name="list_id" form="<?= $form ?>" aria-label="<?= e(__('List')) ?>">
                    <?php foreach ($lists as $list): ?>
                        <option value="<?= (int) $list->id ?>" <?= (int) $list->id === (int) $card->list_id ? 'selected' : '' ?>><?= e($list->name) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
        </div>
    </div>

    <div class="card-back-grid">
        <div class="card-back-main">
            <?= partial('card-labels', compact('card')) ?>

            <div class="card-dates">
                <label>
                    <span class="card-back-label"><?= e(__('Start date')) ?></span>
                    <input class="form-control form-control-sm" type="date" name="start_date" form="<?= $form ?>" value="<?= e($card->start_date) ?>">
                    <span class="invalid-feedback" data-error-for="start_date"></span>
                </label>
                <label>
                    <span class="card-back-label"><?= e(__('End date')) ?></span>
                    <input class="form-control form-control-sm" type="date" name="end_date" form="<?= $form ?>" value="<?= e($card->end_date) ?>">
                    <span class="invalid-feedback" data-error-for="end_date"></span>
                </label>
            </div>

            <section class="card-section">
                <h3><?= component('icon', ['name' => 'text']) ?> <?= e(__('Description')) ?></h3>
                <textarea class="form-control card-description" name="description" form="<?= $form ?>" rows="4"
                          placeholder="<?= e(__('Add a more detailed description…')) ?>"><?= e($card->description) ?></textarea>
            </section>

            <?= partial('card-attachments', compact('card', 'board')) ?>
            <?= partial('card-activity', compact('card')) ?>
        </div>

        <aside class="card-back-side">
            <h3><?= e(__('Add to card')) ?></h3>
            <div class="dropdown">
                <button class="side-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><?= component('icon', ['name' => 'tag']) ?> <?= e(__('Labels')) ?></button>
                <div class="dropdown-menu pop label-picker">
                    <div class="pop-head"><span class="pop-title"><?= e(__('Labels')) ?></span></div>
                    <form class="pop-body" method="post" action="<?= e(url("/cards/{$card->id}/labels")) ?>" data-ajax data-autosubmit>
                        <?= csrf_field() ?>
                        <?php foreach ($myLabels as $label): ?>
                            <label class="label-option">
                                <input type="checkbox" name="label_ids[]" value="<?= (int) $label->id ?>" <?= in_array((int) $label->id, $onCard, true) ? 'checked' : '' ?>>
                                <span class="label label-<?= e($label->color) ?>"><?= e($label->display()) ?></span>
                                <?= component('icon', ['name' => 'check', 'size' => 14]) ?>
                            </label>
                        <?php endforeach ?>
                        <a class="d-block mt-2 small" href="<?= base_url() ?>/account#labels"><?= e(__('Manage my labels')) ?></a>
                    </form>
                </div>
            </div>
            <?php $max = App\Services\AttachmentService::maxBytes(); ?>
            <form method="post" action="<?= e(url("/cards/{$card->id}/attachments")) ?>" enctype="multipart/form-data" data-ajax data-autosubmit data-reset>
                <?= csrf_field() ?>
                <label class="side-btn mb-1"><?= component('icon', ['name' => 'clip']) ?> <?= e(__('Attachment')) ?>
                    <input type="file" name="file" class="visually-hidden" data-max-bytes="<?= $max ?>"
                           data-too-big="<?= e(__('The file is too large (max :size).', ['size' => format_bytes($max)])) ?>">
                </label>
                <small class="side-hint"><?= e(__('Maximum size: :size', ['size' => format_bytes($max)])) ?></small>
                <div class="invalid-feedback" data-error-for="file"></div>
            </form>

            <div class="side-bottom">
                <button class="btn btn-success side-confirm" type="submit" form="<?= $form ?>" data-close><?= component('icon', ['name' => 'check']) ?> <?= e(__('Confirm')) ?></button>
                <h3><?= e(__('Actions')) ?></h3>
                <?php if ($card->isArchived()): ?>
                    <button class="side-btn" type="button" data-post="<?= e(url("/cards/{$card->id}/restore")) ?>"><?= component('icon', ['name' => 'restore']) ?> <?= e(__('Restore')) ?></button>
                <?php else: ?>
                    <button class="side-btn" type="button" data-post="<?= e(url("/cards/{$card->id}/archive")) ?>"><?= component('icon', ['name' => 'archive']) ?> <?= e(__('Archive')) ?></button>
                <?php endif ?>
                <?php if ($board->isOwner()): ?>
                    <button class="side-btn is-danger" type="button" data-action="<?= e(url("/cards/{$card->id}/delete")) ?>"
                            data-confirm="<?= e(__('The card “:name” and its attachments will be deleted for good.', ['name' => $card->title])) ?>"><?= component('icon', ['name' => 'trash']) ?> <?= e(__('Delete')) ?></button>
                <?php endif ?>
            </div>
        </aside>
    </div>
</div>
