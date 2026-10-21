<?php /** Archived cards and lists of a board, with restore / delete. @var App\Models\Board $board */
$cards = $board->archivedCards();
$lists = $board->lists(archived: true); ?>
<div id="archived-items" class="archived-items">
    <h3><?= e(__('Cards')) ?></h3>
    <?php if (!$cards): ?><p class="pop-empty"><?= e(__('No archived cards.')) ?></p><?php endif ?>
    <?php foreach ($cards as $card): ?>
        <div class="archived-item">
            <a href="?card=<?= (int) $card->id ?>" data-card-open="<?= (int) $card->id ?>"><?= e($card->title) ?></a>
            <small class="text-muted d-block"><?= e(__('in :list', ['list' => $card->list_name])) ?></small>
            <span class="archived-actions">
                <button type="button" class="link-btn" data-post="<?= e(url("/cards/{$card->id}/restore")) ?>"><?= e(__('Restore')) ?></button>
                <?php if ($board->isOwner()): ?>
                    · <button type="button" class="link-btn text-danger" data-action="<?= e(url("/cards/{$card->id}/delete")) ?>"
                              data-confirm="<?= e(__('The card “:name” and its attachments will be deleted for good.', ['name' => $card->title])) ?>"><?= e(__('Delete')) ?></button>
                <?php endif ?>
            </span>
        </div>
    <?php endforeach ?>

    <h3><?= e(__('Lists')) ?></h3>
    <?php if (!$lists): ?><p class="pop-empty"><?= e(__('No archived lists.')) ?></p><?php endif ?>
    <?php foreach ($lists as $list): ?>
        <div class="archived-item">
            <strong><?= e($list->name) ?></strong>
            <span class="archived-actions">
                <button type="button" class="link-btn" data-post="<?= e(url("/lists/{$list->id}/restore")) ?>"><?= e(__('Restore')) ?></button>
                <?php if ($board->isOwner()): ?>
                    · <button type="button" class="link-btn text-danger" data-action="<?= e(url("/lists/{$list->id}/delete")) ?>"
                              data-confirm="<?= e(__('The list “:name” and all its cards will be deleted for good.', ['name' => $list->name])) ?>"><?= e(__('Delete')) ?></button>
                <?php endif ?>
            </span>
        </div>
    <?php endforeach ?>
</div>
