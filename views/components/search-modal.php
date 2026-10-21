<?php /* Global search (magnifier in the top bar, or "/" anywhere): a preview while typing, Enter for every result. */ ?>
<div class="modal fade" id="search-modal" tabindex="-1" aria-label="<?= e(__('Search')) ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-fullscreen-sm-down search-dialog">
        <div class="modal-content">
            <form class="search-modal-form" method="get" action="<?= base_url() ?>/search">
                <label class="search-box">
                    <?= component('icon', ['name' => 'search', 'size' => 18]) ?>
                    <input type="search" name="q" placeholder="<?= e(__('Search everywhere')) ?>" aria-label="<?= e(__('Search everywhere')) ?>" autocomplete="off" autofocus
                           data-load="<?= e(url('/search/preview?q=')) ?>" data-load-into="#search-preview" data-load-delay="200">
                </label>
                <button type="button" class="pop-close" data-bs-dismiss="modal" aria-label="<?= e(__('Close')) ?>"><?= component('icon', ['name' => 'x']) ?></button>
            </form>
            <div class="modal-body" id="search-preview">
                <?= partial('search-results', ['query' => '', 'results' => ['boards' => [], 'lists' => [], 'cards' => [], 'attachments' => [], 'events' => []], 'preview' => true]) ?>
            </div>
        </div>
    </div>
</div>
