<?php /** @var string $name @var string $link @var string $app */ ?>
<p style="margin:0 0 12px"><?= e(__('Hi :name,', ['name' => $name])) ?></p>
<p style="margin:0 0 24px"><?= e(__('Thanks for signing up to :app! Open this link to confirm your email address:', ['app' => $app])) ?></p>
<p style="margin:0 0 24px"><a href="<?= e($link) ?>" style="display:inline-block;background:#3d9756;color:#fff;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:999px"><?= e(__('Confirm my email')) ?></a></p>
<p style="margin:0 0 8px;color:#5e6c84;font-size:12px"><?= e(__('If the button doesn\'t work, copy this address into your browser:')) ?><br><span style="word-break:break-all"><?= e($link) ?></span></p>
<p style="margin:0;color:#5e6c84;font-size:12px"><?= e(__('If you didn\'t create an account, just ignore this email.')) ?></p>
