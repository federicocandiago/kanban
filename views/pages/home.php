<section class="hero">
    <div class="hero-text">
        <h1><?= e(__('Everything in order, at a glance.')) ?></h1>
        <p><?= e(__((string) env('APP_DESCRIPTION', ''))) ?></p>
        <?php if (auth()): ?>
            <a href="<?= base_url() ?>/dashboard" class="btn btn-success btn-lg"><?= e(__('Go to your boards')) ?></a>
        <?php elseif (registration_enabled()): ?>
            <a href="<?= base_url() ?>/register" class="btn btn-success btn-lg"><?= e(__('Sign up – it\'s free')) ?></a>
        <?php else: ?>
            <a href="<?= base_url() ?>/login" class="btn btn-success btn-lg"><?= e(__('Sign in')) ?></a>
        <?php endif ?>
    </div>
    <div class="hero-board" aria-hidden="true">
        <?php foreach ([[__('To do'), 3, 'green'], [__('Doing'), 2, 'orange'], [__('Done'), 4, 'blue']] as [$name, $n, $color]): ?>
            <div class="list">
                <div class="list-head"><span class="list-name"><?= e($name) ?></span></div>
                <div class="list-cards">
                    <?php for ($i = 0; $i < $n; $i++): ?>
                        <div class="card-tile"><?php if ($i === 0): ?><span class="card-labels"><span class="label label-<?= $color ?> is-bar"></span></span><?php endif ?><span class="hero-line" style="width: <?= 50 + ($i * 17 % 40) ?>%"></span></div>
                    <?php endfor ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</section>
