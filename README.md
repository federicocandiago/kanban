# Kalban 🫑

Self-hosted kanban and calendar in the style of the 2018 Trello: boards, lists and cards, shared between users.
PHP 8.2+ and MySQL, Bootstrap 5.3 served locally, a few hundred lines of in-house JavaScript.
No Composer/npm dependencies at runtime, no CDN. Same structure as [DOSHost](https://github.com/federicocandiago/doshost).

## Quick start

```bash
cp .env.example .env        # then edit DB_*, ADMIN_* and, optionally, UNSPLASH_ACCESS_KEY
php bin/key.php             # APP_KEY, to keep calendar passwords encrypted
php bin/migrate.php         # optional: the site creates the tables and the admin on its first visit
./serve.sh                  # http://localhost:8000 (PORT=8090 ./serve.sh for another port)
```

`php bin/migrate.php --fresh` drops every table and runs the migrations again.

## What it does

- **Boards** with a colour from the classic palette or an Unsplash photo (12 results, with search; needs
  `UNSPLASH_ACCESS_KEY`). Favourites, search, archive (soft delete) and, for the owner only, delete for good.
- **Lists and cards** with drag & drop (mouse, and touch with press-and-hold), quick add at the bottom of each
  list (Enter adds, Esc closes), archive and restore from the board menu.
- **Card**: name (the only required field), description, start date, end date, attachments, labels.
  Every change goes to the card's log: date, user, field, old value → new value.
- **Labels** belong to each user (six classic colours to start with), editable from Settings.
- **Sharing**, per board only. The owner (whoever created the board) invites by email: users with an account get
  a notification with Accept / Decline; other addresses get an email and find the invitation waiting when they sign
  up (even with registration closed). People already met on other boards are offered as one-click chips.
  The owner can remove members and withdraw invitations; members can leave.
- **Notifications** in the bell dropdown next to the user menu.
- **Global search** (magnifier in the top bar, or `/` / Cmd-Ctrl+K): a preview while typing, Enter for the full
  results page. It looks in what the user can see — boards, lists, cards (name, description, labels), attachments —
  and in the calendar events already in the local cache (the months opened at least once; the remote calendar is
  not queried while typing). Inside a board or the calendar, the magnifier button filters the cards on screen.
- **Calendars** (optional, as many as the user wants, each with its own name): iCloud or any standard CalDAV
  server. They have the first row of the dashboard and each one opens as a month shown like a board, one list per day: events are cards with their sub-calendar as
  label, they can be dragged to another day, created with quick add, renamed, timed, moved to another sub-calendar,
  deleted, with attachments (RFC 8607, iCloud supports them) and a change log. Recurring events, read-only
  calendars and the following days of multi-day events are shown dimmed and can only be opened. On wider screens
  the month opens `CALENDAR_START_OFFSET_DAYS` days from today (default -1: yesterday is visible too); phones open
  on today. Quick add makes whole-day events, or (each calendar's setting, starting from `CALENDAR_NEW_ALL_DAY`,
  default off) opens a "new event" window from now to the user's default length, saved only on "Confirm".
- **Notes**: "Add a note" next to Activity, in cards and calendar events: notes go into the log in time order
  (author and board owner can delete them, the author can change them); card tiles show how many. Event notes stay
  in Kalban, the calendar server never sees them.
- **Export / import** (Settings › Account and data): one JSON file per user that names nobody (no ids, emails,
  passwords, tokens or calendar credentials) with preferences, labels and the owned boards (archived too) with
  lists, cards, log, notes and, optionally, attachments in base64. Importing adds the boards as new ones.
- **Install on a phone**: `public/manifest.webmanifest` (full screen, the app icon), so browsers offer "Add to
  Home screen".
- **Board look**: the top bar and the board header are one band of frosted glass tinted with the photo's average
  colour (from Unsplash) or the board's colour; lists are light frosted glass; cards stay solid.
- **Dashboard**: three sections — Calendars, Favourites (only when there are some) and My boards (the others) —
  that close with a click on their title and stay so; next to the search box the order: last changed (opened by
  the user, or the board and its cards changed), name, date created (remembered in the browser). Each tile shows
  the day it was last changed. Opening a board, its tile grows into the board page (view transitions, in Chrome,
  Edge and Safari): `VIEW_TRANSITIONS=false` turns them off, `VIEW_TRANSITION_SECONDS` sets how long they last.
- **Settings**: name, language (it/en), theme (automatic, light, dark), colour, profile picture, password, labels,
  delete account. The colour (also asked at sign-up) tints the top bar, the boards page and is the starting
  background of new boards.
- **Palette**: twelve colours in `app/Services/Palette.php`, each with a primary shade (board backgrounds, labels,
  dashboard) and a more intense secondary one (top bar). The `.label-*` classes and the `--k-accent` / `--k-header`
  variables are generated from there, so it is the only list to edit.
- **Attachments** up to `ATTACHMENT_MAX_MB` (lowered automatically to PHP's `upload_max_filesize` / `post_max_size`):
  the limit is shown next to the button and a larger file is refused in the browser before uploading.
- **Back-office** for admins: users (admin role, pause, delete), registration open/closed.

## Structure

```
app/Core/         mini framework: Env, Database, Router, Request, Response, View,
                  Session, Auth, Csrf, Middleware, Translator, Migrator
app/Models/       User, Board, BoardList, Card, Label, Attachment, Activity, Invite, Notification, Setting
app/Services/     CalDav, ICal, CalendarService, Palette, Unsplash, AttachmentService, AvatarService, Mailer, Mail
app/Controllers/  Board, List, Card, Share, Label, Notification, Account, Auth, Admin, Page, Home
routes/web.php    every route with its middleware (auth, guest, admin)
views/            layouts/, components/, pages/, partials/ (pieces the server also sends alone), emails/
lang/             messages.pot + {en,it}/LC_MESSAGES/messages.po
public/assets/js/ app.js (fetch + data-* attributes), dnd.js (drag & drop), board.js (board page)
storage/          attachments/{board id}/, avatars/, cache/ (Unsplash), logs/
```

### How the page talks to the server

There is no client-side templating. Forms with `data-ajax` are sent with `fetch`; the answer is JSON
`{ok, message, errors, redirect, reload, close, html, append, remove}` where `html` / `append` map a CSS selector to
markup rendered by the server (`views/partials`). So a new card is appended as the server drew it, a saved card
redraws its tile and its log, and so on. Other attributes: `data-autosubmit` (save on change), `data-post`,
`data-action` + `data-confirm`, `data-filter` (search on the page), `data-load` / `data-load-from` (remote markup),
`data-enter-submit`. See the comment at the top of `public/assets/js/app.js`.

`dnd.js` is a small sortable on Pointer Events: the dragged element moves through the DOM as its own placeholder,
a tilted copy follows the pointer, the board scrolls near its edges. On drop, `board.js` sends the new order.

### Roles

The creator of a board is its owner, everyone else is a member. Members can do everything on lists and cards,
rename the board and change its background, archive and restore. Only the owner invites and removes people,
archives the board, and deletes for good (board, lists, cards).

## Calendar (CalDAV)

Settings › Calendars › Add a calendar: a name for it ("Personal", "Work"), then for iCloud the Apple ID and an **app-specific password** (account.apple.com › Sign-in and
security), for other servers also their address (Nextcloud, Fastmail, Synology, Radicale, Baïkal…; Google and
Outlook are not supported). The password is stored encrypted with `APP_KEY` (`php bin/key.php` creates it).

- The remote calendar is the source of truth. The tables `calendars`, `calendar_months`, `calendar_events` are a
  cache: the page is drawn from it at once, then `/calendars/{id}/sync` asks the server. One PROPFIND tells, through the
  ctag of each sub-calendar, what changed; only those sub-calendars are fetched again, only for the month on screen
  (recurring events are expanded by the server). At most every `CALENDAR_SYNC_SECONDS`; when the server throttles
  (429/503) the account waits as long as `Retry-After` says.
- Writes go to the server first, on a fresh copy (GET, then PUT with `If-Match`): a change made meanwhile on another
  device is never overwritten. Moving to another sub-calendar is a WebDAV `MOVE` (iCloud refuses two copies of one
  UID). Attachments are added with RFC 8607 and removed by saving the event without them (the way iCloud accepts).
- Changes found while syncing are logged as "changed outside the app". Events are shown and saved in the user's
  time zone (Settings › Profile). Changing the start of an event sets its end N minutes later: N is in Settings ›
  Profile, by default `EVENT_DEFAULT_MINUTES` (30).
- Code: `app/Services/CalDav.php` (client), `ICal.php` (iCalendar reader/writer that keeps what it doesn't touch),
  `CalendarService.php` (cache, sync, changes), `app/Controllers/CalendarController.php`.

## API

REST API for boards, lists, cards, labels and attachments (the calendar is not part of it), documented at
**`/api/docs`** — a page laid out like Swagger, with curl examples and "Try it" — from the OpenAPI 3.1 document at
`/api/v1/openapi.json` (`app/Services/ApiSpec.php`, importable in Postman or Insomnia).

- Settings › API access: the user turns it on, then generates a token (read only, or read and change). It is
  shown once; only its SHA-256 hash is stored. "Regenerate" replaces it at once; turning access off deletes it.
  A week before it expires a notification appears in the bell.
- `.env`: `API_ENABLED` (off: no API for anybody), `API_TOKEN_DAYS` (lifetime), `THROTTLE_API_PER_MINUTE`.
- Requests send `Authorization: Bearer kalban_…`; no session, no cookies, no CSRF. A token can do what its user can
  do on the site (member / owner rules), and changes appear in the card log as "Name (API)".
- Answers are `{"data": …}`; errors are RFC 9457 `application/problem+json` (401, 403, 404, 409, 422 with
  `errors`, 429 with `Retry-After`).
- The site and the API share the same rules: `app/Services/BoardService.php`, `ListService.php`, `CardService.php`;
  the API controllers are in `app/Controllers/Api/`.

## Rate limits

`app/Core/Throttle.php` counts attempts per key in fixed windows, all configurable in `.env` (`THROTTLE_*`):
wrong passwords at sign-in per account (5 in 15 minutes, then a pause), wrong current password when changing it or
deleting the account, new confirmation emails per address, invitations per user, calendar connections per user, and
sign-ups with a single counter for the whole site. No IP address is stored: keys are SHA-256 hashes, and expired
counters are deleted along the way. A blocked request gets HTTP 429 and a message saying how long to wait.

## Translations

Strings are in English in the code: `__('Hello, :name', ['name' => $n])`.

```bash
php bin/i18n.php   # regenerates lang/messages.pot and adds the new strings to the .po files
```

The `.po` files can be edited with any editor or with Poedit; no need to compile them to `.mo`.

## Unsplash

Create a free app at <https://unsplash.com/developers> and put its Access Key in `UNSPLASH_ACCESS_KEY`.
The key stays on the server (the browser asks `/backgrounds`, which asks Unsplash); photos are hotlinked and
credited, and the download is tracked when one becomes a background, as the API guidelines require.
Only API calls count against the limit (50 per hour for a demo key, 5,000 once Unsplash approves the app for
production), never the images, which browsers also cache. Answers are kept in `storage/cache`: the default photos
for a day, searches for six hours, each photo for a week, so choosing a photo seen in the picker costs one call
(the download tracking). Without a key only the colours are offered.

## Shared hosting

Same as DOSHost: PHP 8.2+ with `pdo_mysql`, `gd`, `mbstring`, `fileinfo`, `dom`, `sodium`, `curl` (outgoing HTTPS for the calendar and Unsplash),
MySQL 5.7+ / MariaDB 10.3+, Apache with `mod_rewrite`. Best layout: the project outside the web folder, only the
content of `public/` inside it (`public/index.php` also looks for the project in `../kalban`). Data lives in
`storage/` (or `STORAGE_PATH`), never inside the web folder. `public/.user.ini` raises the upload limit to 25 MB:
keep it above `ATTACHMENT_MAX_MB`. Database changes go in new `database/migrations/*.sql` files, applied
automatically on the first request that needs them.

## License

[MIT](LICENSE). Bootstrap 5.3.3 (MIT) is bundled in `public/vendor/`.
