<?php /** @var string $name @var string $board @var string $link @var string $app */ ?>
<?= __('Hello,') ?>


<?= __(':name invited you to work together on the board “:board” on :app. Create a free account with this email address and you will find the invitation in your notifications.', ['name' => $name, 'board' => $board, 'app' => $app]) ?>


<?= $link ?>


<?= __('If you don\'t know :name, just ignore this email.', ['name' => $name]) ?>


— <?= $app ?>

