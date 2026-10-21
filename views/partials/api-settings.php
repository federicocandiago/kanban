<?php
/**
 * Settings › API access: the switch, then the token (one per user). $secret is the token just
 * generated, shown only in this answer. @var App\Models\User $user @var string|null $secret
 */
use App\Models\ApiToken;

$token = ApiToken::forUser($user);
$secret ??= null; ?>
<div id="api-panel">
    <form method="post" action="<?= base_url() ?>/account/api" data-ajax data-autosubmit>
        <?= csrf_field() ?>
        <input type="hidden" name="enabled" value="0">
        <label class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" <?= $user->api_enabled ? 'checked' : '' ?>
                   <?php if ($user->api_enabled && $token): ?>data-ask-off="<?= e(__('The token will be deleted and the scripts using it will stop working.')) ?>"<?php endif ?>>
            <span class="form-check-label"><?= e(__('Turn on API access')) ?></span>
        </label>
    </form>

    <?php if ($user->api_enabled): ?>
        <?php if ($secret): ?>
            <div class="api-secret">
                <label class="form-label" for="api-secret"><?= e(__('Your new token')) ?></label>
                <div class="d-flex gap-2">
                    <input class="form-control mono" id="api-secret" value="<?= e($secret) ?>" readonly>
                    <button class="btn btn-secondary" type="button" data-copy="#api-secret" data-copied="<?= e(__('Copied.')) ?>"><?= e(__('Copy')) ?></button>
                </div>
                <p class="form-text"><?= e(__('Copy it now and keep it somewhere safe: for security it is not stored and will not be shown again.')) ?></p>
            </div>
        <?php endif ?>

        <?php if ($token): ?>
            <dl class="api-token">
                <dt><?= e(__('Token')) ?></dt><dd class="mono"><?= e($token->masked()) ?></dd>
                <dt><?= e(__('Can')) ?></dt><dd><?= e($token->canWrite() ? __('read and change') : __('only read')) ?></dd>
                <dt><?= e(__('Created')) ?></dt><dd><?= e(format_date($token->created_at)) ?></dd>
                <dt><?= e(__('Expires')) ?></dt>
                <dd><?= e(format_date($token->expires_at)) ?><?php if ($token->isExpired()): ?> <span class="badge text-bg-danger"><?= e(__('expired')) ?></span><?php endif ?></dd>
                <dt><?= e(__('Last used')) ?></dt><dd><?= e(format_date($token->last_used_at)) ?></dd>
            </dl>
        <?php endif ?>

        <form method="post" action="<?= base_url() ?>/account/api/token" data-ajax
              <?php if ($token): ?>data-ask="<?= e(__('A new token replaces the current one at once: the scripts using it will stop working until you update them.')) ?>"<?php endif ?>>
            <?= csrf_field() ?>
            <div class="d-flex flex-wrap gap-3 mb-3">
                <?php foreach (['read' => __('Only read'), 'write' => __('Read and change')] as $scope => $label): ?>
                    <label class="form-check">
                        <input class="form-check-input" type="radio" name="scope" value="<?= $scope ?>" <?= ($token?->scope ?? 'read') === $scope ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= e($label) ?></span>
                    </label>
                <?php endforeach ?>
            </div>
            <div class="invalid-feedback" data-error-for="scope"></div>
            <button class="btn btn-success" type="submit"><?= e($token ? __('Regenerate token') : __('Generate token')) ?></button>
            <span class="form-text ms-2"><?= e(__('Valid for :n days, until :date.', ['n' => ApiToken::days(), 'date' => format_day(date('Y-m-d', time() + ApiToken::days() * 86400))])) ?></span>
        </form>
    <?php endif ?>
</div>
