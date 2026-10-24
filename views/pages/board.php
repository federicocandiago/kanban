<?php
/**
 * @var App\Models\Board $board @var App\Models\BoardList[] $lists @var array $cards @var App\Models\User[] $members
 * @var string $access "member", or for a visitor of a public board "edit" / "read" (see Controller::board())
 */
$star = $board->is_favorite ? __('Remove from favourites') : __('Add to favourites');
$visitor = $access !== 'member'; ?>
<div class="board-header">
    <?php if (!$visitor || auth()): ?>
    <a class="board-btn board-back" href="<?= base_url() ?>/dashboard" title="<?= e(__('Back to the boards')) ?>" aria-label="<?= e(__('Back to the boards')) ?>"><?= component('icon', ['name' => 'back', 'size' => 20]) ?></a>
    <?php endif ?>
    <?php if ($visitor): /* a public board seen by someone who is not a member: what they can do, no members */ ?>
        <span class="board-public"><?= component('icon', ['name' => 'users', 'size' => 14]) ?> <?= e($access === 'read' ? __('Public board · read only') : __('Public board')) ?></span>
    <?php else: ?>
    <button class="board-btn board-star-btn <?= $board->is_favorite ? 'is-on' : '' ?>" type="button" data-post="<?= e($board->url('/favorite')) ?>"
            aria-pressed="<?= $board->is_favorite ? 'true' : 'false' ?>" title="<?= e($star) ?>" aria-label="<?= e($star) ?>"><?= component('icon', ['name' => 'star']) ?></button>
    <span class="board-sep"></span>
    <?= partial('board-members', ['board' => $board, 'members' => $members]) ?>
    <?php endif ?>
    <div class="board-filter ms-auto" data-expand>
        <button class="board-btn" type="button" title="<?= e(__('Filter cards')) ?>" aria-label="<?= e(__('Filter cards')) ?>"><?= component('icon', ['name' => 'search']) ?></button>
        <?= component('icon', ['name' => 'search', 'size' => 14]) ?>
        <input type="search" placeholder="<?= e(__('Filter cards')) ?>" aria-label="<?= e(__('Filter cards')) ?>" data-filter=".board-canvas [data-search]">
    </div>
    <?php $owner = array_values(array_filter($members, static fn ($m): bool => (int) $m->id === (int) $board->owner_id))[0] ?? null; ?>
    <?php if ($owner && !$board->isOwner() && !$visitor): /* whose board it is, only when it is somebody else's */ ?>
        <span class="board-owner" title="<?= e(__('Owner: :name', ['name' => $owner->name])) ?>"><?= component('avatar', ['user' => $owner, 'size' => 34]) ?></span>
    <?php endif ?>
    <?php if (!$visitor): ?>
    <button class="board-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#board-menu" aria-controls="board-menu"
            title="<?= e(__('Show menu')) ?>" aria-label="<?= e(__('Show menu')) ?>"><?= component('icon', ['name' => 'more']) ?></button>
    <?php endif ?>
</div>

<div class="board-canvas" <?= $access === 'read' ? 'data-readonly' : 'data-lists-order="' . e($board->url('/lists/order')) . '"' ?> data-cards-url="<?= e(url('/cards/')) ?>">
    <div class="board-lists" data-lists>
        <?php foreach ($lists as $list): ?>
            <?= partial('list', ['list' => $list, 'cards' => $cards[(int) $list->id] ?? []]) ?>
        <?php endforeach ?>
    </div>
    <?php if ($access !== 'read'): ?>
    <details class="add-list quick-add">
        <summary><?= component('icon', ['name' => 'plus', 'size' => 14]) ?> <?= e($lists ? __('Add another list') : __('Add a list')) ?></summary>
        <form method="post" action="<?= e($board->url('/lists')) ?>" data-ajax data-reset>
            <?= csrf_field() ?>
            <input class="form-control" name="name" maxlength="100" required placeholder="<?= e(__('Enter list title…')) ?>" aria-label="<?= e(__('List title')) ?>" autocomplete="off">
            <div class="quick-add-actions">
                <button class="btn btn-success" type="submit"><?= e(__('Add list')) ?></button>
                <button class="pop-close" type="button" data-close-details aria-label="<?= e(__('Cancel')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>
            </div>
        </form>
    </details>
    <?php endif ?>
</div>

<?php if (!$visitor): ?>
<?= partial('board-menu', ['board' => $board]) ?>
<?= component('rename-modal', ['action' => $board->url('/update'), 'name' => $board->name]) ?>

<div class="modal fade" id="share-modal" tabindex="-1" aria-labelledby="share-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pop">
            <div class="pop-head">
                <h2 class="pop-title" id="share-title"><?= e(__('Share board')) ?></h2>
                <button type="button" class="pop-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x']) ?></button>
            </div>
            <?= partial('share', ['board' => $board, 'members' => $members]) ?>
        </div>
    </div>
</div>
<?php endif ?>

<div class="modal fade" id="card-modal" tabindex="-1" aria-label="<?= e(__('Card')) ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content card-window" data-card-content></div>
    </div>
</div>
