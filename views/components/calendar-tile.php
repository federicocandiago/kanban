<?php /** A calendar among the boards: its name, and the service below. @var App\Models\CalendarAccount $account */ ?>
<div class="board-tile" data-search="<?= e(mb_strtolower($account->name . ' ' . $account->label())) ?>"
     data-sort-recent="<?= e((string) $account->updated_at) ?>" data-sort-name="<?= e(mb_strtolower($account->name)) ?>" data-sort-created="<?= e((string) $account->created_at) ?>">
    <a class="board-tile-link calendar-tile" href="<?= e($account->url()) ?>">
        <span class="board-tile-name"><?= e($account->name) ?></span>
        <span class="board-tile-meta"><?= component('icon', ['name' => 'calendar', 'size' => 14]) ?> <span class="text-truncate"><?= e($account->label()) ?></span></span>
    </a>
</div>
