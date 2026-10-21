<?php /** Dashboard: favourite boards, then all the boards, favourites included (redrawn when a star changes). @var App\Models\Board[] $boards */
$favorites = array_filter($boards, static fn (App\Models\Board $b): bool => (bool) $b->is_favorite); ?>
<div id="dashboard-boards">
    <?php if ($favorites): ?>
        <section class="board-section" data-filter-group>
            <details class="board-section-box" open data-remember="dashboard-favorites" data-remember-in="local">
                <summary class="board-section-title"><?= component('icon', ['name' => 'star']) ?> <?= e(__('Favourites')) ?></summary>
                <div class="board-grid">
                    <?php foreach ($favorites as $board): ?>
                        <?= component('board-tile', ['board' => $board]) ?>
                    <?php endforeach ?>
                </div>
            </details>
        </section>
    <?php endif ?>
    <section class="board-section" data-filter-group>
        <details class="board-section-box" open data-remember="dashboard-boards" data-remember-in="local">
            <summary class="board-section-title"><?= component('icon', ['name' => 'board']) ?> <?= e(__('My boards')) ?></summary>
            <div class="board-grid">
                <button class="board-tile board-tile-new" type="button" data-bs-toggle="modal" data-bs-target="#create-board-modal"><?= component('icon', ['name' => 'plus', 'size' => 22]) ?><span><?= e(__('Create new board…')) ?></span></button>
                <?php foreach ($boards as $board): ?>
                    <?= component('board-tile', ['board' => $board]) ?>
                <?php endforeach ?>
            </div>
        </details>
    </section>
</div>
