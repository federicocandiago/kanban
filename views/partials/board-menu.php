<?php /** Board side menu (Trello's "Show menu"): background, archived items, archive / leave. @var App\Models\Board $board */ ?>
<aside class="offcanvas offcanvas-end board-menu" tabindex="-1" id="board-menu" aria-labelledby="board-menu-title">
    <div class="pop-head">
        <h2 class="pop-title" id="board-menu-title"><?= e(__('Menu')) ?></h2>
        <button type="button" class="pop-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x']) ?></button>
    </div>
    <div class="board-menu-body">
        <div class="menu-section">
            <button class="menu-link" type="button" data-bs-toggle="modal" data-bs-target="#rename-modal"><?= component('icon', ['name' => 'text']) ?> <?= e(__('Rename')) ?></button>
        </div>
        <details class="menu-section">
            <summary><?= component('icon', ['name' => 'image']) ?> <?= e(__('Change background')) ?></summary>
            <form method="post" action="<?= e($board->url('/update')) ?>" data-ajax data-autosubmit>
                <?= csrf_field() ?>
                <?= component('background-picker', ['id' => 'menu-bg', 'board' => $board]) ?>
                <?php if ($board->bg_type === 'photo' && $board->bg_credit_name): ?>
                    <p class="bg-credit"><?= __('Current photo by <a href=":url" target="_blank" rel="noopener">:name</a> on Unsplash', ['url' => e($board->bg_credit_url), 'name' => e($board->bg_credit_name)]) ?></p>
                <?php endif ?>
            </form>
        </details>

        <details class="menu-section">
            <summary><?= component('icon', ['name' => 'archive']) ?> <?= e(__('Archived items')) ?></summary>
            <?= partial('archived', ['board' => $board]) ?>
        </details>

        <div class="menu-section">
            <a class="menu-link" href="<?= base_url() ?>/account#labels"><?= component('icon', ['name' => 'tag']) ?> <?= e(__('My labels')) ?></a>
            <?php if ($board->isOwner()): ?>
                <button class="menu-link" type="button" data-action="<?= e($board->url('/archive')) ?>"
                        data-confirm="<?= e(__('The board will be hidden from you and its members. You can restore it from «Archived boards».')) ?>">
                    <?= component('icon', ['name' => 'archive']) ?> <?= e(__('Archive board')) ?></button>
            <?php else: ?>
                <button class="menu-link" type="button" data-action="<?= e($board->url('/leave')) ?>"
                        data-confirm="<?= e(__('You will no longer see the board “:name”.', ['name' => $board->name])) ?>">
                    <?= component('icon', ['name' => 'leave']) ?> <?= e(__('Leave board')) ?></button>
            <?php endif ?>
        </div>
    </div>
</aside>
