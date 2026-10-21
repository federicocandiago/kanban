<?php /** @var array{items: App\Models\User[], page: int, pages: int, total: int} $users @var array $counts @var array $stats @var bool $registrationEnabled */ ?>
<h1 class="dashboard-title mb-4"><?= e(__('Back-office')) ?></h1>

<div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
    <?php foreach ($stats as $label => $value): ?>
        <div class="col"><div class="panel stat"><span><?= e($label) ?></span><strong><?= (int) $value ?></strong></div></div>
    <?php endforeach ?>
</div>

<section class="panel">
    <h2><?= e(__('Users')) ?></h2>
    <div class="table-responsive">
        <table class="table align-middle small mb-0">
            <thead><tr>
                <th><?= e(__('Name')) ?></th><th><?= e(__('Last sign-in')) ?></th><th><?= e(__('Admin')) ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($users['items'] as $user):
                $isMe = (int) $user->id === (int) auth()->id;
                $boards = (int) ($counts[$user->id]['boards'] ?? 0); ?>
                <tr class="<?= $user->isSuspended() ? 'is-paused' : '' ?>">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?= component('avatar', ['user' => $user, 'size' => 28]) ?>
                            <div class="min-w-0">
                                <strong class="d-flex align-items-center gap-2"><span class="text-truncate"><?= e($user->name) ?></span>
                                    <span class="count-badge" title="<?= e(__('Boards')) ?>"><?= component('icon', ['name' => 'board', 'size' => 12]) ?> <?= $boards ?></span>
                                    <?php if ($t = $tokens[$user->id] ?? null): ?>
                                        <span class="count-badge <?= strtotime($t['expires_at']) <= time() ? 'is-expired' : '' ?>"
                                              title="<?= e(__('API token (:scope), expires :date', ['scope' => $t['scope'] === 'write' ? __('read and change') : __('only read'), 'date' => format_date($t['expires_at'])])) ?>">API</span>
                                    <?php endif ?></strong>
                                <span class="text-muted"><?= e($user->email) ?></span>
                                <?php if ($user->isSuspended()): ?><span class="badge text-bg-warning"><?= e(__('Paused')) ?></span><?php endif ?>
                                <?php if (email_verification_enabled() && !$user->isVerified()): ?><span class="badge text-bg-secondary"><?= e(__('Email to confirm')) ?></span><?php endif ?>
                            </div>
                        </div>
                    </td>
                    <td class="text-nowrap"><?= e(format_date($user->last_login_at)) ?></td>
                    <td>
                        <form method="post" action="<?= base_url() ?>/admin/users/<?= (int) $user->id ?>" data-ajax data-autosubmit>
                            <?= csrf_field() ?>
                            <input class="form-check-input" type="checkbox" name="is_admin" value="1" <?= $user->isAdmin() ? 'checked' : '' ?> <?= $isMe ? 'disabled' : '' ?> aria-label="<?= e(__('Admin')) ?>">
                        </form>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if (isset($tokens[$user->id])): ?>
                            <button class="btn btn-sm btn-secondary" type="button" data-action="<?= base_url() ?>/admin/users/<?= (int) $user->id ?>/api-token/delete"
                                    data-confirm="<?= e(__('The API token of :name will stop working at once.', ['name' => $user->name])) ?>"><?= e(__('Revoke API token')) ?></button>
                        <?php endif ?>
                        <?php if (!$isMe): ?>
                            <button class="btn btn-sm btn-secondary" type="button" data-post="<?= base_url() ?>/admin/users/<?= (int) $user->id ?>/suspend"><?= e($user->isSuspended() ? __('Resume') : __('Pause')) ?></button>
                            <button class="btn btn-sm btn-danger" type="button" data-action="<?= base_url() ?>/admin/users/<?= (int) $user->id ?>/delete"
                                    data-confirm="<?= e(__('The user :email and the boards they own will be deleted.', ['email' => $user->email])) ?>"><?= e(__('Delete')) ?></button>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?= component('pagination', ['page' => $users['page'], 'pages' => $users['pages'], 'url' => url('/admin')]) ?>
</section>

<section class="panel">
    <h2><?= e(__('Settings')) ?></h2>
    <form method="post" action="<?= base_url() ?>/admin/settings" data-ajax data-autosubmit>
        <?= csrf_field() ?>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="f-registration_enabled" name="registration_enabled" value="1" <?= $registrationEnabled ? 'checked' : '' ?>>
            <label class="form-check-label" for="f-registration_enabled"><?= e(__('Registration open')) ?></label>
        </div>
        <p class="form-text"><?= e(__('When closed, only existing users can sign in, and people invited to a board can still sign up with the invited address.')) ?></p>
    </form>
</section>
