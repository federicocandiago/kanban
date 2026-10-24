<?php
/**
 * Top bar: menu and boards on the left, logo in the middle, create / notifications / settings (the avatar) on the right.
 * A board or a calendar passes its $name (shown in the middle instead of the logo, on one line) and a board its
 * $newCard URL: the + that opens the "New card" window takes the place of the boards button.
 * @var string|null $name @var string|null $hint @var string|null $newCard
 */
$user = auth();
$name ??= null;
$hint ??= null;
$newCard ??= null; ?>
<header class="topbar <?= $user ? '' : 'is-guest' ?>" id="topbar">
    <?php if ($user): ?>
        <div class="topbar-group">
            <button class="topbar-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#side-menu" aria-controls="side-menu" aria-label="<?= e(__('Menu')) ?>"><?= component('icon', ['name' => 'menu']) ?></button>
            <?php if ($newCard): ?>
                <button class="topbar-btn" type="button" data-new-card="<?= e($newCard) ?>" title="<?= e(__('New card')) ?>" aria-label="<?= e(__('New card')) ?>"><?= component('icon', ['name' => 'plus']) ?></button>
            <?php else: ?>
                <a class="topbar-btn" href="<?= base_url() ?>/dashboard" title="<?= e(__('Boards')) ?>" aria-label="<?= e(__('Boards')) ?>"><?= component('icon', ['name' => 'board']) ?></a>
            <?php endif ?>
        </div>
    <?php endif ?>

    <?php if ($name !== null): ?>
        <?= component('topbar-title', ['name' => $name, 'hint' => $hint]) ?>
    <?php else: ?>
        <a class="topbar-logo" href="<?= base_url() ?>/<?= $user ? 'dashboard' : '' ?>"><?= component('logo') ?><span><?= e(env('APP_NAME')) ?></span></a>
    <?php endif ?>

    <div class="topbar-group ms-auto">
        <?php if ($user): ?>
            <button class="topbar-btn" type="button" data-bs-toggle="modal" data-bs-target="#search-modal" aria-label="<?= e(__('Search')) ?>" title="<?= e(__('Search')) ?> (/)"><?= component('icon', ['name' => 'search']) ?></button>
            <?php if (!$newCard): /* on a board the + on the left makes cards: one + is enough */ ?>
            <button class="topbar-btn topbar-create" type="button" data-bs-toggle="modal" data-bs-target="#create-board-modal" aria-label="<?= e(__('Create board')) ?>" title="<?= e(__('Create board')) ?>"><?= component('icon', ['name' => 'plus']) ?></button>
            <?php endif ?>
            <?= component('notifications', ['user' => $user]) ?>
            <a class="topbar-avatar" href="<?= base_url() ?>/account" title="<?= e(__('Settings')) ?>" aria-label="<?= e(__('Settings')) ?>">
                <?= component('avatar', ['user' => $user, 'size' => 40]) ?>
            </a>
        <?php else: ?>
            <a class="topbar-btn" href="<?= base_url() ?>/login"><?= e(__('Sign in')) ?></a>
            <?php if (registration_enabled()): ?>
                <a class="topbar-btn is-strong" href="<?= base_url() ?>/register"><?= e(__('Sign up')) ?></a>
            <?php endif ?>
        <?php endif ?>
    </div>
</header>
