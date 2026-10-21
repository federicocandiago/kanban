<article class="legal mx-auto">
    <h1 class="h3 mb-4"><?= e(__('Terms of Service')) ?></h1>
    <p class="text-secondary small"><?= e(__('Last updated: :date', ['date' => legal('date')])) ?></p>

    <h2 class="h6 mt-4">1. <?= e(__('The service')) ?></h2>
    <p><?= e(__(':app lets you organise your work in boards, lists and cards, alone or together with other people.', ['app' => env('APP_NAME')])) ?></p>

    <h2 class="h6 mt-4">2. <?= e(__('Your account')) ?></h2>
    <p><?= e(__('You are responsible for your credentials and for everything done with your account.')) ?></p>

    <h2 class="h6 mt-4">3. <?= e(__('Acceptable use')) ?></h2>
    <p><?= e(__('Only upload content you have the right to share. Malware and any content that breaks the law are not allowed and will be removed. Invite only people who expect to hear from you.')) ?></p>

    <h2 class="h6 mt-4">4. <?= e(__('Data and availability')) ?></h2>
    <p><?= e(__('The service is provided "as is", without warranties. We do our best to keep your data safe, but keep a copy of anything important.')) ?></p>

    <h2 class="h6 mt-4">5. <?= e(__('Termination')) ?></h2>
    <p><?= e(__('You can delete your boards and your account at any time. We may suspend accounts that break these terms.')) ?></p>
</article>
