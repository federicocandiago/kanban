<?php
/**
 * The other members in the board header (not you, not the owner: the owner sits on the right),
 * plus the share button. @var App\Models\Board $board @var App\Models\User[] $members
 */
$others = array_values(array_filter($members, static fn ($m): bool => (int) $m->id !== (int) auth()->id && (int) $m->id !== (int) $board->owner_id)); ?>
<div class="board-members" id="board-members">
    <?php foreach (array_slice($others, 0, 6) as $member): ?>
        <?= component('avatar', ['user' => $member, 'size' => 34]) ?>
    <?php endforeach ?>
    <?php if (count($others) > 6): ?><span class="avatar avatar-more">+<?= count($others) - 6 ?></span><?php endif ?>
    <?php $label = $board->isOwner() ? __('Share') : __('Members'); ?>
    <button class="board-btn" type="button" data-bs-toggle="modal" data-bs-target="#share-modal" title="<?= e($label) ?>" aria-label="<?= e($label) ?>">
        <?= component('icon', ['name' => 'share']) ?>
    </button>
</div>
