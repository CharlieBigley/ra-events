<?php

/**
 * @version    2.1.1
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 04/03/25 CB Created
 * 29/03/25 CB max_bookings, ra_event_types
 */

namespace Ramblers\Component\Ra_events\Administrator\Controller;

\defined('_JEXEC') or die;

//use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
// use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;

/**
 * Booking class.
 *
 * @since  4.1.0
 */
class SystemController extends FormController {

    protected $app;
    protected $toolsHelper;
    protected $view_list = 'bookings';

//    protected $params;

    public function __construct() {
        parent::__construct();
        $this->app = Factory::getApplication();
//        $id = $this->app->input->getInt('id', '0');
//        $this->table = Factory::getApplication()->bootComponent('com_ra_events')->getMVCFactory()->createTable('Bookings', 'Administrator');
//        if ($id > 0) {
//            $this->table->load($id);
//        }
        $this->toolsHelper = new ToolsHelper;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancelBooking() {

    }

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

// table ra_ event_types - replaces ra_event_type
        $details = '(
            `id` int(11) UNSIGNED  NOT NULL AUTO_INCREMENT,
            `description` varchar(20) NOT NULL,
            `ordering` INT NOT NULL DEFAULT 0,
            `state` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci;';
        $helper->checkTable('ra_event_types', $details, '');
        $sql = 'SELECT COUNT(id) FROM #__ra_event_types';
//        echo $sql;
        $count = $this->toolsHelper->getValue($sql);
        if ($count == 0) {
            $sql = 'INSERT INTO #__ra_event_types (id,description) VALUES(1,"Committee Meetings")';
            $this->toolsHelper->executeCommand($sql);
            $sql = 'INSERT INTO #__ra_event_types (id,description) VALUES(2,"Social Event")';
            $this->toolsHelper->executeCommand($sql);
            $sql = 'INSERT INTO #__ra_event_types (id,description) VALUES(3,"Training")';
            $this->toolsHelper->executeCommand($sql);
            $sql = 'INSERT INTO #__ra_event_types (id,description) VALUES(4,"Holiday/Weekend")';
            $this->toolsHelper->executeCommand($sql);
        }
        // new fields in ra_events
        $helper->checkColumn('ra_events', 'publication_date', 'A', 'DATE NOT NULL AFTER attachment_description; ');
        $helper->checkColumn('ra_events', 'shareable', 'A', 'INT DEFAULT "0" AFTER publication_date; ');
        $helper->checkColumn('ra_events', 'share_date', 'A', 'DATE NOT NULL AFTER shareable; ');
        $helper->checkColumn('ra_events', 'bookable', 'A', 'INT DEFAULT "0" AFTER share_date;');
        $helper->checkColumn('ra_events', 'max_bookings', 'A', 'INT DEFAULT "20" AFTER bookable; ');
        $helper->checkColumn('ra_events', 'notify_organiser', 'A', 'INT DEFAULT "0" AFTER max_bookings; ');
        $helper->checkColumn('ra_events', 'booking_info', 'A', 'TEXT NULL AFTER notify_organiser; ');

        $helper->checkColumn('ra_events', 'num_places', 'D', '');
        $helper->checkColumn('ra_events', 'partner', 'D', '');
        $helper->checkColumn('ra_bookings', 'num_places', 'A', 'INT NOT NULL DEFAULT "1" AFTER user_id; ');
        $helper->checkColumn('ra_bookings', 'partner', 'A', 'VARCHAR(50) NULL AFTER num_places; ');

        $sql = 'UPDATE #__ra_events SET bookable=0 WHERE bookable IS NULL';
        $this->toolsHelper->executeCommand($sql);
        $sql = 'UPDATE #__ra_events SET max_bookings=20 WHERE bookable=1 AND max_bookings IS NULL';
        $this->toolsHelper->executeCommand($sql);
        $sql = 'UPDATE #__ra_events SET notify_organiser=0 WHERE notify_organiser IS NULL';
        $this->toolsHelper->executeCommand($sql);
        $sql = 'UPDATE #__ra_bookings SET num_places=1 WHERE num_places IS NULL';
        $this->toolsHelper->executeCommand($sql);

        $sql = 'UPDATE #__ra_events SET publication_date=created WHERE publication_date IS NULL';
        $this->toolsHelper->executeCommand($sql);
        $sql = 'UPDATE #__ra_events SET publication_date=created WHERE publication_date = event_date';
        $this->toolsHelper->executeCommand($sql);

        // table ra_api_sites
        $details = '(`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `url` VARCHAR(100) NOT NULL ,
                `token` VARCHAR(255) NOT NULL ,
                `colour` VARCHAR(25) NOT NULL ,
                `state` TINYINT(1) NULL  DEFAULT 1,
                `ordering` INT NULL DEFAULT 0,
                `checked_out` INT(11) UNSIGNED,
                `checked_out_time` DATETIME NULL DEFAULT NULL ,
                `created` DATETIME NULL DEFAULT NULL ,
                `created_by` INT(11)  NULL DEFAULT 0,
                `modified` DATETIME NULL  DEFAULT NULL ,
                `modified_by` INT(11) NULL  DEFAULT 0,
                PRIMARY KEY (`id`)
                ) DEFAULT COLLATE=utf8mb4_unicode_ci; ';
        $helper->checkTable('ra_api_sites', $details);
        $helper->checkColumn('ra_api_sites', 'colour', 'U', 'VARCHAR(25) NOT NULL; ');
        $helper->checkColumn('ra_api_sites', 'sub_system', 'U', 'VARCHAR(25) NOT NULL DEFAULT "RA Events"; ');
        echo 'checking new fields<br>';
        $helper->checkColumn('ra_events', 'max_bookings', 'A', 'INT DEFAULT "20" AFTER bookable; ');
        $helper->checkColumn('ra_events', 'api_site_id', 'A', 'INT NULL AFTER booking_info; ');
        $helper->checkColumn('ra_events', 'original_id', 'A', 'INT NULL AFTER api_site_id; ');
        $helper->checkColumn('ra_events', 'num_bookings', 'A', 'INT DEFAULT "0" AFTER max_bookings; ');
        $sql = 'UPDATE #__ra_api_sites SET api_site_id = NULL WHERE api_site_id=0';
        $this->toolsHelper->executeCommand($sql);
    }

    public function createBooking() {
        // redirect to display form
        $target = 'index.php?option=com_ra_events&view=event&id=' . $event_id . '&Itemid=' . $menu_id;
        $this->redirect($target);
    }

    public function getDbVersion($component = 'com_ra_events') {
        $sql = 'SELECT s.version_id ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        return $this->toolsHelper->getValue($sql);
    }

    public function getVersion($component = 'com_ra_events') {
        // This retuns the version as display by System / Manage extensions
        $sql = 'SELECT manifest_cache ';
        $sql .= 'FROM  #__extensions  ';
        $sql .= 'WHERE element="' . $component . '"';
        $data = json_decode($this->toolsHelper->getValue($sql));
        return $data->version;
    }

    private function lookupUsername($id) {
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . $id;
        //       echo "$sql<br>";
        return $this->toolsHelper->getValue($sql);
    }

    public function notifyOrganiser($event_id, $user_id) {

        // Send a message to the event organiser
        // get name of booker
        $new = $this->lookupUsername($user_id);

        $sql = 'SELECT b.title, b.created, u.email ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_users AS u ON u.id=b.organiser_id ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $item = $this->toolsHelper->getItem($sql);

        $title = 'New booking for ' . $item->title;
        $body = $new . ' made a booking at ' . HTML('date', $item->created, 'hH:ii') . ' on ' . HTML('date', $item->created, 'dd M yy') . '<br>';
        $body .= 'The list of bookings is now:<br>';

        $sql = 'SELECT p.preferred_name, s.description ';
        $sql .= 'FROM #__ra_profiles AS p ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.user_id=p.id ';
        $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' ORDER BY s.seq, p.preferred__name';
        $rows = $this->toolsHelper->getRows($sql);
        $provisional = 0;
        foreach ($rows as $row) {
            if ($row->state == 0) {
                $provisional++;
            }
            $body .= $row->preferred_name . ', ' . $row->description . '<br>';
        }
        if ($provisional > 0) {
            $body .= 'Logon to confirm ' . $provisional . ' bookings<br>';
        }

        // send the email
        $this->toolsHelper->sendEmail($title, $body, $item->email);
    }

    public function showBookings() {
        // Only available to the event organiser
        // invoked from tmpl/event/book
        $event_id = $this->app->input->getInt('event_id', '0');

        if ($current->user->id !== $this->item->contact_id) {
            throw new \Exception('This function only available to the event organiser', 403);
        }
        $table = new TableHelper;
        $table->addHeader('Name,Status,Created');

        $sql = 'SELECT p.preferred_name, s.description, b.state, b.created ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id  ';
        $sql .= 'INNER JOIN #__ra_states AS s ON s.id = b.state  ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' ORDER BY s.seq, p.preferred_name';

        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $table->addItem($row->preferred_name);
            $table->addItem($row->description);
            $table->addItem(HTML('date', $row->created, 'dd/M/yy'));
            $table->addLine();
        }
        $table . showTable();

        $back = 'index.php&option=com_ra_events&view=event&id=' .
                $this->item->id;

        echo $this->toolsHelper->backButton($back);
    }

    public function red($text) {
        echo '<p><span style="color: #ff0000;"><strong>';
        echo $text;
        echo '</strong></span></p>';
    }

    public function test() {
        echo $this->red('Hello');
        if (ComponentHelper::isEnabled('com_ra_events', true)) {
            $this->current_version = $this->getVersion();
            echo 'com_ra_events already present, version=' . $this->getVersion();
            echo ', DB version=' . $this->getDbVersion() . '<br>';
        }
        if (!ComponentHelper::isEnabled('com_ra_tools', true)) {
            echo 'Can only be installed if com_ra_tools is already present';
            return false;
        }

        $tools_required = '3.3.0';
        $tools_version = $this->getVersion('com_ra_tools');
        echo '<p>Version ' . $tools_required . ' of com_ra_tools required<br>';
        if (version_compare($tools_version, $tools_required, 'ge')) {
            echo 'Version ' . $tools_version . ' of com_ra_tools found</p>';
        } else {
            echo 'Version ' . $tools_version . ' of com_ra_tools found</p>';
            echo '<p>WARNING: Requires version of com_ra_tools >=' . $tools_required . '</p>';
            return false;
        }
        return;
        //       echo 'test<br>';
        //       return;
        //       Created2025-03-05 17:20:51
        $sql = 'INSERT INTO #__ra_bookings (created_by, created,event_id,user_id) values ';
        $sql .= '(1,"2025-03-05 17:20:51",4,1)';
        echo $sql;
//        return;
        $this->toolsHelper->executeCommand($sql);
// index.php?option=com_ra_events&task=booking.createBooking&user_id=994&event_id=4
        $id = 1;
//        $this->table = Factory::getTable('#__ra_bookings', 'Administrator');
        if ($id > 0) {
            $this->table->load($id);
            echo 'id ' . $this->id . '<br>';
            echo 'Created ' . $this->table->created . '<br>';
            echo 'Created by ' . $this->table->created_by . '<br>';

            echo 'Event_id ' . $this->table->event_id . '<br>';
            echo 'User_id ' . $this->table->user_id . '<br>';
            echo 'State ' . $this->table->state . '<br>';
        }
        $this->confirmBooking();
//$this->cancel();
//$this->showBookings(1,1,true);
    }

    // localhost/administrator/index.php?option=com_ra_events&task=system.checkUserLinks&id=979
    // Alie Hagendoorn
    private function checkUserlinks($id) {
        // Checks that records exist in
        //  Links User to given group
        $db = Factory::getDbo();
        $helper = New ToolsHelper;

        for ($i = 1; $i < 3; $i++) {
            $sql = 'SELECT COUNT(user_id) FROM #__user_usergroup_map WHERE user_id=' . $id . ' AND group_id=' . $i;
            $record_count = $helper->getValue($sql);
            if ($record_count == 0) {
                $query = $db->getQuery(true);
                $query
                        ->insert($db->quoteName('#__user_usergroup_map'))
                        ->set('user_id =' . $db->quote($id))
                        ->set('group_id=' . $db->quote($i));
                $db->setQuery($query);
                $return = $db->execute();

                if ($return == false) {
                    $this->error = 'Unable to link ' . $this->user_id . ' to ' . $group_id;
                    Factory::getApplication()->enqueueMessage('Unable to link user ' . $group_id, 'Warning');
                }
            }
        }
        return $return;
    }

    public function testUserlinks() {
        $id = $this->app->input->getInt('id', '0');
        $this->checkUserlinks($id);
    }

}
