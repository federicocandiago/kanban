<?php /** Settings › Calendars: the connected CalDAV accounts (rename, new credentials, disconnect) and a form to add one. @var App\Models\CalendarAccount[] $accounts */ ?>
<div id="calendar-panel">
    <?php foreach ($accounts as $account): ?>
        <details class="calendar-account" id="calendar-<?= (int) $account->id ?>">
            <summary>
                <span class="calendar-account-name">
                    <strong class="text-truncate"><?= e($account->name) ?></strong>
                    <small class="text-muted text-truncate"><?= e($account->label()) ?></small>
                </span>
                <a class="btn btn-sm btn-success" href="<?= e($account->url()) ?>"><?= e(__('Open')) ?></a>
                <span class="btn btn-sm btn-secondary"><?= e(__('Edit')) ?></span>
            </summary>
            <div class="calendar-account-body">
                <ul class="calendar-list">
                    <?php foreach ($account->calendars() as $calendar): ?>
                        <li>
                            <span class="label" style="--lc: <?= e($calendar->color()) ?>"><?= e($calendar->name) ?></span>
                            <?php if (!$calendar->writable): ?><small class="text-muted"><?= e(__('read-only')) ?></small><?php endif ?>
                            <?php if ($calendar->owner_name): ?><small class="text-muted"><?= e(__('shared by :name', ['name' => $calendar->owner_name])) ?></small><?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
                <p class="form-text">
                    <?= e($account->attachments ? __('This server accepts attachments on events.') : __('This server does not accept attachments on events.')) ?>
                    <?= e(__('Last sync: :date', ['date' => format_date($account->synced_at)])) ?>
                </p>
                <form method="post" action="<?= e($account->url('/update')) ?>" data-ajax novalidate>
                    <?= csrf_field() ?>
                    <?= component('field', ['prefix' => 'cal' . (int) $account->id . '-', 'name' => 'name', 'label' => __('Name'), 'value' => $account->name, 'attrs' => 'maxlength="100" required']) ?>
                    <?php $writable = array_filter($account->calendars(), static fn ($c) => (bool) $c->writable); ?>
                    <?php if (count($writable) > 1): ?>
                        <div class="mb-3">
                            <label class="form-label" for="f-cal<?= (int) $account->id ?>-default"><?= e(__('Default calendar')) ?></label>
                            <select class="form-select" id="f-cal<?= (int) $account->id ?>-default" name="default_calendar_id">
                                <?php foreach ($writable as $calendar): ?>
                                    <option value="<?= (int) $calendar->id ?>" <?= (int) $calendar->id === (int) ($account->defaultCalendar()->id ?? 0) ? 'selected' : '' ?>><?= e($calendar->name) ?></option>
                                <?php endforeach ?>
                            </select>
                            <div class="form-text"><?= e(__('New events are created in this one.')) ?></div>
                        </div>
                    <?php endif ?>
                    <label class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" name="all_day_default" value="1" <?= $account->all_day_default ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= e(__('New events are whole-day')) ?></span>
                    </label>
                    <p class="form-text mt-0 mb-3"><?= e(__('Off: an event added at the bottom of a day opens first in a window, from now to :n minutes later, to check it before saving.', ['n' => auth()->eventMinutes()])) ?></p>
                    <?php if ($account->provider === 'caldav'): ?>
                        <?= component('field', ['prefix' => 'cal' . (int) $account->id . '-', 'name' => 'server_url', 'type' => 'url', 'label' => __('Server address'), 'value' => $account->server_url]) ?>
                    <?php endif ?>
                    <div class="row g-3">
                        <div class="col-sm-6"><?= component('field', ['prefix' => 'cal' . (int) $account->id . '-', 'name' => 'username', 'label' => $account->provider === 'icloud' ? __('Apple ID') : __('Username'), 'value' => $account->username, 'attrs' => 'autocomplete="username"']) ?></div>
                        <div class="col-sm-6"><?= component('field', ['prefix' => 'cal' . (int) $account->id . '-', 'name' => 'password', 'type' => 'password', 'label' => __('New app-specific password'), 'attrs' => 'autocomplete="new-password"',
                            'help' => __('Leave it empty to keep the current one.')]) ?></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-success" type="submit"><?= e(__('Save')) ?></button>
                        <button class="btn btn-secondary ms-auto" type="button" data-action="<?= e($account->url('/delete')) ?>"
                                data-confirm="<?= e(__('The calendar “:name” will be disconnected from this site; nothing changes on the calendar itself.', ['name' => $account->name])) ?>"><?= e(__('Disconnect')) ?></button>
                    </div>
                </form>
            </div>
        </details>
    <?php endforeach ?>

    <details class="calendar-account calendar-add" id="calendar-new" <?= $accounts ? '' : 'open' ?>>
        <summary><span class="calendar-account-name"><strong><?= component('icon', ['name' => 'plus', 'size' => 14]) ?> <?= e(__('Add a calendar')) ?></strong></span></summary>
        <form class="calendar-connect calendar-account-body" method="post" action="<?= base_url() ?>/calendars" data-ajax novalidate>
            <?= csrf_field() ?>
            <div class="d-flex gap-3 mb-3">
                <label class="form-check"><input class="form-check-input" type="radio" name="provider" value="icloud" checked> <?= e(__('iCloud')) ?></label>
                <label class="form-check"><input class="form-check-input" type="radio" name="provider" value="caldav"> <?= e(__('Other CalDAV server')) ?></label>
            </div>
            <?= component('field', ['prefix' => 'cal-new-', 'name' => 'name', 'label' => __('Name'), 'attrs' => 'maxlength="100" required placeholder="' . e(__('Personal, Work…')) . '"',
                'help' => __('How it is called on your dashboard.')]) ?>
            <div class="calendar-server">
                <?= component('field', ['prefix' => 'cal-new-', 'name' => 'server_url', 'type' => 'url', 'label' => __('Server address'), 'attrs' => 'placeholder="https://cloud.example.com/remote.php/dav/"',
                    'help' => __('Nextcloud, Fastmail, Synology, Radicale, Baïkal… (Google and Outlook are not supported).')]) ?>
            </div>
            <div class="row g-3">
                <div class="col-sm-6"><?= component('field', ['prefix' => 'cal-new-', 'name' => 'username', 'label' => __('Apple ID / username'), 'attrs' => 'autocomplete="username"']) ?></div>
                <div class="col-sm-6"><?= component('field', ['prefix' => 'cal-new-', 'name' => 'password', 'type' => 'password', 'label' => __('App-specific password'), 'attrs' => 'autocomplete="new-password"']) ?></div>
            </div>
            <p class="form-text"><?= __('For iCloud create an app-specific password at <a href="https://account.apple.com" target="_blank" rel="noopener">account.apple.com</a> › Sign-in and security. It is stored encrypted and used only to read and write your events.') ?></p>
            <?php if (!App\Core\Crypt::ready()): ?>
                <p class="alert alert-warning small"><?= e(__('The administrator must set APP_KEY in .env first (php bin/key.php).')) ?></p>
            <?php endif ?>
            <button class="btn btn-success" type="submit"><?= e(__('Connect')) ?></button>
        </form>
    </details>
</div>
