<footer class="site-footer">
    <div class="container d-flex flex-wrap gap-3 justify-content-center">
        <a href="<?= base_url() ?>/about"><?= e(__('About')) ?></a>
        <a href="<?= base_url() ?>/terms"><?= e(__('Terms of Service')) ?></a>
        <a href="<?= base_url() ?>/privacy"><?= e(__('Privacy Policy')) ?></a>
        <a href="<?= base_url() ?>/cookies"><?= e(__('Cookie Policy')) ?></a>
        <span>
            <?php foreach (App\Core\Translator::available() as $i => $code): ?>
                <?= $i ? '·' : '' ?>
                <a href="<?= base_url() ?>/locale/<?= e($code) ?>" class="<?= $code === locale() ? 'fw-bold' : '' ?>"><?= e(strtoupper($code)) ?></a>
            <?php endforeach ?>
        </span>
    </div>
</footer>
