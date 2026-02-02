/**
 * Author:  charles
 * Created: 15 Jun 2025
 */
CREATE TABLE IF NOT EXISTS `#__ra_api_sites` (
`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
`url` VARCHAR(100)  NOT NULL ,
`token` VARCHAR(255)  NOT NULL ,
`colour` VARCHAR(25)  NOT NULL ,
`sub_system` VARCHAR(10)  NOT NULL DEFAULT "RA Events",
`state` TINYINT(1)  NULL  DEFAULT 1,
`ordering` INT NULL  DEFAULT 0,
`checked_out` INT(11)  UNSIGNED,
`checked_out_time` DATETIME NULL  DEFAULT NULL ,
`created` DATETIME NULL  DEFAULT NULL ,
`created_by` INT(11)  NULL  DEFAULT 0,
`modified` DATETIME NULL  DEFAULT NULL ,
`modified_by` INT(11)  NULL  DEFAULT 0,
PRIMARY KEY (`id`)
) DEFAULT COLLATE=utf8mb4_unicode_ci; 
#-------------------------------------------------------------------------------