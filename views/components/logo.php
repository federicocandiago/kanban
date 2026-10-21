<?php
/**
 * The Kalban pepper (outline). $on = 'color' on the coloured top bar (always white),
 * 'surface' on the page background (dark outline, white in the dark theme).
 * @var int|null $size @var string|null $on
 */
$size ??= 28;
$on ??= 'color'; ?>
<?php if ($on === 'color'): ?>
    <img class="logo" src="<?= asset('assets/icons/logo-dark.svg') ?>" width="<?= (int) $size ?>" height="<?= (int) $size ?>" alt="">
<?php else: ?>
    <img class="logo logo-for-light" src="<?= asset('assets/icons/logo-light.svg') ?>" width="<?= (int) $size ?>" height="<?= (int) $size ?>" alt="">
    <img class="logo logo-for-dark" src="<?= asset('assets/icons/logo-dark.svg') ?>" width="<?= (int) $size ?>" height="<?= (int) $size ?>" alt="">
<?php endif ?>
