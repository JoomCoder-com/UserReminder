-- UserReminder 4.2.0 — Scheduled Tasks cutover.
-- Scheduling moved from the page-hit system plugin (#__userreminder_sch
-- double-fire bookkeeping) to a TaskPlugin in com_scheduler.

DROP TABLE IF EXISTS `#__userreminder_sch`;

-- Fix historical log rows that stored untranslated language keys
-- (pre-4.2 SendService used keys without the COM_ prefix).
UPDATE `#__userreminder_log`
   SET `description` = REPLACE(`description`, 'USERREMINDER_ACTIVATE_REMINDER_SENT', 'Activation reminder sent')
 WHERE `description` LIKE '%USERREMINDER_ACTIVATE_REMINDER_SENT%';

UPDATE `#__userreminder_log`
   SET `description` = REPLACE(`description`, 'USERREMINDER_LOGIN_REMINDER_SENT', 'Login reminder sent')
 WHERE `description` LIKE '%USERREMINDER_LOGIN_REMINDER_SENT%';

UPDATE `#__userreminder_log`
   SET `description` = REPLACE(`description`, 'USERREMINDER_USER_REMINDER_SENT', 'User reminder sent')
 WHERE `description` LIKE '%USERREMINDER_USER_REMINDER_SENT%';
