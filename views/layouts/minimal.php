<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <?= component('head', ['title' => $title ?? null]) ?>
</head>
<body class="page page-minimal">
    <main class="container py-5 text-center" style="max-width: 560px">
        <?= $content ?>
    </main>
</body>
</html>
