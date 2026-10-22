<?php /** "Add a note" next to the Activity title: opens a field, Enter adds the note to the log. @var string $action @var string|null $view */ ?>
<details class="quick-add note-add">
    <summary><?= component('icon', ['name' => 'plus', 'size' => 14]) ?> <?= e(__('Add a note')) ?></summary>
    <form method="post" action="<?= e($action) ?>" data-ajax data-reset>
        <?= csrf_field() ?>
        <?php if (!empty($view)): ?><input type="hidden" name="view" value="<?= e($view) ?>"><?php endif ?>
        <textarea class="quick-add-input" name="text" rows="2" maxlength="5000" required placeholder="<?= e(__('Write a note…')) ?>" aria-label="<?= e(__('Note')) ?>" data-enter-submit></textarea>
        <div class="quick-add-actions">
            <button class="btn btn-success" type="submit"><?= e(__('Add note')) ?></button>
            <button class="pop-close" type="button" data-close-details aria-label="<?= e(__('Cancel')) ?>"><?= component('icon', ['name' => 'x', 'size' => 20]) ?></button>
        </div>
    </form>
</details>
