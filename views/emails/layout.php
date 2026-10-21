<?php /** HTML email frame (Trello 2018 colours). @var string $app @var string $header colour of the header @var string $body */ ?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<body style="margin:0;padding:32px 16px;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#172b4d">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border-radius:20px;box-shadow:0 1px 0 rgba(9,30,66,.25)">
            <tr><td style="padding:16px 32px;background:<?= e($header) ?>;border-radius:20px 20px 0 0;color:#fff;font-size:18px;font-weight:700"><img src="<?= e(absolute_url('/assets/icons/logo-64.png')) ?>" width="28" height="28" alt="" style="vertical-align:-8px;margin-right:8px;border:0"><?= e($app) ?></td></tr>
            <tr><td style="padding:24px 32px 32px;font-size:15px;line-height:1.6"><?= $body ?></td></tr>
        </table>
    </td></tr></table>
</body>
</html>
