<?php /** The shared boards, reached from the side menu: with me, by me. @var App\Models\Board[] $withMe $byMe */ ?>
<div class="dashboard">
    <div class="dashboard-bar">
        <h1 class="dashboard-title"><?= e(__('Shared boards')) ?></h1>
        <?php if ($withMe || $byMe): ?>
            <div class="dashboard-tools">
                <label class="search-box">
                    <?= component('icon', ['name' => 'search', 'size' => 14]) ?>
                    <input type="search" placeholder="<?= e(__('Search boards')) ?>" aria-label="<?= e(__('Search boards')) ?>" data-filter=".dashboard [data-search]">
                </label>
            </div>
        <?php endif ?>
    </div>

    <?php if (!$withMe && !$byMe): ?>
        <div class="empty-panel">
            <?= component('icon', ['name' => 'users', 'size' => 28]) ?>
            <h2><?= e(__('No shared boards.')) ?></h2>
            <p><?= e(__('Share a board from its Share button: the people you invite appear here, and so do the boards others share with you.')) ?></p>
            <a class="btn btn-secondary" href="<?= base_url() ?>/dashboard"><?= e(__('Back to boards')) ?></a>
        </div>
    <?php endif ?>

    <?php foreach ([['shared-with-me', 'users', __('Shared with me (boards)'), $withMe], ['shared-by-me', 'share', __('Shared by me'), $byMe]] as [$key, $icon, $heading, $list]):
        if (!$list) continue; ?>
        <section class="board-section" data-filter-group>
            <details class="board-section-box" open data-remember="<?= $key ?>" data-remember-in="local">
                <summary class="board-section-title"><?= component('icon', ['name' => $icon]) ?> <?= e($heading) ?></summary>
                <div class="board-grid">
                    <?php foreach ($list as $board): ?>
                        <?= component('board-tile', ['board' => $board, 'redraw' => false]) ?>
                    <?php endforeach ?>
                </div>
            </details>
        </section>
    <?php endforeach ?>
</div>
