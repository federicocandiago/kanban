<?php /** Bell dropdown: invitations (with Accept / Decline) and news about shared boards. @var App\Models\User $user */
use App\Models\Notification;

App\Models\ApiToken::remind($user); // a week before the API token expires
$items = Notification::latest($user);
$unread = Notification::unread($user); ?>
<div class="dropdown">
    <button class="topbar-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
            aria-label="<?= e(__('Notifications')) ?>" title="<?= e(__('Notifications')) ?>"
            <?= $unread ? 'data-open-post="' . e(url('/notifications/read')) . '"' : '' ?>>
        <?= component('icon', ['name' => 'bell']) ?>
        <?php if ($unread): ?><span class="topbar-badge" data-unread><?= $unread > 9 ? '9+' : $unread ?></span><?php endif ?>
    </button>
    <div class="dropdown-menu dropdown-menu-end pop notifications">
        <div class="pop-head"><span class="pop-title"><?= e(__('Notifications')) ?></span></div>
        <?php if (!$items): ?>
            <p class="pop-empty"><?= e(__('No notifications.')) ?></p>
        <?php endif ?>
        <?php foreach ($items as $n): $invite = $n->pendingInvite(); ?>
            <div class="notification <?= $n->read_at ? '' : 'is-unread' ?>">
                <?php if ($n->link()): ?>
                    <a href="<?= e($n->link()) ?>"><?= e($n->text()) ?></a>
                <?php else: ?>
                    <span><?= e($n->text()) ?></span>
                <?php endif ?>
                <small class="text-muted d-block"><?= e(format_date($n->created_at)) ?></small>
                <?php if ($invite): ?>
                    <div class="d-flex gap-2 mt-2">
                        <button class="btn btn-sm btn-success" type="button" data-post="<?= e(url("/invites/{$invite->id}/accept")) ?>"><?= e(__('Accept')) ?></button>
                        <button class="btn btn-sm btn-secondary" type="button" data-post="<?= e(url("/invites/{$invite->id}/decline")) ?>"><?= e(__('Decline')) ?></button>
                    </div>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
</div>
