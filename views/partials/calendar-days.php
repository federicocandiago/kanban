<?php /** The days of the month, as lists. @var App\Models\CalendarAccount $account @var array<string, App\Models\Event[]> $days @var DateTimeZone $tz */
$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$view ??= substr((string) array_key_first($days), 0, 7); ?>
<div class="board-lists calendar-days" id="calendar-days">
    <?php foreach ($days as $date => $events): $time = strtotime($date); ?>
        <section class="list day-list <?= $date === $today ? 'is-today' : '' ?> <?= date('N', $time) >= 6 ? 'is-weekend' : '' ?>" id="day-<?= e($date) ?>" data-date="<?= e($date) ?>">
            <header class="list-head day-head">
                <span class="day-name"><?= e(weekday((int) date('N', $time))) ?></span>
                <span class="day-number"><?= (int) date('j', $time) ?></span>
            </header>
            <div class="list-cards" data-cards>
                <?php foreach ($events as $event): ?>
                    <?= partial('event-tile', ['event' => $event, 'date' => $date, 'tz' => $tz]) ?>
                <?php endforeach ?>
            </div>
            <details class="quick-add">
                <summary><?= component('icon', ['name' => 'plus', 'size' => 14]) ?> <?= e(__('Add an event')) ?></summary>
                <form method="post" action="<?= e($account->url("/days/{$date}/events")) ?>" data-ajax data-reset>
                    <?= csrf_field() ?>
                    <input type="hidden" name="view" value="<?= e($view) ?>">
                    <textarea class="quick-add-input" name="title" rows="2" maxlength="500" required placeholder="<?= e(__('Enter a title for this event…')) ?>" aria-label="<?= e(__('Event title')) ?>" data-enter-submit></textarea>
                    <div class="quick-add-actions">
                        <button class="btn btn-success" type="submit"><?= e(__('Add event')) ?></button>
                        <button class="pop-close" type="button" data-close-details aria-label="<?= e(__('Cancel')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>
                    </div>
                </form>
            </details>
        </section>
    <?php endforeach ?>
</div>
