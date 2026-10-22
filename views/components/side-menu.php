<?php /** The hamburger menu: calendars, boards, favourites, archive, settings. */
use App\Models\CalendarAccount;

$user = auth();
$favorites = array_filter($user->boards(), static fn ($b) => (bool) $b->is_favorite); ?>
<aside class="offcanvas offcanvas-start side-menu" tabindex="-1" id="side-menu" aria-labelledby="side-menu-title">
    <div class="pop-head">
        <h2 class="pop-title" id="side-menu-title"><?= component('logo', ['size' => 24, 'on' => 'surface']) ?> <?= e(env('APP_NAME')) ?></h2>
        <button type="button" class="pop-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x']) ?></button>
    </div>
    <nav class="side-nav">
        <?php foreach (CalendarAccount::forUser($user) as $calendar): ?>
            <a href="<?= e($calendar->url()) ?>"><?= component('icon', ['name' => 'calendar']) ?> <?= e($calendar->name) ?></a>
        <?php endforeach ?>
        <a href="<?= base_url() ?>/dashboard"><?= component('icon', ['name' => 'board']) ?> <?= e(__('Boards')) ?></a>
        <?php if ($favorites): ?>
            <h3><?= component('icon', ['name' => 'star', 'size' => 14]) ?> <?= e(__('Favourite boards')) ?></h3>
            <?php foreach ($favorites as $board): ?>
                <a class="side-board" href="<?= e($board->url()) ?>"><span class="side-thumb" style="<?= $board->backgroundStyle(true) ?>"></span><?= e($board->name) ?></a>
            <?php endforeach ?>
            <div class="side-sep"></div>
        <?php endif ?>
        <a href="<?= base_url() ?>/dashboard?shared=1"><?= component('icon', ['name' => 'users']) ?> <?= e(__('Shared boards')) ?></a>
        <a href="<?= base_url() ?>/dashboard?archived=1"><?= component('icon', ['name' => 'archive']) ?> <?= e(__('Archived boards')) ?></a>
        <a href="<?= base_url() ?>/account"><?= component('icon', ['name' => 'gear']) ?> <?= e(__('Settings')) ?></a>
        <?php if ($user->isAdmin()): ?>
            <a href="<?= base_url() ?>/admin"><?= component('icon', ['name' => 'shield']) ?> <?= e(__('Back-office')) ?></a>
        <?php endif ?>
        <div class="side-sep"></div>
        <div class="theme-switch" role="group" aria-label="<?= e(__('Theme')) ?>">
            <?php foreach (['auto' => __('Auto'), 'light' => __('Light'), 'dark' => __('Dark')] as $theme => $label): ?>
                <button type="button" class="<?= $user->theme() === $theme ? 'is-on' : '' ?>" data-post="<?= e(url('/account/theme?theme=' . $theme)) ?>"
                        aria-pressed="<?= $user->theme() === $theme ? 'true' : 'false' ?>"><?= component('icon', ['name' => $theme === 'light' ? 'sun' : ($theme === 'dark' ? 'moon' : 'auto'), 'size' => 14]) ?> <?= e($label) ?></button>
            <?php endforeach ?>
        </div>
        <div class="side-sep"></div>
        <form method="post" action="<?= base_url() ?>/logout">
            <?= csrf_field() ?>
            <button type="submit"><?= component('icon', ['name' => 'logout']) ?> <?= e(__('Sign out')) ?></button>
        </form>
    </nav>
</aside>
