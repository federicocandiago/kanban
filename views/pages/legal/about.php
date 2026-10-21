<article class="legal mx-auto">
    <h1 class="h3 mb-4"><?= e(__('About')) ?></h1>
    <p class="lead"><?= e(__((string) env('APP_DESCRIPTION', ''))) ?></p>
    <p><?= e(__(':app is a small self-hosted kanban: boards made of lists, lists made of cards. Share a board with the people you work with, move the cards as the work goes on.', ['app' => env('APP_NAME')])) ?></p>
    <h2 class="h6 mt-4"><?= e(__('Credits')) ?></h2>
    <p><?= e(__('Built with PHP, MySQL and Bootstrap. Background photos from Unsplash. Look and feel inspired by the 2018 Trello.')) ?></p>
</article>
