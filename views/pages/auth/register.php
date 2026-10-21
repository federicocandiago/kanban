<?php /** @var array $locales @var string $email @var bool $invited */ ?>
<div class="auth-box">
    <h1><?= e(__('Create account')) ?></h1>
    <?php if ($invited): ?>
        <p class="alert alert-info small"><?= e(__('A board is waiting for you: sign up with :email to find it in your notifications.', ['email' => $email])) ?></p>
    <?php endif ?>
    <form method="post" action="<?= base_url() ?>/register" data-ajax novalidate>
        <?= csrf_field() ?>
        <?= component('field', ['name' => 'name', 'label' => __('Name'), 'required' => true, 'attrs' => 'autocomplete="name" maxlength="100" autofocus']) ?>
        <?= component('field', ['name' => 'email', 'type' => 'email', 'label' => __('Email'), 'value' => $email, 'required' => true, 'attrs' => 'autocomplete="email"']) ?>
        <?= component('field', ['name' => 'password', 'type' => 'password', 'label' => __('Password'), 'required' => true, 'help' => __('At least 8 characters.'), 'attrs' => 'autocomplete="new-password"']) ?>
        <?= component('field', ['name' => 'password_confirmation', 'type' => 'password', 'label' => __('Repeat password'), 'required' => true, 'attrs' => 'autocomplete="new-password"']) ?>
        <div class="mb-3">
            <label class="form-label" for="f-locale"><?= e(__('Language')) ?></label>
            <select class="form-select" id="f-locale" name="locale">
                <?php foreach ($locales as $code): ?>
                    <option value="<?= e($code) ?>" <?= $code === locale() ? 'selected' : '' ?>><?= e($code === 'it' ? 'Italiano' : ($code === 'en' ? 'English' : strtoupper($code))) ?></option>
                <?php endforeach ?>
            </select>
            <div class="invalid-feedback" data-error-for="locale"></div>
        </div>
        <?= component('color-picker', ['value' => App\Services\Palette::DEFAULT]) ?>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" name="terms" id="f-terms">
            <label class="form-check-label small" for="f-terms">
                <?= __('I accept the <a href=":terms" target="_blank">Terms of Service</a> and the <a href=":privacy" target="_blank">Privacy Policy</a>.', ['terms' => url('/terms'), 'privacy' => url('/privacy')]) ?>
            </label>
            <div class="invalid-feedback" data-error-for="terms"></div>
        </div>
        <button class="btn btn-success w-100" type="submit"><?= e(__('Create account')) ?></button>
    </form>
    <p class="auth-foot"><?= e(__('Already registered?')) ?> <a href="<?= base_url() ?>/login"><?= e(__('Sign in')) ?></a></p>
</div>
