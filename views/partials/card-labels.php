<?php /** @var App\Models\Card $card */ ?>
<div id="card-labels">
    <?php if ($card->labels()): ?>
        <span class="card-back-label"><?= e(__('Labels')) ?></span>
        <div class="card-back-labels">
            <?php foreach ($card->labels() as $label): ?>
                <span class="label label-<?= e($label->color) ?>"><?= e($label->display()) ?></span>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
