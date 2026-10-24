<?php /** Calendar side menu (the ⋯ in its header, like a board's): rename, background, settings. @var App\Models\CalendarAccount $account */ ?>
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
            <form method="post" action="<?= e($account->url('/background')) ?>" data-ajax data-autosubmit>
                <?= csrf_field() ?>
                <?= component('background-picker', ['id' => 'menu-bg', 'board' => $account]) ?>
                <?php if ($account->bg_type === 'photo' && $account->bg_credit_name): ?>
                    <p class="bg-credit"><?= __('Current photo by <a href=":url" target="_blank" rel="noopener">:name</a> on Unsplash', ['url' => e($account->bg_credit_url), 'name' => e($account->bg_credit_name)]) ?></p>
                <?php endif ?>
            </form>
            <?php if ($account->hasBackground()): ?>
                <button class="menu-link mt-2" type="button" data-post="<?= e($account->url('/background?reset=1')) ?>"><?= component('icon', ['name' => 'restore']) ?> <?= e(__('Back to my colour')) ?></button>
            <?php endif ?>
        </details>

        <div class="menu-section">
            <a class="menu-link" href="<?= base_url() ?>/account#calendar-<?= (int) $account->id ?>"><?= component('icon', ['name' => 'gear']) ?> <?= e(__('Calendar settings')) ?></a>
        </div>
    </div>
</aside>
