CREATE TABLE IF NOT EXISTS `#__userreminder` (
    `userid`         INT(11) UNSIGNED NOT NULL,
    `datesent`       DATETIME NULL DEFAULT NULL,
    `remindernumber` INT(11) NOT NULL DEFAULT 0,
    `type`           INT(11) NOT NULL DEFAULT 0,
    `optoutcode`     VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`userid`),
    KEY `idx_ur_sent_type` (`datesent`, `remindernumber`, `type`),
    KEY `idx_ur_optoutcode` (`optoutcode`(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__userreminder_log` (
    `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userId`      INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `username`    VARCHAR(240) NOT NULL DEFAULT '',
    `description` TEXT NOT NULL,
    `message_key` VARCHAR(64) NOT NULL DEFAULT '',
    `date`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_userreminder_log_userId_date` (`userId`, `date`),
    KEY `idx_ur_log_date` (`date`),
    KEY `idx_ur_log_date_id` (`date`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__userreminder_optout` (
    `user_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__userreminder_optout_usergroups` (
    `group_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Record the schema version so freshly installed sites skip historical
-- update SQL (##VERSION## is replaced by the release build).
INSERT IGNORE INTO `#__schemas` (`extension_id`, `version_id`)
    SELECT `extension_id`, '##VERSION##'
      FROM `#__extensions`
     WHERE `element` = 'com_userreminder' AND `type` = 'component';