<?php /** @var string $name @var string $link @var string $app */ ?>
<?= __('Hi :name,', ['name' => $name]) ?>


<?= __('Thanks for signing up to :app! Open this link to confirm your email address:', ['app' => $app]) ?>


<?= $link ?>


<?= __('If you didn\'t create an account, just ignore this email.') ?>


— <?= $app ?>

