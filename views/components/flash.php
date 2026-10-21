<?php foreach (App\Core\Session::pullFlash() as $flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= e(__('Close')) ?>"></button>
    </div>
<?php endforeach ?>
