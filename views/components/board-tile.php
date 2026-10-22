<?php /** @var App\Models\Board $board @var bool|null $redraw false: a star click only lights the star (no dashboard sections to redraw) */ ?>
<div class="board-tile" data-search="<?= e(mb_strtolower($board->name)) ?>"
     data-sort-recent="<?= e((string) $board->touched_at) ?>" data-sort-name="<?= e(mb_strtolower($board->name)) ?>" data-sort-created="<?= e((string) $board->created_at) ?>">
    <a class="board-tile-link <?= $board->bg_type === 'photo' ? 'has-photo' : '' ?>" href="<?= e($board->url()) ?>" style="<?= $board->backgroundStyle(true) ?>">
        <span class="board-tile-name"><?= e($board->name) ?></span>
        <span class="board-tile-meta">
            <?php if ($share = $board->shareLabel()): ?>
                <span class="tile-share" title="<?= e($board->isOwner() ? __(':n members', ['n' => (int) $board->member_count]) : __('Owner: :name', ['name' => (string) $board->owner_name])) ?>"><?= component('icon', ['name' => 'users', 'size' => 13]) ?> <span><?= e($share) ?><?= $board->isOwner() && (int) $board->member_count > 2 ? ' · ' . (int) $board->member_count : '' ?></span></span>
            <?php endif ?>
            <?php if ($board->touched_at): ?>
                <span class="tile-date" title="<?= e(__('Last changed')) ?>"><?= e(format_day((string) $board->touched_at)) ?></span>
            <?php endif ?>
        </span>
    </a>
    <?php $label = $board->is_favorite ? __('Remove from favourites') : __('Add to favourites'); ?>
    <button class="board-star <?= $board->is_favorite ? 'is-on' : '' ?>" type="button" data-post="<?= e($board->url(($redraw ?? true) ? '/favorite?dashboard=1' : '/favorite')) ?>"
            aria-pressed="<?= $board->is_favorite ? 'true' : 'false' ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"><?= component('icon', ['name' => 'star']) ?></button>
</div>
