-- Public boards: whoever has the address can open them. NULL: members only; 'read': read and add
-- notes; 'edit': anything but deleting (archiving included, everything stays in the log).
ALTER TABLE `boards` ADD COLUMN `public_mode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `bg_credit_url`;
