<?php

/**
 * @version    2.2.1
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_events\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Booking controller class.
 *
 * @since  2.0
 */
class BookingController extends FormController {

    protected $view_list = 'bookings';

    public function checkSchema() {

        $helper = New SchemaHelper;
// table ra_ bookings
        $details = '(
            id INT NOT NULL AUTO_INCREMENT,
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
        ) DEFAULT COLLATE=utf8mb4_unicode_ci; ';
        $helper->checkTable('ra_bookings', $details, '');

// table ra_ event_states

        $details = '(`id` int NOT NULL ,
            `seq` INT NOT NULL,
            `title` varchar(20) NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci; ';

        $data = "INSERT INTO `#__ra_event_states` (seq,id,title) VALUES
        (1,0,'Provisional'),
        (2,1,'Confirmed'),
        (3,-2, 'Cancelled');";
        $helper->checkTable('ra_event_states', $details, $data);

        // new fields in ra_events
        $helper->checkColumn('ra_events', 'publication_date', 'A', 'DATE NOT NULL AFTER attachment_description; ');
        $helper->checkColumn('ra_events', 'shareable', 'A', 'INT DEFAULT "0" AFTER publication_date; ');
        $helper->checkColumn('ra_events', 'share_date', 'A', 'DATE NOT NULL AFTER shareable; ');
        $helper->checkColumn('ra_events', 'bookable', 'A', 'INT DEFAULT "0" AFTER share_date;');
        $helper->checkColumn('ra_events', 'notify_organiser', 'A', 'INT DEFAULT "0" AFTER bookable; ');
        $helper->checkColumn('ra_events', 'booking_info', 'A', 'TEXT NULL AFTER notify_organiser; ');

        $helper->checkColumn('ra_events', 'num_places', 'A', 'INT NOT NULL DEFAULT "1" AFTER user_id; ');
        $helper->checkColumn('ra_events', 'partner', 'D', 'VARCHAR(50) NULL AFTER num_places; ');

        $helper->checkColumn('ra_bookings', 'num_places', 'A', 'INT NOT NULL DEFAULT "1" AFTER user_id; ');
        $helper->checkColumn('ra_bookings', 'partner', 'A', 'VARCHAR(50) NULL AFTER num_places; ');

        $sql = 'UPDATE #__ra_events SET publication_date="2025-05-01"';
        $this->objHelper->executeCommand($sql);
        $sql = 'UPDATE #__ra_events SET bookable=0 WHERE bookable IS NULL';
        $this->objHelper->executeCommand($sql);
        $sql = 'UPDATE #__ra_events SET notify_organiser=0 WHERE notify_organiser IS NULL';
        $this->objHelper->executeCommand($sql);
        $sql = 'UPDATE #__ra_bookings SET num_places=1 WHERE num_places IS NULL';
        $this->objHelper->executeCommand($sql);
    }

}
