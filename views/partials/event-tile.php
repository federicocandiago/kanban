<?php
/**
 * An event on one day of the month. It is "dimmed" (only openable) when it is recurring, on a
 * read-only calendar, or this is a following day of an event that started earlier.
 * @var App\Models\Event $event @var string $date @var DateTimeZone $tz
 */
$following = $event->start_day < $date;
$dimmed = $following || !$event->isEditable();
$time = $event->timeRange($tz); ?>
<a class="card-tile event-tile <?= $dimmed ? 'is-dimmed' : '' ?>" href="?card=<?= (int) $event->id ?>" data-card-id="<?= (int) $event->id ?>" draggable="false"
   data-search="<?= e(mb_strtolower($event->summary . ' ' . $event->calendar_name . ' ' . $event->sharedBy())) ?>">
    <span class="card-labels">
        <span class="label" style="--lc: <?= e($event->calendar_color ?: '#838c91') ?>"><?= e($event->calendar_name) ?></span>
    </span>
    <?php if ($event->sharedBy()): ?><span class="event-owner"><?= e($event->sharedBy()) ?></span><?php endif ?>
    <span class="card-title"><?= e($event->summary ?: __('(no title)')) ?></span>
    <span class="card-badges">
        <?php if ($time !== '' && !$following): ?>
            <span class="badge-icon"><?= component('icon', ['name' => 'clock', 'size' => 12]) ?> <?= e($time) ?></span>
        <?php elseif ($event->spansDays()): ?>
            <span class="badge-icon"><?= component('icon', ['name' => 'clock', 'size' => 12]) ?> <?= e(format_day((string) $event->start_day) . ' – ' . format_day((string) $event->end_day)) ?></span>
        <?php endif ?>
        <?php if ($event->recurring): ?>
            <span class="badge-icon" title="<?= e(__('Recurring event')) ?>"><?= component('icon', ['name' => 'restore', 'size' => 12]) ?></span>
        <?php endif ?>
        <?php if ($event->description): ?>
            <span class="badge-icon" title="<?= e(__('This event has a description.')) ?>"><?= component('icon', ['name' => 'text', 'size' => 12]) ?></span>
        <?php endif ?>
        <?php if ($event->attachments()): ?>
            <span class="badge-icon" title="<?= e(__('Attachments')) ?>"><?= component('icon', ['name' => 'clip', 'size' => 12]) ?> <?= count($event->attachments()) ?></span>
        <?php endif ?>
    </span>
</a>
