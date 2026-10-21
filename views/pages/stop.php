<?php /** Stop screen for paused accounts. @var string $heading @var string $text */ ?>
<div class="error-page">
    <h1 class="h3 mb-3"><?= e($heading) ?></h1>
    <p class="text-muted"><?= e($text) ?></p>
    <form method="post" action="<?= base_url() ?>/logout"><?= csrf_field() ?><button class="btn btn-secondary mt-3"><?= e(__('Sign out')) ?></button></form>
</div>
