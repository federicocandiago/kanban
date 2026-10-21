<?php /** The event's change log; changes found while syncing are marked "outside the app". @var App\Models\Event $event @var App\Models\CalendarAccount $account */
$entries = $event->activity((int) $account->id); ?>
<section class="card-section" id="event-activity" <?= $entries ? '' : 'hidden' ?>>
    <h3><?= component('icon', ['name' => 'activity']) ?> <?= e(__('Activity')) ?></h3>
    <ul class="activity">
        <?php foreach ($entries as $entry): ?>
            <li class="<?= $entry->isOutside() ? 'is-outside' : '' ?>">
                <strong><?= e($entry->isOutside() ? __('Changed outside the app') : $entry->user_name) ?></strong>
                <?php if ($entry->field === 'created'): ?>
                    <?= e(__('created this event in :calendar', ['calendar' => $entry->new_value])) ?>
                <?php else: ?>
                    <span class="activity-field"><?= e($entry->fieldName()) ?>:</span>
                    <?php if (str_starts_with($entry->field, 'attachment_')): ?>
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
