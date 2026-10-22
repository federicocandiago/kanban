<?php /** Card front, as it sits in its list. @var App\Models\Card $card */
$due = $card->dueState();
$labels = $card->labels(); ?>
<a class="card-tile" id="card-<?= (int) $card->id ?>" href="?card=<?= (int) $card->id ?>" data-card-id="<?= (int) $card->id ?>" draggable="false"
   data-search="<?= e(mb_strtolower($card->title . ' ' . implode(' ', array_map(static fn ($l) => $l->name, $labels)))) ?>">
    <?php if ($labels): ?>
        <span class="card-labels">
            <?php foreach ($labels as $label): ?>
                <span class="label label-<?= e($label->color) ?> <?= $label->name === '' ? 'is-bar' : '' ?>" title="<?= e($label->display()) ?>"><?= e($label->name) ?></span>
            <?php endforeach ?>
        </span>
    <?php endif ?>
    <span class="card-title"><?= e($card->title) ?></span>
    <?php if ($card->start_date || $card->end_date || $card->description || $card->attachment_count || $card->note_count || $card->check_total): ?>
        <span class="card-badges">
            <?php if ($card->start_date || $card->end_date): ?>
                <span class="badge-date <?= $due ? 'is-' . $due : '' ?>" title="<?= e(__('Dates')) ?>">
                    <?= component('icon', ['name' => 'clock', 'size' => 12]) ?>
                    <?= e(trim(format_day($card->start_date) . ($card->start_date && $card->end_date ? ' – ' : '') . format_day($card->end_date))) ?>
                </span>
            <?php endif ?>
            <?php if ($card->description): ?>
                <span class="badge-icon" title="<?= e(__('This card has a description.')) ?>"><?= component('icon', ['name' => 'text', 'size' => 12]) ?></span>
            <?php endif ?>
            <?php if ($card->check_total): ?>
                <span class="badge-check <?= (int) $card->check_done === (int) $card->check_total ? 'is-complete' : '' ?>" title="<?= e(__('Checklist')) ?>"><?= component('icon', ['name' => 'check-square', 'size' => 12]) ?> <?= (int) $card->check_done ?>/<?= (int) $card->check_total ?></span>
            <?php endif ?>
            <?php if ($card->note_count): ?>
                <span class="badge-icon" title="<?= e(__('Notes')) ?>"><?= component('icon', ['name' => 'note', 'size' => 12]) ?> <?= (int) $card->note_count ?></span>
            <?php endif ?>
            <?php if ($card->attachment_count): ?>
                <span class="badge-icon" title="<?= e(__('Attachments')) ?>"><?= component('icon', ['name' => 'clip', 'size' => 12]) ?> <?= (int) $card->attachment_count ?></span>
            <?php endif ?>
        </span>
    <?php endif ?>
</a>
