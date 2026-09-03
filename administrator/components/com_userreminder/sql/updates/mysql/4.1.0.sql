-- 4.1.0 — dashboard rebuild: add covering indexes for fast COUNTs and trend queries.
-- Own tables only — never touch #__users (Joomla core).
-- Idempotent: DROP IF EXISTS before ADD so fresh installs and upgrades both succeed.
-- Requires MySQL 8.0.23+ / MariaDB 10.1.8+ (satisfied by Joomla 5 floor: MySQL 8.0.13+ / MariaDB 10.4+).

-- #__userreminder — supports due-now and aging counts filtered on datesent/remindernumber/type.
ALTER TABLE `#__userreminder` DROP INDEX IF EXISTS `idx_ur_sent_type`;
ALTER TABLE `#__userreminder` ADD INDEX `idx_ur_sent_type` (`datesent`, `remindernumber`, `type`);

-- #__userreminder_log — trend (WHERE date >= X GROUP BY DATE(date)) and retention prune.
ALTER TABLE `#__userreminder_log` DROP INDEX IF EXISTS `idx_ur_log_date`;
ALTER TABLE `#__userreminder_log` ADD INDEX `idx_ur_log_date` (`date`);

-- Composite for ORDER BY id DESC pagination when filtered by date (covers both).
ALTER TABLE `#__userreminder_log` DROP INDEX IF EXISTS `idx_ur_log_date_id`;
ALTER TABLE `#__userreminder_log` ADD INDEX `idx_ur_log_date_id` (`date`, `id`);

-- Bump schema version for this update.
INSERT IGNORE INTO `#__schemas` (`extension_id`, `version_id`)
    SELECT `extension_id`, '4.1.0'
      FROM `#__extensions`
     WHERE `element` = 'com_userreminder' AND `type` = 'component';
