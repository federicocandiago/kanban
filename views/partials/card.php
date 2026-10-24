<?php
/**
 * Card back, loaded into #card-modal. The fields belong to one form (form="…" attribute) that
 * saves itself on every change; labels and attachments are separate forms in the sidebar.
 * @var App\Models\Card $card @var App\Models\Board $board @var App\Models\BoardList[] $lists @var App\Models\Label[] $myLabels
 * On a public board read only ($access "read") the fields only show; notes can still be added.
 */
$form = 'card-form-' . (int) $card->id;
$onCard = $card->labelIds();
$read = ($access ?? 'member') === 'read';
$off = $read ? 'disabled' : ''; ?>
<div class="card-back" id="card-panel" data-card-id="<?= (int) $card->id ?>">
    <form id="<?= $form ?>" method="post" action="<?= e(url("/cards/{$card->id}/update")) ?>" data-ajax data-autosubmit novalidate><?= csrf_field() ?></form>
    <button type="button" class="pop-close card-back-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>

    <?php if ($card->isArchived()): ?>
        <div class="card-back-archived"><?= component('icon', ['name' => 'archive']) ?> <?= e(__('This card is archived.')) ?></div>
    <?php endif ?>

    <div class="card-back-head">
        <?= component('icon', ['name' => 'card', 'size' => 20]) ?>
        <div class="flex-grow-1">
            <textarea class="card-back-title" name="title" form="<?= $form ?>" rows="1" maxlength="255" required data-enter-submit <?= $off ?> aria-label="<?= e(__('Card title')) ?>"><?= e($card->title) ?></textarea>
            <div class="invalid-feedback" data-error-for="title"></div>
        </div>
    </div>

    <div class="card-back-grid">
        <div class="card-back-main">
            <?= partial('card-labels', compact('card')) ?>

            <?php $dates = trim(format_day($card->start_date) . ($card->start_date && $card->end_date ? ' → ' : '') . format_day($card->end_date)); ?>
            <details class="card-dates-box">
                <summary><?= component('icon', ['name' => 'clock', 'size' => 16]) ?> <span><?= e(__('Dates')) ?></span>
                    <small class="card-dates-sum" data-none="<?= e(__('none')) ?>"><?= $dates !== '' ? e($dates) : e(__('none')) ?></small></summary>
                <div class="card-dates">
                    <label>
                        <span class="card-back-label"><?= e(__('Start date')) ?></span>
                        <input class="form-control form-control-sm" type="date" name="start_date" form="<?= $form ?>" value="<?= e($card->start_date) ?>" <?= $off ?>>
                        <span class="invalid-feedback" data-error-for="start_date"></span>
                    </label>
                    <label>
                        <span class="card-back-label"><?= e(__('End date')) ?></span>
                        <input class="form-control form-control-sm" type="date" name="end_date" form="<?= $form ?>" value="<?= e($card->end_date) ?>" <?= $off ?>>
                        <span class="invalid-feedback" data-error-for="end_date"></span>
                    </label>
                </div>
            </details>

            <?php $checks = App\Models\ChecklistItem::forCard((int) $card->id); ?>
            <section class="card-section card-switch" data-card-switch="<?= (int) $card->id ?>" data-view="<?= $checks ? 'checklist' : 'description' ?>">
                <h3>
                    <span data-pane-title="description"><?= component('icon', ['name' => 'text']) ?> <?= e(__('Description')) ?></span>
                    <span data-pane-title="checklist"><?= component('icon', ['name' => 'check-square']) ?> <?= e(__('Checklist')) ?> <span data-check-count></span></span>
                </h3>
                <div class="card-switch-pane" data-pane="description">
                    <textarea class="form-control card-description" name="description" form="<?= $form ?>" rows="7" <?= $off ?>
                              placeholder="<?= e(__('Add a more detailed description…')) ?>"><?= e($card->description) ?></textarea>
                </div>
                <div class="card-switch-pane" data-pane="checklist">
                    <?= partial('card-checklist', ['card' => $card, 'items' => $checks]) ?>
                </div>
            </section>
            <?php if (!$read): ?><button class="btn btn-success side-confirm confirm-inline" type="submit" form="<?= $form ?>" data-close><?= component('icon', ['name' => 'check']) ?> <?= e(__('Confirm')) ?></button><?php endif /* phones only: right under the description, in reach */ ?>

            <?= partial('card-attachments', compact('card', 'board')) ?>
            <?= partial('card-activity', compact('card', 'board')) ?>
        </div>

        <aside class="card-back-side">
            <h3><?= e(__('List')) ?></h3>
            <label class="side-select">
                <?= component('icon', ['name' => 'list']) ?>
                <select name="list_id" form="<?= $form ?>" aria-label="<?= e(__('List')) ?>" <?= $off ?>>
                    <?php foreach ($lists as $list): ?>
                        <option value="<?= (int) $list->id ?>" <?= (int) $list->id === (int) $card->list_id ? 'selected' : '' ?>><?= e($list->name) ?></option>
                    <?php endforeach ?>
                </select>
            </label>
            <h3><?= e(__('Card items')) ?></h3>
            <?php if (!$read && $myLabels): /* labels are each user's own: a visitor without an account has none */ ?>
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
            <?php endif ?>
            <?php $max = App\Services\AttachmentService::maxBytes(); ?>
            <?php if (!$read): ?>
            <form method="post" action="<?= e(url("/cards/{$card->id}/attachments")) ?>" enctype="multipart/form-data" data-ajax data-autosubmit data-reset>
                <?= csrf_field() ?>
                <label class="side-btn mb-1"><?= component('icon', ['name' => 'clip']) ?> <?= e(__('Attachment')) ?>
                    <input type="file" name="file" class="visually-hidden" data-max-bytes="<?= $max ?>"
                           data-too-big="<?= e(__('The file is too large (max :size).', ['size' => format_bytes($max)])) ?>">
                </label>
                <small class="side-hint"><?= e(__('Maximum size: :size', ['size' => format_bytes($max)])) ?></small>
                <div class="invalid-feedback" data-error-for="file"></div>
            </form>
            <?php endif ?>
            <button class="side-btn side-toggle" type="button" data-switch-toggle="<?= (int) $card->id ?>" aria-pressed="false"
                    title="<?= e(__('Show the checklist instead of the description')) ?>"><?= component('icon', ['name' => 'check-square']) ?> <?= e(__('Checklist')) ?></button>

            <?php if (!$read): ?>
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
            <?php endif ?>
        </aside>
    </div>
</div>
