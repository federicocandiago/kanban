<?php $theme = auth()?->theme() ?? 'auto'; ?>
<!doctype html>
<html lang="<?= e(locale()) ?>" <?= $theme !== 'auto' ? 'data-bs-theme="' . e($theme) . '"' : '' ?>>
<head>
    <?= component('head', ['title' => $title ?? null, 'expect' => $expect ?? null]) ?>
</head>
<body class="page <?= e($bodyClass ?? '') ?>">
    <?= component('header') ?>
    <main class="page-main">
        <div class="container">
            <?= component('flash') ?>
            <?= $content ?>
        </div>
    </main>
    <?= component('footer') ?>
    <?= component('modals') ?>
    <script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
