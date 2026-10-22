<?php
/**
 * A note in a log: who, when, the text; its author can change it, its author (or $canDelete) remove it.
 * @var App\Models\Activity $entry @var string $base URL of the note ("/notes/12" or "/event-notes/12") @var bool $canDelete
 * @var string|null $view
 */
$view ??= null;
$mine = $entry->user_id !== null && (int) $entry->user_id === (int) auth()->id;
$author = $entry->user_id ? App\Models\User::find((int) $entry->user_id) : null;
$hidden = $view ? '<input type="hidden" name="view" value="' . e($view) . '">' : ''; ?>
<li class="is-note">
    <div class="note-head">
        <?php if ($author): ?><?= component('avatar', ['user' => $author, 'size' => 24]) ?><?php endif ?>
        <strong><?= e($entry->user_name) ?></strong>
        <span class="activity-time"><?= time_ago($entry->created_at) ?><?= $entry->updated_at !== $entry->created_at ? ' · ' . e(__('edited')) : '' ?></span>
        <?php if ($mine || $canDelete): ?>
            <span class="note-actions">
                <?php if ($mine): ?>
                    <button class="link-btn" type="button" data-note-edit><?= e(__('Edit')) ?></button>
                <?php endif ?>
                <button class="link-btn" type="button" data-action="<?= e(url($base . '/delete') . ($view ? '?view=' . rawurlencode($view) : '')) ?>"
                        data-confirm="<?= e(__('The note will be deleted.')) ?>"><?= e(__('Delete')) ?></button>
            </span>
        <?php endif ?>
    </div>
    <div class="note-text"><?= nl2br(e((string) $entry->new_value)) ?></div>
    <?php if ($mine): ?>
        <form class="note-edit" method="post" action="<?= e(url($base . '/update')) ?>" data-ajax hidden>
            <?= csrf_field() ?><?= $hidden ?>
            <textarea class="form-control" name="text" rows="3" maxlength="5000" required data-enter-submit aria-label="<?= e(__('Note')) ?>"><?= e((string) $entry->new_value) ?></textarea>
            <div class="quick-add-actions mt-2">
                <button class="btn btn-sm btn-success" type="submit"><?= e(__('Save')) ?></button>
                <button class="link-btn" type="button" data-note-cancel><?= e(__('Cancel')) ?></button>
            </div>
        </form>
    <?php endif ?>
</li>
