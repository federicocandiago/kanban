<div class="error-page">
    <h1 class="h3 mb-3"><?= e($title) ?></h1>
    <p class="text-muted"><?= e(__('The service cannot reach its database right now. Please try again in a few minutes.')) ?></p>
    <?php if (env('APP_DEBUG')): ?><pre class="small text-wrap text-start"><?= e($detail) ?></pre><?php endif ?>
    <a class="btn btn-secondary mt-3" href="javascript:location.reload()"><?= e(__('Retry')) ?></a>
</div>
