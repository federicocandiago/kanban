<?php /** @var bool $archived @var App\Models\Board[] $boards @var App\Models\CalendarAccount[] $calendars */ ?>
<div class="dashboard">
    <div class="dashboard-bar">
        <h1 class="dashboard-title"><?= e($archived ? __('Archived boards') : __('Boards')) ?></h1>
        <?php if ($boards || $calendars): ?>
            <div class="dashboard-tools">
                <label class="search-box">
                    <?= component('icon', ['name' => 'search', 'size' => 14]) ?>
                    <input type="search" placeholder="<?= e(__('Search boards')) ?>" aria-label="<?= e(__('Search boards')) ?>" data-filter=".dashboard [data-search]">
                </label>
                <?php if (!$archived): ?>
                    <div class="dropdown sort-menu" data-sort=".dashboard .board-grid" data-sort-key="dashboard">
                        <button class="sort-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('Order by')) ?>">
                            <?= component('icon', ['name' => 'sort', 'size' => 14]) ?> <span data-sort-label><?= e(__('Last changed')) ?></span> <?= component('icon', ['name' => 'chevron', 'size' => 12]) ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end pop sort-pop">
                            <div class="pop-head"><h2 class="pop-title"><?= e(__('Order by')) ?></h2></div>
                            <?php foreach (['recent' => [__('Last changed'), true], 'name' => [__('Name'), false], 'created' => [__('Date created'), true]] as $by => [$label, $desc]): ?>
                                <button class="dropdown-item <?= $by === 'recent' ? 'is-on' : '' ?>" type="button" role="menuitemradio" aria-checked="<?= $by === 'recent' ? 'true' : 'false' ?>"
                                        data-sort-by="<?= e($by) ?>" <?= $desc ? 'data-desc' : '' ?>><?= component('icon', ['name' => 'check', 'size' => 14]) ?> <?= e($label) ?></button>
                            <?php endforeach ?>
                        </div>
                    </div>
                <?php endif ?>
            </div>
        <?php endif ?>
    </div>

    <?php if ($archived): ?>
        <?php if (!$boards): ?>
            <div class="empty-panel">
                <?= component('icon', ['name' => 'archive', 'size' => 28]) ?>
                <h2><?= e(__('No archived boards.')) ?></h2>
                <p><?= e(__('Archived boards are hidden from everyone. The owner can restore them or delete them for good.')) ?></p>
                <a class="btn btn-secondary" href="<?= base_url() ?>/dashboard"><?= e(__('Back to boards')) ?></a>
            </div>
        <?php endif ?>
        <div class="archived-boards">
            <?php foreach ($boards as $board): ?>
                <div class="archived-row" data-search="<?= e(mb_strtolower($board->name)) ?>">
                    <span class="archived-thumb" style="<?= $board->backgroundStyle(true) ?>"></span>
                    <div class="flex-grow-1 min-w-0">
                        <strong class="d-block text-truncate"><?= e($board->name) ?></strong>
                        <small class="text-muted"><?= e(__('Archived on :date', ['date' => format_date($board->archived_at)])) ?></small>
                    </div>
                    <?php if ($board->isOwner()): ?>
                        <button class="btn btn-sm btn-secondary" type="button" data-post="<?= e($board->url('/restore')) ?>"><?= component('icon', ['name' => 'restore', 'size' => 14]) ?> <?= e(__('Restore')) ?></button>
                        <button class="btn btn-sm btn-danger" type="button" data-action="<?= e($board->url('/delete')) ?>"
                                data-confirm="<?= e(__('The board “:name”, its lists, cards and attachments will be deleted for everyone. This cannot be undone.', ['name' => $board->name])) ?>"><?= component('icon', ['name' => 'trash', 'size' => 14]) ?> <?= e(__('Delete')) ?></button>
                    <?php else: ?>
                        <button class="btn btn-sm btn-secondary" type="button" data-action="<?= e($board->url('/leave')) ?>"
                                data-confirm="<?= e(__('You will no longer see the board “:name”.', ['name' => $board->name])) ?>"><?= e(__('Leave')) ?></button>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php else: ?>
        <?php if ($calendars): /* calendars are added from Settings */ ?>
        <section class="board-section" data-filter-group>
            <details class="board-section-box" open data-remember="dashboard-calendars" data-remember-in="local">
                <summary class="board-section-title"><?= component('icon', ['name' => 'calendar']) ?> <?= e(__('Calendars')) ?></summary>
                <div class="board-grid">
                    <?php foreach ($calendars as $calendar): ?>
                        <?= component('calendar-tile', ['account' => $calendar]) ?>
                    <?php endforeach ?>
                </div>
            </details>
        </section>
        <?php endif ?>
        <?= partial('dashboard-boards', ['boards' => $boards]) ?>
    <?php endif ?>
</div>
<span id="dashboard-end" hidden></span>
