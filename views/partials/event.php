<?php
/**
 * Event back, loaded into the modal: same layout as a card. Its fields belong to one form that
 * saves itself on every change; recurring events and read-only calendars only show it.
 * @var App\Models\Event $event @var App\Models\CalendarAccount $account @var App\Models\Calendar[] $calendars
 * @var DateTimeZone $tz @var string|null $view
 */
$form = 'event-form-' . (int) $event->id;
$editable = $event->isEditable();
$off = $editable ? '' : 'disabled';
$start = $event->start($tz);
$end = $event->all_day ? $event->end($tz)->modify('-1 day') : $event->end($tz); // whole days: show the last day, not the exclusive end
$query = $view ? '?view=' . rawurlencode($view) : '';
$minutes = auth()->eventMinutes();
$defaultEnd = (new DateTimeImmutable('09:00'))->modify("+{$minutes} minutes")->format('H:i'); ?>
<div class="card-back" id="card-panel" data-card-id="<?= (int) $event->id ?>">
    <form id="<?= $form ?>" method="post" action="<?= e(url("/events/{$event->id}/update")) ?>" data-ajax data-autosubmit data-duration="<?= $minutes ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="view" value="<?= e($view) ?>">
    </form>
    <button type="button" class="pop-close card-back-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>

    <?php if (!$editable): ?>
        <div class="card-back-archived">
            <?= component('icon', ['name' => $event->recurring ? 'restore' : 'shield']) ?>
            <?= e($event->recurring ? __('Recurring event: change it from your calendar app.') : __('This calendar is read-only.')) ?>
        </div>
    <?php endif ?>

    <div class="card-back-head">
        <?= component('icon', ['name' => 'clock', 'size' => 20]) ?>
        <div class="flex-grow-1">
            <?php if ($event->sharedBy()): ?><span class="event-owner"><?= e($event->sharedBy()) ?></span><?php endif ?>
            <textarea class="card-back-title" name="title" form="<?= $form ?>" rows="1" maxlength="500" required data-enter-submit <?= $off ?> aria-label="<?= e(__('Event title')) ?>"><?= e($event->summary) ?></textarea>
            <div class="invalid-feedback" data-error-for="title"></div>
        </div>
    </div>

    <div class="card-back-grid">
        <div class="card-back-main">
            <div class="card-back-labels">
                <?php if ($editable && count($calendars) > 1): /* the pill is also where the calendar is changed */ ?>
                    <div class="dropdown">
                        <button class="label calendar-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="--lc: <?= e($event->calendar_color ?: '#838c91') ?>"
                                title="<?= e(__('Change calendar')) ?>"><?= e($event->calendar_name) ?> <?= component('icon', ['name' => 'chevron', 'size' => 12]) ?></button>
                        <div class="dropdown-menu pop label-picker">
                            <div class="pop-head"><span class="pop-title"><?= e(__('Calendar')) ?></span></div>
                            <div class="pop-body">
                                <?php foreach ($calendars as $calendar): ?>
                                    <label class="label-option">
                                        <input type="radio" name="calendar_id" value="<?= (int) $calendar->id ?>" form="<?= $form ?>" <?= (int) $calendar->id === (int) $event->calendar_id ? 'checked' : '' ?>>
                                        <span class="label" style="--lc: <?= e($calendar->color()) ?>"><?= e($calendar->name) ?></span>
                                        <?= component('icon', ['name' => 'check', 'size' => 14]) ?>
                                    </label>
                                <?php endforeach ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="label" style="--lc: <?= e($event->calendar_color ?: '#838c91') ?>"><?= e($event->calendar_name) ?></span>
                <?php endif ?>
            </div>

            <div class="card-dates event-dates">
                <label>
                    <span class="card-back-label"><?= e(__('Start')) ?></span>
                    <span class="d-flex gap-1">
                        <input class="form-control form-control-sm" type="date" name="start_date" form="<?= $form ?>" value="<?= e($start->format('Y-m-d')) ?>" required <?= $off ?>>
                        <input class="form-control form-control-sm" type="time" name="start_time" form="<?= $form ?>" value="<?= e($event->all_day ? '09:00' : $start->format('H:i')) ?>" <?= $off ?>>
                    </span>
                    <span class="invalid-feedback" data-error-for="start_date"></span>
                </label>
                <label>
                    <span class="card-back-label"><?= e(__('End')) ?></span>
                    <span class="d-flex gap-1">
                        <input class="form-control form-control-sm" type="date" name="end_date" form="<?= $form ?>" value="<?= e($end->format('Y-m-d')) ?>" required <?= $off ?>>
                        <input class="form-control form-control-sm" type="time" name="end_time" form="<?= $form ?>" value="<?= e($event->all_day ? $defaultEnd : $end->format('H:i')) ?>" <?= $off ?>>
                    </span>
                    <span class="invalid-feedback" data-error-for="end_date"></span>
                </label>
            </div>
            <label class="form-check form-switch event-all-day">
                <input class="form-check-input" type="checkbox" role="switch" name="all_day" value="1" form="<?= $form ?>" <?= $event->all_day ? 'checked' : '' ?> <?= $off ?>>
                <span class="form-check-label"><?= e(__('All day')) ?></span>
            </label>

            <section class="card-section">
                <h3><?= component('icon', ['name' => 'text']) ?> <?= e(__('Description')) ?></h3>
                <textarea class="form-control card-description" name="description" form="<?= $form ?>" rows="4" <?= $off ?>
                          placeholder="<?= e($editable ? __('Add a more detailed description…') : '') ?>"><?= e($event->description) ?></textarea>
            </section>

            <?= partial('event-attachments', compact('event', 'account', 'view')) ?>
            <?php if ($editable): ?><button class="btn btn-success side-confirm confirm-inline" type="submit" form="<?= $form ?>" data-close><?= component('icon', ['name' => 'check']) ?> <?= e(__('Confirm')) ?></button><?php endif /* phones only: in reach before the log */ ?>
            <?= partial('event-activity', compact('event', 'account')) ?>
        </div>

        <aside class="card-back-side">
            <?php if ($editable): ?>
                <?php if ($account->attachments): ?>
                    <h3><?= e(__('Edit the event')) ?></h3>
                    <?php $max = App\Services\AttachmentService::maxBytes(); ?>
                    <form method="post" action="<?= e(url("/events/{$event->id}/attachments")) ?>" enctype="multipart/form-data" data-ajax data-autosubmit data-reset>
                        <?= csrf_field() ?>
                        <input type="hidden" name="view" value="<?= e($view) ?>">
                        <label class="side-btn mb-1"><?= component('icon', ['name' => 'clip']) ?> <?= e(__('Attachment')) ?>
                            <input type="file" name="file" class="visually-hidden" data-max-bytes="<?= $max ?>"
                                   data-too-big="<?= e(__('The file is too large (max :size).', ['size' => format_bytes($max)])) ?>">
                        </label>
                        <small class="side-hint"><?= e(__('Maximum size: :size', ['size' => format_bytes($max)])) ?></small>
                        <div class="invalid-feedback" data-error-for="file"></div>
                    </form>
                <?php endif ?>
                <div class="side-bottom">
                    <button class="btn btn-success side-confirm" type="submit" form="<?= $form ?>" data-close><?= component('icon', ['name' => 'check']) ?> <?= e(__('Confirm')) ?></button>
                    <h3><?= e(__('Actions')) ?></h3>
                    <button class="side-btn is-danger" type="button" data-action="<?= e(url("/events/{$event->id}/delete") . $query) ?>"
                            data-confirm="<?= e(__('The event “:name” will be deleted from your calendar, on every device.', ['name' => $event->summary])) ?>"><?= component('icon', ['name' => 'trash']) ?> <?= e(__('Delete')) ?></button>
                </div>
            <?php endif ?>
        </aside>
    </div>
</div>
