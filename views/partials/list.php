<?php /** @var App\Models\BoardList $list @var App\Models\Card[] $cards */ ?>
<section class="list" id="list-<?= (int) $list->id ?>" data-list-id="<?= (int) $list->id ?>">
    <header class="list-head">
        <form method="post" action="<?= e(url("/lists/{$list->id}/update")) ?>" data-ajax data-autosubmit>
            <?= csrf_field() ?>
            <input class="list-name" name="name" value="<?= e($list->name) ?>" maxlength="100" required aria-label="<?= e(__('List title')) ?>" autocomplete="off">
        </form>
        <div class="dropdown">
            <button class="list-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(__('List actions')) ?>"><?= component('icon', ['name' => 'more']) ?></button>
            <div class="dropdown-menu dropdown-menu-end pop">
                <div class="pop-head"><span class="pop-title"><?= e(__('List actions')) ?></span></div>
                <button class="dropdown-item" type="button" data-open-details="#list-<?= (int) $list->id ?> .quick-add"><?= e(__('Add card…')) ?></button>
                <button class="dropdown-item" type="button" data-post="<?= e(url("/lists/{$list->id}/archive")) ?>"><?= e(__('Archive this list')) ?></button>
            </div>
        </div>
    </header>
    <div class="list-cards" data-cards>
        <?php foreach ($cards as $card): ?>
            <?= partial('card-tile', ['card' => $card]) ?>
        <?php endforeach ?>
    </div>
    <details class="quick-add">
        <summary><?= component('icon', ['name' => 'plus', 'size' => 14]) ?> <?= e($cards ? __('Add another card') : __('Add a card')) ?></summary>
        <form method="post" action="<?= e(url("/lists/{$list->id}/cards")) ?>" data-ajax data-reset>
            <?= csrf_field() ?>
            <textarea class="quick-add-input" name="title" rows="3" maxlength="255" required placeholder="<?= e(__('Enter a title for this card…')) ?>" aria-label="<?= e(__('Card title')) ?>" data-enter-submit></textarea>
            <div class="quick-add-actions">
                <button class="btn btn-success" type="submit"><?= e(__('Add card')) ?></button>
                <button class="pop-close" type="button" data-close-details aria-label="<?= e(__('Cancel')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>
            </div>
        </form>
    </details>
</section>
