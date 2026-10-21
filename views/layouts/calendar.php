<?php /** The calendar month: like a board, on the user's colour. */
$theme = auth()->theme(); ?>
<!doctype html>
<html lang="<?= e(locale()) ?>" <?= $theme !== 'auto' ? 'data-bs-theme="' . e($theme) . '"' : '' ?>>
<head>
    <?= component('head', ['title' => $title ?? null]) ?>
</head>
<body class="board-page is-calendar">
    <div class="board-backdrop"></div>
    <?= component('header') ?>
    <?= component('flash') ?>
    <?= $content ?>
    <?= component('modals') ?>
    <script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('assets/js/app.js') ?>"></script>
    <script src="<?= asset('assets/js/dnd.js') ?>"></script>
    <script src="<?= asset('assets/js/board.js') ?>"></script>
</body>
</html>
