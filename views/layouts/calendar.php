<?php /** The calendar month: like a board, on its own background or the user's colour. @var App\Models\CalendarAccount $account */
$theme = auth()->theme(); ?>
<!doctype html>
<html lang="<?= e(locale()) ?>" <?= $theme !== 'auto' ? 'data-bs-theme="' . e($theme) . '"' : '' ?> <?= $account->hasBackground() ? 'style="--tint: ' . e($account->tint()) . '"' : '' ?>>
<head>
    <?= component('head', ['title' => $title ?? null]) ?>
</head>
<body class="board-page is-calendar <?= $account->hasBackground() ? 'has-background' : '' ?> <?= $account->bg_type === 'photo' ? 'has-photo' : '' ?>">
    <div class="board-backdrop" <?= $account->hasBackground() ? 'style="' . $account->backgroundStyle() . '"' : '' ?>></div>
    <?= component('header', ['name' => $account->name, 'hint' => $account->name . ' · ' . $account->label()]) ?>
    <?= component('flash') ?>
    <?= $content ?>
    <?= component('modals') ?>
    <script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('assets/js/app.js') ?>"></script>
    <script src="<?= asset('assets/js/dnd.js') ?>"></script>
    <script src="<?= asset('assets/js/board.js') ?>"></script>
</body>
</html>
