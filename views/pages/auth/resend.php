<div class="auth-box">
    <h1><?= e(__('Confirm your email')) ?></h1>
    <p class="text-muted small"><?= e(__('Enter the address you signed up with and we\'ll send you a new confirmation link.')) ?></p>
    <form method="post" action="<?= base_url() ?>/verify/resend" data-ajax novalidate>
        <?= csrf_field() ?>
        <?= component('field', ['name' => 'email', 'type' => 'email', 'label' => __('Email'), 'required' => true, 'attrs' => 'autocomplete="email" autofocus']) ?>
        <button class="btn btn-success w-100 mt-2" type="submit"><?= e(__('Send a new link')) ?></button>
    </form>
    <p class="auth-foot"><a href="<?= base_url() ?>/login"><?= e(__('Sign in')) ?></a></p>
</div>
