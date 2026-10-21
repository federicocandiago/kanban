<?php /** @var App\Models\Board $board */ ?>
<div class="board-tile" data-search="<?= e(mb_strtolower($board->name)) ?>"
     data-sort-recent="<?= e((string) $board->touched_at) ?>" data-sort-name="<?= e(mb_strtolower($board->name)) ?>" data-sort-created="<?= e((string) $board->created_at) ?>">
    <a class="board-tile-link <?= $board->bg_type === 'photo' ? 'has-photo' : '' ?>" href="<?= e($board->url()) ?>" style="<?= $board->backgroundStyle(true) ?>">
        <span class="board-tile-name"><?= e($board->name) ?></span>
        <span class="board-tile-meta">
            <?php if ($board->touched_at): ?>
                <span title="<?= e(__('Last changed')) ?>"><?= e(format_day((string) $board->touched_at)) ?></span>
            <?php endif ?>
            <?php if ((int) $board->member_count > 1): ?>
                <span title="<?= e(__(':n members', ['n' => (int) $board->member_count])) ?>"><?= component('icon', ['name' => 'users', 'size' => 14]) ?> <?= (int) $board->member_count ?></span>
            <?php endif ?>
        </span>
    </a>
    <?php $label = $board->is_favorite ? __('Remove from favourites') : __('Add to favourites'); ?>
    <button class="board-star <?= $board->is_favorite ? 'is-on' : '' ?>" type="button" data-post="<?= e($board->url('/favorite?dashboard=1')) ?>"
            aria-pressed="<?= $board->is_favorite ? 'true' : 'false' ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"><?= component('icon', ['name' => 'star']) ?></button>
</div>
