<?php /** @var string $name @var string $board @var string $link @var string $app */ ?>
<p style="margin:0 0 12px"><?= e(__('Hello,')) ?></p>
<p style="margin:0 0 24px"><?= e(__(':name invited you to work together on the board “:board” on :app. Create a free account with this email address and you will find the invitation in your notifications.', ['name' => $name, 'board' => $board, 'app' => $app])) ?></p>
<p style="margin:0 0 24px"><a href="<?= e($link) ?>" style="display:inline-block;background:#3d9756;color:#fff;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:999px"><?= e(__('Join the board')) ?></a></p>
<p style="margin:0;color:#5e6c84;font-size:12px"><?= e(__('If you don\'t know :name, just ignore this email.', ['name' => $name])) ?></p>
