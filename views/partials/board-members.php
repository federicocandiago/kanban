<?php /** Avatars in the board header, plus the share button. @var App\Models\Board $board @var App\Models\User[] $members */ ?>
<div class="board-members" id="board-members">
    <?php foreach (array_slice($members, 0, 6) as $member): ?>
        <?= component('avatar', ['user' => $member, 'size' => 34]) ?>
    <?php endforeach ?>
    <?php if (count($members) > 6): ?><span class="avatar avatar-more">+<?= count($members) - 6 ?></span><?php endif ?>
    <?php $label = $board->isOwner() ? __('Share') : __('Members'); ?>
    <button class="board-btn" type="button" data-bs-toggle="modal" data-bs-target="#share-modal" title="<?= e($label) ?>" aria-label="<?= e($label) ?>">
        <?= component('icon', ['name' => 'share']) ?>
    </button>
</div>
