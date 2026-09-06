CREATE TABLE IF NOT EXISTS `#__userreminder` (
    `userid`         INT(11) UNSIGNED NOT NULL,
    `datesent`       DATETIME NULL DEFAULT NULL,
    `remindernumber` INT(11) NOT NULL DEFAULT 0,
    `type`           INT(11) NOT NULL DEFAULT 0,
    `optoutcode`     VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`userid`),
    KEY `idx_ur_sent_type` (`datesent`, `remindernumber`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__userreminder_log` (
    `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userId`      INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `username`    VARCHAR(240) NOT NULL DEFAULT '',
    `description` TEXT NOT NULL,
    `date`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_userreminder_log_userId_date` (`userId`, `date`),
    KEY `idx_ur_log_date` (`date`),
    KEY `idx_ur_log_date_id` (`date`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__userreminder_sch` (
    `id`        INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `daysent`   TINYINT(2) UNSIGNED NOT NULL DEFAULT 0,
    `monthsent` TINYINT(2) UNSIGNED NOT NULL DEFAULT 0,
    `yearsent`  INT(4) NOT NULL DEFAULT 0,
    `timesent`  INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_userreminder_sch_when` (`yearsent`, `monthsent`, `daysent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__userreminder_optout` (
    `user_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__userreminder_optout_usergroups` (
    `group_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record the schema version so update SQL is skipped on fresh installs.
INSERT IGNORE INTO `#__schemas` (`extension_id`, `version_id`)
    SELECT `extension_id`, '6.0.0'
      FROM `#__extensions`
     WHERE `element` = 'com_userreminder' AND `type` = 'component';