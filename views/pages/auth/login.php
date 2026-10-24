<div class="auth-box">
    <h1><?= e(__('Sign in')) ?></h1>
    <form method="post" action="<?= base_url() ?>/login" data-ajax novalidate>
        <?= csrf_field() ?>
        <?= component('field', ['name' => 'email', 'type' => 'email', 'label' => __('Email'), 'required' => true, 'attrs' => 'autocomplete="email" autofocus']) ?>
        <?= component('field', ['name' => 'password', 'type' => 'password', 'label' => __('Password'), 'required' => true, 'attrs' => 'autocomplete="current-password"']) ?>
        <label class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" value="1" checked>
            <span class="form-check-label"><?= e(__('Keep me signed in')) ?></span>
        </label>
        <button class="btn btn-success w-100 mt-2" type="submit"><?= e(__('Sign in')) ?></button>
    </form>
    <?php if (email_verification_enabled()): ?>
        <p class="auth-foot"><a href="<?= base_url() ?>/verify/resend"><?= e(__('Didn\'t get the confirmation email?')) ?></a></p>
    <?php endif ?>
    <?php if (registration_enabled()): ?>
        <p class="auth-foot"><?= e(__('No account yet?')) ?> <a href="<?= base_url() ?>/register"><?= e(__('Create one')) ?></a></p>
    <?php endif ?>
</div>
