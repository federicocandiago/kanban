<?php /** @var string $query @var array $results */ ?>
<div class="search-page">
    <div class="dashboard-bar">
        <h1 class="dashboard-title"><?= e(__('Search')) ?></h1>
    </div>
    <form class="search-page-form" method="get" action="<?= base_url() ?>/search">
        <label class="search-box w-100">
            <?= component('icon', ['name' => 'search', 'size' => 16]) ?>
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="<?= e(__('Search everywhere')) ?>" aria-label="<?= e(__('Search everywhere')) ?>" autofocus>
        </label>
    </form>
    <?= partial('search-results', ['query' => $query, 'results' => $results]) ?>
</div>
