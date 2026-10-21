<?php /** The user's labels, editable in place (Settings). @var App\Models\Label[] $labels */
use App\Services\Palette;

$colors = Palette::names();
$select = static function (string $current) use ($colors): string {
    $html = '<select class="form-select form-select-sm" name="color" aria-label="' . e(__('Colour')) . '">';
    foreach ($colors as $key => $name) {
        $html .= '<option value="' . e($key) . '"' . ($key === $current ? ' selected' : '') . '>' . e($name) . '</option>';
    }

    return $html . '</select>';
}; ?>
<div id="labels-panel" class="labels-panel">
    <?php foreach ($labels as $label): ?>
        <form class="label-row" method="post" action="<?= e(url("/labels/{$label->id}/update")) ?>" data-ajax data-autosubmit>
            <?= csrf_field() ?>
            <span class="label label-<?= e($label->color) ?>"><?= e($label->name) ?></span>
            <input class="form-control form-control-sm" name="name" value="<?= e($label->name) ?>" maxlength="40" placeholder="<?= e(__('No name')) ?>" aria-label="<?= e(__('Label name')) ?>">
            <?= $select((string) $label->color) ?>
            <button class="pop-close" type="button" data-action="<?= e(url("/labels/{$label->id}/delete")) ?>"
                    data-confirm="<?= e(__('The label will also be removed from every card it is on.')) ?>" aria-label="<?= e(__('Delete')) ?>"><?= component('icon', ['name' => 'trash']) ?></button>
        </form>
    <?php endforeach ?>
    <form class="label-row is-new" method="post" action="<?= base_url() ?>/labels" data-ajax>
        <?= csrf_field() ?>
        <span class="label label-green"><?= component('icon', ['name' => 'plus', 'size' => 12]) ?></span>
        <input class="form-control form-control-sm" name="name" maxlength="40" placeholder="<?= e(__('New label name (optional)')) ?>" aria-label="<?= e(__('Label name')) ?>">
        <?= $select('green') ?>
        <button class="btn btn-sm btn-success" type="submit"><?= e(__('Add')) ?></button>
    </form>
</div>
