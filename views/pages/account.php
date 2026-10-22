<?php /** @var App\Models\User $user @var array $locales @var App\Models\Label[] $labels @var App\Models\CalendarAccount[] $calendars */ ?>
<div class="account mx-auto">
    <div class="account-head">
        <?= component('avatar', ['user' => $user, 'size' => 64]) ?>
        <div>
            <h1 class="h4 mb-0"><?= e($user->name) ?></h1>
            <div class="text-muted small"><?= e($user->email) ?></div>
        </div>
    </div>

    <section class="panel">
        <h2><?= e(__('Profile')) ?></h2>
        <form method="post" action="<?= base_url() ?>/account/profile" data-ajax novalidate>
            <?= csrf_field() ?>
            <?= component('field', ['name' => 'name', 'label' => __('Name'), 'value' => $user->name, 'required' => true, 'attrs' => 'maxlength="100"']) ?>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="f-locale"><?= e(__('Language')) ?></label>
                    <select class="form-select" id="f-locale" name="locale">
                        <?php foreach ($locales as $code): ?>
                            <option value="<?= e($code) ?>" <?= $code === locale() ? 'selected' : '' ?>><?= e($code === 'it' ? 'Italiano' : ($code === 'en' ? 'English' : strtoupper($code))) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="invalid-feedback" data-error-for="locale"></div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="f-theme"><?= e(__('Theme')) ?></label>
                    <select class="form-select" id="f-theme" name="theme">
                        <?php foreach (['auto' => __('Automatic (like the system)'), 'light' => __('Light'), 'dark' => __('Dark')] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $user->theme() === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="invalid-feedback" data-error-for="theme"></div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="f-timezone"><?= e(__('Time zone')) ?></label>
                <select class="form-select" id="f-timezone" name="timezone">
                    <?php foreach (DateTimeZone::listIdentifiers() as $zone): ?>
                        <option value="<?= e($zone) ?>" <?= $zone === $user->timezone()->getName() ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $zone)) ?></option>
                    <?php endforeach ?>
                </select>
                <div class="form-text"><?= e(__('Calendar events are shown and saved in this time zone.')) ?></div>
                <div class="invalid-feedback" data-error-for="timezone"></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="f-event_minutes"><?= e(__('Length of new events')) ?></label>
                <div class="input-group event-minutes">
                    <input class="form-control" type="number" id="f-event_minutes" name="event_minutes" min="5" max="1440" step="5" value="<?= $user->eventMinutes() ?>">
                    <span class="input-group-text"><?= e(__('minutes')) ?></span>
                </div>
                <div class="form-text"><?= e(__('When you change the start of a calendar event, the end is set this many minutes later.')) ?></div>
                <div class="invalid-feedback" data-error-for="event_minutes"></div>
            </div>
            <?= component('color-picker', ['value' => $user->color()]) ?>
            <button class="btn btn-success" type="submit"><?= e(__('Save')) ?></button>
        </form>
    </section>

    <details class="panel is-collapsible" id="labels" data-remember="account-labels">
        <summary><h2><?= e(__('My labels')) ?></h2></summary>
        <p class="text-muted small"><?= e(__('Your labels, available on every board. The other members see them on the cards, and use their own.')) ?></p>
        <?= partial('labels', ['labels' => $labels]) ?>
    </details>

    <details class="panel is-collapsible" id="calendar" data-remember="account-calendar">
        <summary><h2><?= e(__('Calendars')) ?></h2><?php if ($calendars): ?><small class="text-muted"><?= e(implode(', ', array_map(static fn ($c) => $c->name, $calendars))) ?></small><?php endif ?></summary>
        <p class="text-muted small"><?= e(__('Connect your calendars (iCloud or other CalDAV servers, as many as you like): they appear on your dashboard, each one shown like a board with one list per day.')) ?></p>
        <?= partial('calendar-settings', ['accounts' => $calendars]) ?>
    </details>

    <?php if (App\Models\ApiToken::enabled()): ?>
        <details class="panel is-collapsible" id="api" data-remember="account-api">
            <summary><h2><?= e(__('API access')) ?></h2><?php if ($user->api_enabled): ?><small class="text-muted"><?= e(__('on')) ?></small><?php endif ?></summary>
            <p class="text-muted small"><?= __('Read and change your boards from scripts and other apps, with a personal token. The calendar is not part of the API. <a href=":docs" target="_blank">Documentation</a>', ['docs' => url('/api/docs')]) ?></p>
            <?= partial('api-settings', ['user' => $user]) ?>
        </details>
    <?php endif ?>

    <details class="panel is-collapsible" id="picture" data-remember="account-picture">
        <summary><h2><?= e(__('Profile picture')) ?></h2></summary>
        <div class="d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
            <?= component('avatar', ['user' => $user, 'size' => 72]) ?>
            <form class="flex-grow-1 d-flex gap-2 align-items-start" method="post" action="<?= base_url() ?>/account/avatar" enctype="multipart/form-data" data-ajax novalidate>
                <?= csrf_field() ?>
                <div class="flex-grow-1">
                    <input class="form-control" type="file" name="avatar" accept="image/png,image/jpeg,image/gif,image/webp" aria-label="<?= e(__('Profile picture')) ?>">
                    <div class="form-text"><?= e(__('JPG, PNG, GIF or WebP. It will be cropped to a square.')) ?></div>
                    <div class="invalid-feedback" data-error-for="avatar"></div>
                </div>
                <button class="btn btn-secondary" type="submit"><?= e(__('Upload')) ?></button>
            </form>
            <?php if ($user->avatar): ?>
                <form method="post" action="<?= base_url() ?>/account/avatar/delete" data-ajax>
                    <?= csrf_field() ?>
                    <button class="btn btn-link" type="submit"><?= e(__('Remove')) ?></button>
                </form>
            <?php endif ?>
        </div>
    </details>

    <details class="panel is-collapsible" id="password" data-remember="account-password">
        <summary><h2><?= e(__('Change password')) ?></h2></summary>
        <form method="post" action="<?= base_url() ?>/account/password" data-ajax data-reset novalidate>
            <?= csrf_field() ?>
            <?= component('field', ['name' => 'current_password', 'type' => 'password', 'label' => __('Current password'), 'attrs' => 'autocomplete="current-password"']) ?>
            <div class="row g-3">
                <div class="col-sm-6"><?= component('field', ['name' => 'password', 'type' => 'password', 'label' => __('New password'), 'help' => __('At least 8 characters.'), 'attrs' => 'autocomplete="new-password"']) ?></div>
                <div class="col-sm-6"><?= component('field', ['name' => 'password_confirmation', 'type' => 'password', 'label' => __('Repeat password'), 'attrs' => 'autocomplete="new-password"']) ?></div>
            </div>
            <button class="btn btn-success" type="submit"><?= e(__('Change password')) ?></button>
        </form>
    </details>

    <details class="panel is-collapsible" id="account-data">
        <summary><h2><?= e(__('Account and data')) ?></h2></summary>
        <p class="text-muted small"><?= e(__('Only when you need it: take your data with you, bring it in from another account, or close your account.')) ?></p>

        <h3 class="account-sub"><?= component('icon', ['name' => 'download', 'size' => 16]) ?> <?= e(__('Export your data')) ?></h3>
        <p class="small text-muted"><?= e(__('One JSON file with your preferences, your labels and the boards you own (archived too), with lists, cards, log and notes. It names nobody and holds no passwords: it can be imported into any account. Calendars: only their names and settings.')) ?></p>
        <form class="d-flex flex-wrap align-items-center gap-3 mb-4" method="get" action="<?= base_url() ?>/account/export">
            <label class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" name="attachments" value="1" checked>
                <span class="form-check-label"><?= e(__('Include the attachments (a bigger file)')) ?></span>
            </label>
            <button class="btn btn-secondary" type="submit"><?= component('icon', ['name' => 'download', 'size' => 14]) ?> <?= e(__('Download')) ?></button>
        </form>

        <h3 class="account-sub"><?= component('icon', ['name' => 'restore', 'size' => 16]) ?> <?= e(__('Import')) ?></h3>
        <p class="small text-muted"><?= e(__('The boards of an export are added as new ones (nothing you have is changed); labels with the same name and colour are reused. Up to :size.', ['size' => format_bytes(App\Services\AttachmentService::uploadBytes())])) ?></p>
        <form class="d-flex flex-wrap align-items-start gap-2 mb-4" method="post" action="<?= base_url() ?>/account/import" enctype="multipart/form-data" data-ajax novalidate>
            <?= csrf_field() ?>
            <div class="flex-grow-1">
                <input class="form-control" type="file" name="file" accept="application/json,.json" aria-label="<?= e(__('Export file')) ?>">
                <div class="invalid-feedback" data-error-for="file"></div>
            </div>
            <button class="btn btn-secondary" type="submit"><?= e(__('Import')) ?></button>
        </form>

        <h3 class="account-sub text-danger"><?= component('icon', ['name' => 'trash', 'size' => 16]) ?> <?= e(__('Delete account')) ?></h3>
        <form method="post" action="<?= base_url() ?>/account/delete" data-ajax
              data-ask="<?= e(__('Your account and the boards you own will be deleted forever. Continue?')) ?>" novalidate>
            <?= csrf_field() ?>
            <p class="small text-muted"><?= e(__('This deletes your account and the boards you own, with their cards and attachments, for every member. You will leave the boards shared with you. It cannot be undone.')) ?></p>
            <?= component('field', ['name' => 'confirm_password', 'type' => 'password', 'label' => __('Confirm with your password'), 'attrs' => 'autocomplete="current-password"']) ?>
            <button class="btn btn-danger" type="submit"><?= e(__('Delete my account')) ?></button>
        </form>
    </details>
</div>
