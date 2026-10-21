<?php /** The change log: date, who, field, old value → new value. @var App\Models\Card $card */ ?>
<section class="card-section" id="card-activity">
    <h3><?= component('icon', ['name' => 'activity']) ?> <?= e(__('Activity')) ?></h3>
    <ul class="activity">
        <?php foreach ($card->activity() as $entry): ?>
            <li>
                <strong><?= e($entry->user_name) ?></strong>
                <?php if ($entry->field === 'created'): ?>
                    <?= e(__('created this card in :list', ['list' => $entry->new_value])) ?>
                <?php elseif (in_array($entry->field, ['archived', 'restored'], true)): ?>
                    <?= e($entry->fieldName()) ?>
                <?php else: ?>
                    <span class="activity-field"><?= e($entry->fieldName()) ?>:</span>
                    <?php if (str_starts_with($entry->field, 'label_') || str_starts_with($entry->field, 'attachment_')): ?>
                        <?= e($entry->value($entry->new_value ?? $entry->old_value)) ?>
                    <?php else: ?>
                        <del><?= e($entry->value($entry->old_value)) ?></del> → <ins><?= e($entry->value($entry->new_value)) ?></ins>
                    <?php endif ?>
                <?php endif ?>
                <span class="d-block activity-time"><?= time_ago($entry->created_at) ?></span>
            </li>
        <?php endforeach ?>
    </ul>
</section>
