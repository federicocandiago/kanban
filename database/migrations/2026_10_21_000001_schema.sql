-- ─────────────────────────────────────────────────────────────────────────────
-- Kalban — database schema.
-- Applied automatically on the first request when the tables are missing
-- (or with: php bin/migrate.php). It can also be imported as-is with phpMyAdmin.
-- Future changes go in new files in this folder (e.g. 2026_11_01_000001_something.sql),
-- applied in name order, once each (tracked in the `migrations` table).
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `verification_token` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_sent_at` datetime DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `locale` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  -- interface colour (App\Services\Palette) and time zone of the calendar (empty: APP_TIMEZONE)
  `color` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sky',
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  -- calendar: minutes between start and end set when the start changes (empty: EVENT_DEFAULT_MINUTES)
  `event_minutes` smallint unsigned DEFAULT NULL,
  -- the user turned on access to the API (Settings › API access)
  `api_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `suspended_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_verification_token` (`verification_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- archived_at = soft delete (the board goes to «Archived», restorable; only the owner deletes it for good).
-- bg_type: color (bg_value = palette key) or photo (bg_value = Unsplash image URL, bg_tint = its average colour).
CREATE TABLE IF NOT EXISTS `boards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` int unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bg_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'color',
  `bg_value` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue',
  `bg_thumb` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bg_tint` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bg_credit_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bg_credit_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_boards_owner` (`owner_id`),
  CONSTRAINT `fk_boards_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Everyone who can open a board, owner included (role = owner | member). Favourites are per user.
CREATE TABLE IF NOT EXISTS `board_members` (
  `board_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `is_favorite` tinyint(1) NOT NULL DEFAULT '0',
  `last_opened_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`board_id`, `user_id`),
  KEY `idx_members_user` (`user_id`),
  CONSTRAINT `fk_members_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invitations by email. user_id is set when the address belongs to an account (now or when it signs up).
CREATE TABLE IF NOT EXISTS `board_invites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `board_id` int unsigned NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `invited_by` int unsigned NOT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invites_board` (`board_id`),
  KEY `idx_invites_email` (`email`),
  KEY `idx_invites_user` (`user_id`),
  CONSTRAINT `fk_invites_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invites_by` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lists` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `board_id` int unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lists_board` (`board_id`, `position`),
  CONSTRAINT `fk_lists_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `board_id` int unsigned NOT NULL,
  `list_id` int unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cards_list` (`list_id`, `position`),
  KEY `idx_cards_board` (`board_id`),
  CONSTRAINT `fk_cards_board` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cards_list` FOREIGN KEY (`list_id`) REFERENCES `lists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cards_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Labels belong to a user (each one keeps their own set); on a card everybody sees them.
CREATE TABLE IF NOT EXISTS `labels` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_labels_user` (`user_id`),
  CONSTRAINT `fk_labels_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `card_labels` (
  `card_id` int unsigned NOT NULL,
  `label_id` int unsigned NOT NULL,
  PRIMARY KEY (`card_id`, `label_id`),
  KEY `idx_card_labels_label` (`label_id`),
  CONSTRAINT `fk_card_labels_card` FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_card_labels_label` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `card_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` int unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attachments_card` (`card_id`),
  CONSTRAINT `fk_attachments_card` FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attachments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Change log under each card: who, when, which field, old value → new value.
-- user_name is copied so the log still reads well after the account is deleted.
CREATE TABLE IF NOT EXISTS `card_activity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `card_id` int unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_card` (`card_id`, `id`),
  CONSTRAINT `fk_activity_card` FOREIGN KEY (`card_id`) REFERENCES `cards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification dropdown. type: invite | invite_accepted | invite_declined | removed | left. data: JSON.
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`, `id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Calendar (CalDAV, e.g. iCloud): any number of accounts per user ──────────
-- The remote calendar is the source of truth; the tables below are a cache (months already
-- fetched, with the ctag they were fetched at) plus the change log, which CalDAV can't hold.

-- name: given by the user ("Personale", "Lavoro"); secret: the app-specific password, encrypted with APP_KEY (sodium secretbox)
CREATE TABLE IF NOT EXISTS `calendar_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'caldav',
  `server_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `home_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachments` tinyint(1) NOT NULL DEFAULT '0',
  `default_calendar_id` int unsigned DEFAULT NULL,
  `all_day_default` tinyint(1) NOT NULL DEFAULT '0',
  `synced_at` datetime DEFAULT NULL,
  `retry_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_calendar_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sub-calendars (only those holding events). url_hash = sha1(url), for a short unique key.
CREATE TABLE IF NOT EXISTS `calendars` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_hash` char(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ctag` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `writable` tinyint(1) NOT NULL DEFAULT '1',
  `owner_name` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_url` (`account_id`, `url_hash`),
  CONSTRAINT `fk_calendars_account` FOREIGN KEY (`account_id`) REFERENCES `calendar_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Months in the cache: refetched only when the sub-calendar's ctag changes.
CREATE TABLE IF NOT EXISTS `calendar_months` (
  `calendar_id` int unsigned NOT NULL,
  `month` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ctag` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fetched_at` datetime NOT NULL,
  PRIMARY KEY (`calendar_id`, `month`),
  CONSTRAINT `fk_calendar_months_calendar` FOREIGN KEY (`calendar_id`) REFERENCES `calendars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per occurrence (recurring events are expanded by the server: recurrence_id set).
-- starts_at / ends_at in UTC; start_day / end_day (inclusive) in the user's time zone, to group by day.
CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `calendar_id` int unsigned NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url_hash` char(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recurrence_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `uid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `etag` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` text COLLATE utf8mb4_unicode_ci,
  `organizer` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `start_day` date NOT NULL,
  `end_day` date NOT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `recurring` tinyint(1) NOT NULL DEFAULT '0',
  `attachments` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `occurrence` (`calendar_id`, `url_hash`, `recurrence_id`),
  KEY `idx_calendar_events_days` (`calendar_id`, `start_day`, `end_day`),
  CONSTRAINT `fk_calendar_events_calendar` FOREIGN KEY (`calendar_id`) REFERENCES `calendars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Change log of events, by UID (it survives moving the event to another sub-calendar).
-- user_name '' = changed outside the app (found while syncing).
CREATE TABLE IF NOT EXISTS `event_activity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL,
  `uid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `field` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_activity_uid` (`account_id`, `uid`(191), `id`),
  CONSTRAINT `fk_event_activity_account` FOREIGN KEY (`account_id`) REFERENCES `calendar_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API token, one per user: only its SHA-256 hash is kept. scope: read | write.
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hint` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'read',
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `reminded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limits (App\Core\Throttle): attempts per hashed key in a time window. No IP addresses.
CREATE TABLE IF NOT EXISTS `throttle` (
  `key` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hits` int unsigned NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`key`),
  KEY `idx_throttle_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Six starting labels for every existing user, in the first six colours of the palette and without
-- a name (as Trello did); new users get the same on sign-up (App\Models\Label::DEFAULTS).
INSERT INTO labels (user_id, name, color, created_at, updated_at)
SELECT u.id, '', c.color, NOW(), NOW()
FROM users u
CROSS JOIN (
    SELECT 'green' AS color, 1 AS sort UNION ALL SELECT 'yellow', 2 UNION ALL SELECT 'orange', 3
    UNION ALL SELECT 'red', 4 UNION ALL SELECT 'purple', 5 UNION ALL SELECT 'blue', 6
) c
WHERE NOT EXISTS (SELECT 1 FROM labels l WHERE l.user_id = u.id)
ORDER BY u.id, c.sort;
