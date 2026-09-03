-- Migrate #__userreminder from MyISAM zero-date conventions to NULL + InnoDB conventions
-- (MySQL 8.0.13+ / Joomla 6 require NO_ZERO_DATE in sql_mode).

-- Update schema version (idempotent).
INSERT IGNORE INTO `#__schemas` (`version_id`, `schema_version`, `extension_id`)
    SELECT NULL, '4.0.0', `extension_id`
      FROM `#__extensions`
     WHERE `element` = 'com_userreminder' AND `type` = 'component';

-- Convert existing '0000-00-00 00:00:00' dates in #__userreminder to NULL.
UPDATE `#__userreminder`
   SET `datesent` = NULL
 WHERE `datesent` = '0000-00-00 00:00:00'
    OR `datesent` = '';

-- Switch engines and charset to InnoDB / utf8mb4. These ALTERs are no-ops when
-- the table already has the desired engine / collation.
ALTER TABLE `#__userreminder` ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `#__userreminder_log` ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `#__userreminder_sch` ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `#__userreminder_optout` ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `#__userreminder_optout_usergroups` ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add useful indexes. CREATE INDEX IF NOT EXISTS is MySQL 8.0.29+ / MariaDB 10.5+,
-- which is above Joomla 4's floor. We instead drop the index if it exists, then
-- create it. The DROP is a no-op when missing (error 1091) and the installer
-- will surface that as a fatal failure if it occurs.
ALTER TABLE `#__userreminder_log` DROP INDEX `idx_userreminder_log_userId_date`;
ALTER TABLE `#__userreminder_log` ADD INDEX `idx_userreminder_log_userId_date` (`userId`, `date`);
ALTER TABLE `#__userreminder_sch` DROP INDEX `idx_userreminder_sch_when`;
ALTER TABLE `#__userreminder_sch` ADD INDEX `idx_userreminder_sch_when` (`yearsent`, `monthsent`, `daysent`);