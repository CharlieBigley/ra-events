# 20/09/23 define index within the table definition
# 24/09/23 add contact_id, remove agenda, ordering, organiser, email, contact_details
# 30/10/23 add customers and invoices
# 07/11/23 events: date as DATE, event_time_end and customer_id
# 02/12/24 delete unwanted fields, change description to title
# 09/12/24 CB add cat_id
# 18/12/24 CB default reports and agenda to NULL
# 04/03/24 CB add cat_id
# 05/03/25 CB 7 new fields to ra_events + ra_bookings + ra_event_states
# 06/03/25 CB change ra_event_type to ra_event_types
# 29/03/25 CB max_bookings
# 15/06/25 CB api_sites
# 19/06/25 CB additional fields in ra_events and api_sites
# 29/06/25 CB add indices to ra_events
# 07/07/25 CB removed api_sites
#-------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_bookings` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    `num_places` INT NOT NULL DEFAULT "1",
    `partner` VARCHAR(50) NULL ,
    state INT DEFAULT 0,
    created DATETIME NOT NULL,
    created_by INT NOT NULL,
    confirmed DATETIME NULL,
    confirmed_by INT NOT NULL DEFAULT 0,
    cancelled DATETIME NULL,
    cancelled_by INT NOT NULL DEFAULT 0,
PRIMARY KEY (`id`),
INDEX idx_event_id(event_id),
INDEX idx_userid(user_id)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

#-------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__ra_events` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` INT NULL ,
    `event_date` DATE NULL ,
    `event_date_end` DATE NULL ,
    `event_time` VARCHAR(5) NOT NULL DEFAULT "19:00",
    `event_time_end` VARCHAR(5) NULL,
    `event_type_id` INT  NOT NULL,
    `title` VARCHAR(255)  NULL DEFAULT "",
    `details` text  DEFAULT NULL,
    `reports` TEXT DEFAULT NULL,
    `minutes` TEXT DEFAULT NULL,
    `group_code` varchar(4) NOT NULL,
    `location` text NULL,
    `contact_id` int(11) DEFAULT "0",
    `cat_id` int(11) DEFAULT "0",
    `url` VARCHAR(255)  NULL  DEFAULT "",
    `url_description` VARCHAR(255)  NULL  DEFAULT "",
    `attachments` VARCHAR(255)  NULL  DEFAULT "",
    `attachment_description` VARCHAR(255)  NULL  DEFAULT "",
    `publication_date`DATETIME NULL , 
    `shareable` INT DEFAULT '0',
    `share_date` DATETIME NULL DEFAULT NULL,
    `bookable`INT DEFAULT '0',
    `max_bookings`INT DEFAULT '20',
    `num_bookings`INT DEFAULT '0',
    `notify_organiser`INT DEFAULT '0',
    `booking_info` TEXT DEFAULT NULL,
    `api_site_id` INT NULL,  
    `original_id` INT NULL,  
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,   
    `created_by` INT NULL DEFAULT "0",
    `modified` DATETIME NULL DEFAULT NULL,
    `modified_by` INT NULL DEFAULT "0",
    `checked_out_time` DATETIME NULL  DEFAULT NULL ,
    `checked_out` INT NULL,  
    `state` TINYINT(1)  NULL  DEFAULT 1,

    PRIMARY KEY (`id`),
    INDEX idx_event_type_id(event_type_id),
    INDEX idx_api_site_id(api_site_id),
    INDEX idx_original_id(original_id)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

#-------------------------------------------------------------------------------
DROP TABLE IF EXISTS `#__ra_event_states`;
CREATE TABLE `#__ra_event_states` (
    id INT NOT NULL,
    seq INT NOT NULL,
    title VARCHAR(11),
PRIMARY KEY (`id`)
) DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__ra_event_states` (seq,id,title) VALUES
(1,0,'Provisional'),
(2,1,'Confirmed'),
(3,-2, 'Cancelled');
#-------------------------------------------------------------------------------
DROP TABLE IF EXISTS `#__ra_event_types`;
CREATE TABLE IF NOT EXISTS `#__ra_event_types` (
    `id` int(11) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `description` varchar(20) NOT NULL,
    `ordering` INT NOT NULL DEFAULT 0,
    `state` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci;

INSERT INTO `#__ra_event_types` (`description`,`ordering`) VALUES
    ('Committee meeting',10),
    ('Social event',20),
    ('Training',30),
    ('Holiday/weekend',40);
#-------------------------------------------------------------------------------
