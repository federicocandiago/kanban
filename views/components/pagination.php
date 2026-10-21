<?php /** @var int $page @var int $pages @var string $url */
if ($pages <= 1) {
    return;
}
$link = static fn (int $p): string => $url . (str_contains($url, '?') ? '&' : '?') . 'page=' . $p;
?>
<nav class="d-flex gap-1 justify-content-center mt-3" aria-label="<?= e(__('Pages')) ?>">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e($link($p)) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
    <?php endfor ?>
</nav>
