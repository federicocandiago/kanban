<?php
/**
 * The event's change log, with the notes (kept only here, not on the calendar server); changes found
 * while syncing are marked "outside the app". @var App\Models\Event $event @var App\Models\CalendarAccount $account @var string|null $view
 */
$entries = $event->activity((int) $account->id);
$view ??= null; ?>
<section class="card-section" id="event-activity">
    <div class="activity-head">
        <h3><?= component('icon', ['name' => 'activity']) ?> <?= e(__('Activity')) ?></h3>
        <?= partial('note-add', ['action' => url("/events/{$event->id}/notes"), 'view' => $view]) ?>
    </div>
    <ul class="activity">
        <?php foreach ($entries as $entry): ?>
            <?php if ($entry->field === 'note'): ?>
                <?= partial('note', ['entry' => $entry, 'base' => '/event-notes/' . $entry->id, 'canDelete' => true, 'view' => $view]) ?>
                <?php continue ?>
            <?php endif ?>
            <li class="<?= $entry->isOutside() ? 'is-outside' : '' ?>">
                <strong><?= e($entry->isOutside() ? __('Changed outside the app') : $entry->user_name) ?></strong>
                <?php if ($entry->field === 'created'): ?>
                    <?= e(__('created this event in :calendar', ['calendar' => $entry->new_value])) ?>
                <?php else: ?>
                    <span class="activity-field"><?= e($entry->fieldName()) ?>:</span>
                    <?php if (str_starts_with($entry->field, 'attachment_')): ?>
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
