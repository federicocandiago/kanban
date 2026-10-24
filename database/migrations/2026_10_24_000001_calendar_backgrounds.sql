-- A calendar can have its own background, as a board (NULL: the user's colour, as before)
ALTER TABLE `calendar_accounts`
  ADD COLUMN `bg_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `all_day_default`,
  ADD COLUMN `bg_value` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `bg_type`,
  ADD COLUMN `bg_thumb` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `bg_value`,
  ADD COLUMN `bg_tint` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `bg_thumb`,
  ADD COLUMN `bg_credit_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `bg_tint`,
  ADD COLUMN `bg_credit_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `bg_credit_name`;
