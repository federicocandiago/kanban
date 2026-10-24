<?php /** Full-screen board: the background covers the page, lists scroll sideways. @var App\Models\Board $board */
$theme = auth()?->theme() ?? 'auto';
$access ??= 'member'; ?>
<!doctype html>
<html lang="<?= e(locale()) ?>" <?= $theme !== 'auto' ? 'data-bs-theme="' . e($theme) . '"' : '' ?> style="--tint: <?= e($board->tint()) ?>">
<head>
    <?= component('head', ['title' => $title ?? null]) ?>
</head>
<body class="board-page <?= $board->bg_type === 'photo' ? 'has-photo' : '' ?>">
    <div class="board-backdrop" style="<?= $board->backgroundStyle() ?>"></div>
    <?= component('header', ['name' => $board->name, 'newCard' => $access !== 'read' ? $board->url('/cards/new') : null]) ?>
    <?= component('flash') ?>
    <?= $content ?>
    <?= component('modals') ?>
    <script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('assets/js/app.js') ?>"></script>
    <script src="<?= asset('assets/js/dnd.js') ?>"></script>
    <script src="<?= asset('assets/js/board.js') ?>"></script>
</body>
</html>
