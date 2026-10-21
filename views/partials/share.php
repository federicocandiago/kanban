<?php /** Share dialog body: invite, members, pending invitations. @var App\Models\Board $board @var App\Models\User[] $members */
$memberIds = array_map(static fn ($m) => (int) $m->id, $members);
$invites = $board->pendingInvites();
$invitedEmails = array_map(static fn ($i) => $i->email, $invites);
$contacts = $board->isOwner()
    ? array_filter(auth()->contacts(), static fn ($u) => !in_array((int) $u->id, $memberIds, true) && !in_array($u->email, $invitedEmails, true))
    : []; ?>
<div class="pop-body" id="share-panel">
    <?php if ($board->isOwner()): ?>
        <form class="share-form" method="post" action="<?= e($board->url('/invites')) ?>" data-ajax data-reset novalidate>
            <?= csrf_field() ?>
            <input class="form-control" type="email" name="email" placeholder="<?= e(__('Email address')) ?>" aria-label="<?= e(__('Email address')) ?>"
                   list="share-contacts" autocomplete="off" required>
            <button class="btn btn-success" type="submit"><?= e(__('Invite')) ?></button>
            <div class="invalid-feedback" data-error-for="email"></div>
        </form>
        <?php if ($contacts): ?>
            <datalist id="share-contacts">
                <?php foreach ($contacts as $contact): ?><option value="<?= e($contact->email) ?>"><?= e($contact->name) ?></option><?php endforeach ?>
            </datalist>
            <h3 class="share-heading"><?= e(__('People you work with')) ?></h3>
            <div class="share-contacts">
                <?php foreach ($contacts as $contact): ?>
                    <form method="post" action="<?= e($board->url('/invites')) ?>" data-ajax>
                        <?= csrf_field() ?>
                        <input type="hidden" name="email" value="<?= e($contact->email) ?>">
                        <button class="contact-chip" type="submit" title="<?= e(__('Invite :name', ['name' => $contact->name])) ?>">
                            <?= component('avatar', ['user' => $contact, 'size' => 24]) ?> <?= e($contact->name) ?> <?= component('icon', ['name' => 'plus', 'size' => 12]) ?>
                        </button>
                    </form>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        <p class="form-text"><?= e(__('People with an account get a notification; anyone else gets an email to sign up, and will find the board waiting.')) ?></p>
    <?php else: ?>
        <p class="form-text"><?= e(__('Only the owner of the board can invite or remove people.')) ?></p>
    <?php endif ?>

    <h3 class="share-heading"><?= e(__('Members')) ?></h3>
    <ul class="member-list">
        <?php foreach ($members as $member): ?>
            <li>
                <?= component('avatar', ['user' => $member, 'size' => 32]) ?>
                <span class="flex-grow-1 min-w-0">
                    <strong class="d-block text-truncate"><?= e($member->name) ?><?= (int) $member->id === (int) auth()->id ? ' ' . e(__('(you)')) : '' ?></strong>
                    <small class="text-muted d-block text-truncate"><?= e($member->email) ?></small>
                </span>
                <?php if ($member->role === 'owner'): ?>
                    <span class="role-badge"><?= e(__('Owner')) ?></span>
                <?php elseif ($board->isOwner()): ?>
                    <button class="btn btn-sm btn-secondary" type="button" data-action="<?= e($board->url("/members/{$member->id}/remove")) ?>"
                            data-confirm="<?= e(__(':name will no longer see this board.', ['name' => $member->name])) ?>"><?= e(__('Remove')) ?></button>
                <?php else: ?>
                    <span class="role-badge"><?= e(__('Member')) ?></span>
                <?php endif ?>
            </li>
        <?php endforeach ?>
        <?php foreach ($invites as $invite): ?>
            <li class="is-pending">
                <span class="avatar avatar-initials" style="width: 32px; height: 32px">@</span>
                <span class="flex-grow-1 min-w-0">
                    <strong class="d-block text-truncate"><?= e($invite->email) ?></strong>
                    <small class="text-muted"><?= e(__('Invitation pending')) ?></small>
                </span>
                <?php if ($board->isOwner()): ?>
                    <button class="btn btn-sm btn-secondary" type="button" data-post="<?= e(url("/invites/{$invite->id}/cancel")) ?>"><?= e(__('Withdraw')) ?></button>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
</div>
