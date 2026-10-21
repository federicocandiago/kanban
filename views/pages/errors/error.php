<div class="error-page">
    <p class="error-code"><?= (int) $status ?></p>
    <h1 class="h3 mb-3"><?= e($title) ?></h1>
    <?php if ($detail): ?><p class="text-muted small text-break"><?= e($detail) ?></p><?php endif ?>
    <a class="btn btn-success mt-3" href="<?= base_url() ?>/"><?= e(__('Back to home')) ?></a>
</div>
