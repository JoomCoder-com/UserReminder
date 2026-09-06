-- UserReminder 6.0.0 — full component rebuild migration.
-- Single update file covering any pre-6.0.0 schema (last released: 5.2.3 —
-- MyISAM tables, UNIQUE KEY `userid`, no #__schemas row).
-- Every statement is safe to run against both the 5.2.3 schema and an
-- already-rebuilt one (idempotent). Index/PK surgery is done in script.php
-- (information_schema guarded) because MySQL aborts on duplicate index names
-- and MariaDB-only `DROP INDEX IF EXISTS` is not portable.

-- ----------------------------------------------------------------- data fixes

-- Zero/garbage dates would break the nullable conversion below
-- (MySQL 8.0.13+ / Joomla 6 force NO_ZERO_DATE in sql_mode).
UPDATE `#__userreminder`
   SET `datesent` = NULL
 WHERE `datesent` IS NOT NULL
   AND `datesent` < '1000-01-01 00:00:00';

-- NOT NULL columns must not carry NULLs before the MODIFYs below.
UPDATE `#__userreminder` SET `remindernumber` = 0 WHERE `remindernumber` IS NULL;
UPDATE `#__userreminder` SET `type`           = 0 WHERE `type` IS NULL;
UPDATE `#__userreminder` SET `optoutcode`     = '' WHERE `optoutcode` IS NULL;
UPDATE `#__userreminder_optout`         SET `user_id`  = 0 WHERE `user_id`  IS NULL;
UPDATE `#__userreminder_optout_usergroups` SET `group_id` = 0 WHERE `group_id` IS NULL;
UPDATE `#__userreminder_log`            SET `userId`   = 0 WHERE `userId`   IS NULL;
UPDATE `#__userreminder_log`            SET `username` = '' WHERE `username` IS NULL;

-- Fix historical log rows that stored untranslated language keys
-- (pre-rebuild SendService used keys without the COM_ prefix).
UPDATE `#__userreminder_log`
   SET `description` = REPLACE(`description`, 'USERREMINDER_ACTIVATE_REMINDER_SENT', 'Activation reminder sent')
 WHERE `description` LIKE '%USERREMINDER_ACTIVATE_REMINDER_SENT%';

UPDATE `#__userreminder_log`
   SET `description` = REPLACE(`description`, 'USERREMINDER_LOGIN_REMINDER_SENT', 'Login reminder sent')
 WHERE `description` LIKE '%USERREMINDER_LOGIN_REMINDER_SENT%';

UPDATE `#__userreminder_log`
   SET `description` = REPLACE(`description`, 'USERREMINDER_USER_REMINDER_SENT', 'User reminder sent')
 WHERE `description` LIKE '%USERREMINDER_USER_REMINDER_SENT%';

-- The legacy 5.2.x scheduler also stored a raw language-key prefix in front of
-- every description. Strip it so no raw keys remain in the table.
UPDATE `#__userreminder_log`
   SET `description` = TRIM(REPLACE(`description`, 'COM_USERREMINDER_LOG_PREFIX', ''))
 WHERE `description` LIKE '%COM_USERREMINDER_LOG_PREFIX%';

-- ------------------------------------------------------------- engine/charset

-- MyISAM → InnoDB + utf8 → utf8mb4 (no-op on already-converted tables).
ALTER TABLE `#__userreminder` ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__userreminder_log` ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__userreminder_optout` ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `#__userreminder_optout_usergroups` ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ------------------------------------------------------------------- columns

ALTER TABLE `#__userreminder`
    MODIFY `userid`         INT(11) UNSIGNED NOT NULL,
    MODIFY `datesent`       DATETIME NULL DEFAULT NULL,
    MODIFY `remindernumber` INT(11) NOT NULL DEFAULT 0,
    MODIFY `type`           INT(11) NOT NULL DEFAULT 0,
    MODIFY `optoutcode`     VARCHAR(255) NOT NULL DEFAULT '';

ALTER TABLE `#__userreminder_log`
    MODIFY `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY `userId`      INT(11) UNSIGNED NOT NULL DEFAULT 0,
    MODIFY `username`    VARCHAR(240) NOT NULL DEFAULT '',
    MODIFY `date`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `#__userreminder_optout`
    MODIFY `user_id` INT(11) UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE `#__userreminder_optout_usergroups`
    MODIFY `group_id` INT(11) UNSIGNED NOT NULL DEFAULT 0;

-- ------------------------------------------------------- dropped/added tables

-- Scheduling moved to the plg_task_userreminder TaskPlugin (com_scheduler);
-- the page-hit bookkeeping table is obsolete.
DROP TABLE IF EXISTS `#__userreminder_sch`;
