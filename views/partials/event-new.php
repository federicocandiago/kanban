<?php
/**
 * New timed event from quick add, in the event window: nothing is saved until "Confirm".
 * @var App\Models\CalendarAccount $account @var string $title @var DateTimeImmutable $start @var DateTimeImmutable $end
 * @var App\Models\Calendar $calendar the preselected one @var App\Models\Calendar[] $calendars @var string $view
 */
$form = 'event-new-form';
$minutes = auth()->eventMinutes(); ?>
<div class="card-back" id="card-panel">
    <form id="<?= $form ?>" method="post" action="<?= e($account->url('/events')) ?>" data-ajax data-duration="<?= $minutes ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <input type="hidden" name="nonce" value="<?= e(uuid()) ?>"><?php /* one event per window, even if Confirm arrives twice */ ?>
    </form>
    <button type="button" class="pop-close card-back-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>

    <div class="card-back-head">
        <?= component('icon', ['name' => 'clock', 'size' => 20]) ?>
        <div class="flex-grow-1">
            <textarea class="card-back-title" name="title" form="<?= $form ?>" rows="1" maxlength="500" required data-enter-submit aria-label="<?= e(__('Event title')) ?>"><?= e($title) ?></textarea>
            <div class="invalid-feedback" data-error-for="title"></div>
            <p class="card-back-list"><?= e(__('New event: it is saved when you create it.')) ?></p>
        </div>
    </div>

    <div class="card-back-grid">
        <div class="card-back-main">
            <span class="card-back-label"><?= e(__('Calendar')) ?></span>
            <div class="card-back-labels calendar-choice">
                <?php foreach ($calendars as $option): ?>
                    <label>
                        <input class="visually-hidden" type="radio" name="calendar_id" value="<?= (int) $option->id ?>" form="<?= $form ?>" <?= (int) $option->id === (int) $calendar->id ? 'checked' : '' ?>>
                        <span class="label" style="--lc: <?= e($option->color()) ?>"><?= e($option->name) ?></span>
                    </label>
                <?php endforeach ?>
            </div>
            <div class="invalid-feedback" data-error-for="calendar_id"></div>

            <div class="card-dates event-dates">
                <label>
                    <span class="card-back-label"><?= e(__('Start')) ?></span>
                    <span class="d-flex gap-1">
                        <input class="form-control form-control-sm" type="date" name="start_date" form="<?= $form ?>" value="<?= e($start->format('Y-m-d')) ?>" required>
                        <input class="form-control form-control-sm" type="time" name="start_time" form="<?= $form ?>" value="<?= e($start->format('H:i')) ?>" autofocus>
                    </span>
                    <span class="invalid-feedback" data-error-for="start_date"></span>
                </label>
                <label>
                    <span class="card-back-label"><?= e(__('End')) ?></span>
                    <span class="d-flex gap-1">
                        <input class="form-control form-control-sm" type="date" name="end_date" form="<?= $form ?>" value="<?= e($end->format('Y-m-d')) ?>" required>
                        <input class="form-control form-control-sm" type="time" name="end_time" form="<?= $form ?>" value="<?= e($end->format('H:i')) ?>">
                    </span>
                    <span class="invalid-feedback" data-error-for="end_date"></span>
                </label>
            </div>
            <label class="form-check form-switch event-all-day">
                <input class="form-check-input" type="checkbox" role="switch" name="all_day" value="1" form="<?= $form ?>">
                <span class="form-check-label"><?= e(__('All day')) ?></span>
            </label>

            <section class="card-section">
                <h3><?= component('icon', ['name' => 'text']) ?> <?= e(__('Description')) ?></h3>
                <textarea class="form-control card-description" name="description" form="<?= $form ?>" rows="4" placeholder="<?= e(__('Add a more detailed description…')) ?>"></textarea>
            </section>
            <button class="btn btn-success side-confirm confirm-inline" type="submit" form="<?= $form ?>"><?= component('icon', ['name' => 'plus']) ?> <?= e(__('Create event')) ?></button><?php /* phones only: right under the description */ ?>
        </div>

        <aside class="card-back-side">
            <div class="side-bottom">
                <button class="btn btn-success side-confirm" type="submit" form="<?= $form ?>"><?= component('icon', ['name' => 'plus']) ?> <?= e(__('Create event')) ?></button>
            </div>
        </aside>
    </div>
</div>
