<?php
/**
 * A card's checklist: tick, rename (click the text), remove (×), drag by the grip, add at the bottom.
 * Each change is its own little form, sent at once; every one goes to the card's log.
 * @var App\Models\Card $card @var App\Models\ChecklistItem[]|null $items
 */
$items ??= App\Models\ChecklistItem::forCard((int) $card->id);
$done = count(array_filter($items, static fn ($i): bool => (bool) $i->done)); ?>
<div id="card-checklist" data-count="<?= $items ? $done . '/' . count($items) : '' ?>" data-order="<?= e(url("/cards/{$card->id}/checklist/order")) ?>">
    <ul class="checklist" data-checks>
        <?php foreach ($items as $item): ?>
            <li class="check-item <?= $item->done ? 'is-done' : '' ?>" data-check-id="<?= (int) $item->id ?>">
                <span class="check-grip" aria-hidden="true"><?= component('icon', ['name' => 'grip', 'size' => 14]) ?></span>
                <form method="post" action="<?= e(url("/checklist/{$item->id}/update")) ?>" data-ajax data-autosubmit>
                    <?= csrf_field() ?>
                    <input type="hidden" name="done" value="0">
                    <input class="form-check-input" type="checkbox" name="done" value="1" <?= $item->done ? 'checked' : '' ?> aria-label="<?= e(__('Done')) ?>">
                </form>
                <form class="check-text" method="post" action="<?= e(url("/checklist/{$item->id}/update")) ?>" data-ajax data-autosubmit>
                    <?= csrf_field() ?>
                    <input name="text" value="<?= e($item->text) ?>" maxlength="500" required autocomplete="off" aria-label="<?= e(__('Checklist item')) ?>">
                </form>
                <button class="check-remove" type="button" data-post="<?= e(url("/checklist/{$item->id}/delete")) ?>" title="<?= e(__('Remove')) ?>" aria-label="<?= e(__('Remove')) ?>"><?= component('icon', ['name' => 'x', 'size' => 14]) ?></button>
            </li>
        <?php endforeach ?>
    </ul>
    <form class="check-add" method="post" action="<?= e(url("/cards/{$card->id}/checklist")) ?>" data-ajax data-reset data-keep-focus>
        <?= csrf_field() ?>
        <?= component('icon', ['name' => 'plus', 'size' => 14]) ?>
        <input name="text" maxlength="500" required autocomplete="off" placeholder="<?= e(__('Add an item…')) ?>" aria-label="<?= e(__('Add an item')) ?>">
    </form>
</div>
