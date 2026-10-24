<?php /** The name of a board or calendar in the middle of the top bar (redrawn after "Rename"). @var string $name @var string|null $hint */ ?>
<h1 class="topbar-title" title="<?= e($hint ?? $name) ?>"><span><?= e($name) ?></span></h1>
