<?php /** The change log, with the notes: date, who, field, new value. @var App\Models\Card $card @var App\Models\Board $board */ ?>
<section class="card-section" id="card-activity">
    <div class="activity-head">
        <h3><?= component('icon', ['name' => 'activity']) ?> <?= e(__('Activity')) ?></h3>
        <?= partial('note-add', ['action' => url("/cards/{$card->id}/notes")]) ?>
    </div>
    <ul class="activity">
        <?php foreach ($card->activity() as $entry): ?>
            <?php if ($entry->field === 'note'): ?>
                <?= partial('note', ['entry' => $entry, 'base' => '/notes/' . $entry->id, 'canDelete' => $board->isOwner()]) ?>
                <?php continue ?>
            <?php endif ?>
            <li>
                <strong><?= e($entry->user_name) ?></strong>
                <?php if ($entry->field === 'created'): ?>
                    <?= e(__('created this card in :list', ['list' => $entry->new_value])) ?>
                <?php elseif (in_array($entry->field, ['archived', 'restored'], true)): ?>
                    <?= e($entry->fieldName()) ?>
                <?php else: ?>
                    <span class="activity-field"><?= e($entry->fieldName()) ?>:</span>
                    <?php if (str_starts_with($entry->field, 'label_') || str_starts_with($entry->field, 'attachment_') || in_array($entry->field, ['check_added', 'check_done', 'check_undone', 'check_removed'], true)): ?>
                        <?= e($entry->value($entry->new_value ?? $entry->old_value)) ?>
                    <?php else: ?>
                        <?php if (($entry->new_value ?? '') === ''): ?><span class="activity-empty"><?= e(__('(empty)')) ?></span><?php else: ?><ins><?= e($entry->value($entry->new_value)) ?></ins><?php endif /* only what it is now: the line before says what it was */ ?>
                    <?php endif ?>
                <?php endif ?>
                <span class="d-block activity-time"><?= time_ago($entry->created_at) ?></span>
            </li>
        <?php endforeach ?>
    </ul>
</section>
