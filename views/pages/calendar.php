<?php /** @var App\Models\CalendarAccount $account @var string $month @var array $days @var DateTimeZone $tz */
[$y, $m] = array_map('intval', explode('-', $month));
$prev = date('Y-m', mktime(0, 0, 0, $m - 1, 1, $y));
$next = date('Y-m', mktime(0, 0, 0, $m + 1, 1, $y)); ?>
<div class="board-header">
    <div class="calendar-title">
        <h1><?= e($account->name) ?></h1>
        <small><?= e($account->label()) ?></small>
    </div>
    <a class="board-btn calendar-today" href="<?= e($account->url()) ?>" data-today title="<?= e(__('Today')) ?>" aria-label="<?= e(__('Today')) ?>"><?= component('icon', ['name' => 'target']) ?></a>
    <span class="board-sep"></span>
    <a class="board-btn" href="?month=<?= e($prev) ?>" title="<?= e(__('Previous month')) ?>" aria-label="<?= e(__('Previous month')) ?>">‹</a>
    <span class="calendar-month"><span class="month-long"><?= e(month_name($m) . ' ' . $y) ?></span><span class="month-short"><?= e(mb_strtolower(mb_substr(month_name($m), 0, 3)) . ' ' . $y) ?></span></span>
    <a class="board-btn" href="?month=<?= e($next) ?>" title="<?= e(__('Next month')) ?>" aria-label="<?= e(__('Next month')) ?>">›</a>
    <div class="board-filter ms-auto" data-expand>
        <button class="board-btn" type="button" title="<?= e(__('Filter events')) ?>" aria-label="<?= e(__('Filter events')) ?>"><?= component('icon', ['name' => 'search']) ?></button>
        <?= component('icon', ['name' => 'search', 'size' => 14]) ?>
        <input type="search" placeholder="<?= e(__('Filter events')) ?>" aria-label="<?= e(__('Filter events')) ?>" data-filter=".board-canvas [data-search]">
    </div>
    <span class="board-btn calendar-sync" data-sync-state title="<?= e(__('Last sync: :date', ['date' => format_date($account->synced_at)])) ?>"><?= component('icon', ['name' => 'restore']) ?></span>
    <a class="board-btn calendar-settings" href="<?= base_url() ?>/account#calendar-<?= (int) $account->id ?>" title="<?= e(__('Calendar settings')) ?>" aria-label="<?= e(__('Calendar settings')) ?>"><?= component('icon', ['name' => 'gear']) ?></a>
</div>

<div class="board-canvas" data-cards-url="<?= e(url('/events/')) ?>" data-view="<?= e($month) ?>" data-start-offset="<?= (int) env('CALENDAR_START_OFFSET_DAYS', -1) ?>"
     data-sync="<?= e($account->url('/sync?month=' . $month)) ?>">
    <?= partial('calendar-days', ['account' => $account, 'days' => $days, 'tz' => $tz, 'view' => $month]) ?>
</div>

<div class="modal fade" id="card-modal" tabindex="-1" aria-label="<?= e(__('Event')) ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content card-window" data-card-content></div>
    </div>
</div>
