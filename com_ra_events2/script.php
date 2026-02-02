<?php

/*
 * Installation script
 * 16/09/23 CB Created from mailman script
 * 14/11/23 CB check tables for booking / Invoicing
 * 18/11/23 CB create getValue
 * 20/22/23 CB additional fields for Bookings
 * 16/01/25 CB delete messages from update, seek /images/com_ra_events in JPATH_ROOT
 * 07/04/25 CB delete files
 * 10/04/25 CB link to Dashboard
 * 23/04/25 Comment out all active code
 * 19/06/25 CB include DatabaseInterface
 * 30/06/25 CB tools 3.2.4 as prerequisite
 * 08/07/25 CB tools 3.4.0 as prerequisite
 * N.B. delete getDatabaseVersion and getExtensionId
 * 09/0725 CB api_sites / sub_system
 * 22/07/25 CB emails/ref to INT
 * 31/07/25 CB this->version_required
 * 03/08/25 CB delete forms social.xml etc
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

class Com_Ra_eventsInstallerScript {

    private $component;
    private $current_version;
    private $minimumJoomlaVersion = '4.0';
    private $minimumPHPVersion = JOOMLA_MINIMUM_PHP;
    private $reconfigure_message;
    private $required_version;

    function buildButton($url, $text, $newWindow = 0, $colour = '') {
        if ($colour == '') {
            $colour = 'sunrise';
        }
        $class = 'link-button ' . $colour;
        //       echo "colour=$colour, code=$code, class=$class<br>";
        $q = chr(34);
        $out = "<a class=" . $q . $class . $q;
        $out .= " href=" . $q . $url . $q;
        $out .= " target =" . $q . "_self" . $q;
        $out .= ">";
        $out .= $text;
        $out .= "</a>";
        return $out;
    }

    function checkColumn($table, $column, $mode, $details = '') {
//  $mode = A: add the field
//  $mode = U: update the field (keeping name the same)
//  $mode = D: delete the field

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
        echo 'mode=' . $mode . ': Seeking ' . $table_name . '/' . $column . ', count=' . $count . "<br>";
        if (($mode == 'A') AND ($count == 1)
                OR ($mode == 'D') AND ($count == 0)) {
            return true;
        }
        if (($mode == 'U') AND ($count == 0)) {
            echo 'Field ' . $column . ' not found in ' . $table_name . '<br>';
            return false;
        }

        $sql = 'ALTER TABLE ' . $table_name . ' ';
        if ($mode == 'A') {
            $sql .= 'ADD ' . $column . ' ';
            $sql .= $details;
        } elseif ($mode == 'D') {
            $sql .= 'DROP ' . $column;
        } elseif ($mode == 'U') {
            $sql .= 'CHANGE ' . $column . ' ' . $column . ' ';
            $sql .= $details;
        }
        echo "$sql<br>";
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Success';
        } else {
            echo 'Failure';
        }
        echo ' for ' . $table_name . '<br>';
        return $count;
    }

    private function checkColumnExists($table, $column) {
        $config = JFactory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";
//    echo "$sql<br>";

        return $this->getValue($sql);
    }

    function checkTable($table, $details, $details2 = '') {

        $config = JFactory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $table_name . "' ";
//        echo "$sql<br>";

        $count = $this->getValue($sql);
        echo 'Seeking ' . $table_name . ', count=' . $count . "<br>";
        if ($count > 0) {
            return $count;
        }
        $sql = 'CREATE TABLE ' . $table_name . ' ' . $details;
        echo "$sql<br>";
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Table created OK<br>';
        } else {
            echo 'Failure<br>';
            return false;
        }
        if ($details2 != '') {
            $sql = 'ALTER TABLE ' . $table_name . ' ' . $details2;
            $response = $this->executeCommand($sql);
            if ($response) {
                echo 'Table altered OK<br>';
            } else {
                echo 'Failure<br>';
                return false;
            }
        }
    }

    private function createTables() {
// table ra_ event_states

        $details = '(`id` int NOT NULL ,
            `seq` INT NOT NULL,
            `title` varchar(20) NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci; ';
        $this->checkTable('ra_event_states', $details);

        $sql = 'SELECT COUNT(id) FROM #__ra_event_states';
        $count = $this->getValue($sql);
        if ($count == 0) {
            $sql = "INSERT INTO #__ra_event_states (seq,id,title) VALUES(1,0,'Provisional')";
            $this->toolsHelper->executeCommand($sql);
            $sql = "INSERT INTO #__ra_event_states (seq,id,title) VALUES(2,1,'Confirmed')";
            $this->toolsHelper->executeCommand($sql);
            $sql = "INSERT INTO #__ra_event_states (seq,id,title) VALUES(3,-2, 'Cancelled')";
            $this->toolsHelper->executeCommand($sql);
        }

// ra_event_types
        $details = '(
            `id` int(11) UNSIGNED  NOT NULL AUTO_INCREMENT,
            `description` varchar(20) NOT NULL,
            `ordering` INT NOT NULL DEFAULT 0,
            `state` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci;';
        $this->checkTable('ra_event_types', $details);

        $sql = 'SELECT COUNT(id) FROM #__ra_event_types';
        echo $sql;
        $count = $this->getValue($sql);
        echo 'Number of records ' . $count . '<br>';
        return;
        if ($count == 0) {
            $sql = 'INSERT INTO #__ra_event_types (description) VALUES("Committee Meetings")';
            $this->toolsHelper->executeCommand($sql);
            $sql = 'INSERT INTO #__ra_event_types (description) VALUES("Social Event")';
            $this->toolsHelper->executeCommand($sql);
            $sql = 'INSERT INTO #__ra_event_types (description) VALUES("Training")';
            $this->toolsHelper->executeCommand($sql);
            $sql = 'INSERT INTO #__ra_event_types (description) VALUES("Holiday/Weekend")';
            $this->executeCommand($sql);
        }
    }

    private function deleteFile($target) {
// Not needed, could use a built in function (if details were known!)
        if (file_exists(JPATH_SITE . $target)) {
            File::delete(JPATH_SITE . $target);
            echo "$target deleted<br>";
        }
    }

    private function deleteFolder($target) {
// 08/10/24 CB does not seem to work!
        if (file_exists(JPATH_SITE . $target)) {
            Folder::delete(JPATH_SITE . $target);
            echo JPATH_SITE . "$target deleted<br>";
        } else {
            echo "$target not found<br>";
        }
    }

    private function executeCommand($sql) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->execute();
    }

    public function getDatabaseVersion($component = 'com_ra_events') {
// Get the extension ID
        $db = JFactory::getDbo();
        $eid = $this->getExtensionId($component);
////
        if ($eid != null) {
// Get the schema version
            $query = $db->getQuery(true);
            $query->select('manifest_cache')
                    ->from('#__extensions')
                    ->where('extension_id = ' . $db->quote($eid));
            $db->setQuery($query);
            $json = $db->loadResult();
////
            $values = json_decode($json->manifest_cache);
            return $version;
        }
////
        return null;
    }

    public function getDbVersion($component = 'com_ra_events') {
        $sql = 'SELECT s.version_id ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        return $this->getValue($sql);
    }

    /**
     * Loads the ID of the extension from the database
     *
     * @return mixed
     */
    public function getExtensionId($component = 'com_ra_events') {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true);
        $query->select('extension_id')
                ->from('#__extensions')
                ->where($db->qn('element') . ' = ' . $db->q($component) . ' AND type=' . $db->q('component'));
        $db->setQuery($query);
        $eid = $db->loadResult();
//        echo $db->replacePrefix($query) . '<br>';
        return $eid;
    }

    private function getValue($sql) {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->loadResult();
    }

    public function getVersion($component = 'com_ra_events') {
        // This retuns the version as display by System / Manage extensions
        $sql = 'SELECT manifest_cache ';
        $sql .= 'FROM  #__extensions  ';
        $sql .= 'WHERE element="' . $component . '"';
        $data = json_decode($this->getValue($sql));
        return $data->version;
    }

    /**
     *     returns details of the component version and the database version
     *
     * @return  CMSObject
     *
     */
    public function getVersions($component = 'com_ra_events') {
        $sql = 'SELECT e.manifest_cache, s.version_id AS db_version ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        $db->execute();
        $item = $db->loadObject();
        if ($item == false) {
            echo 'Can\'t find versions for ' . $component . '<br>';
            return false;
        }
        $values = json_decode($item->manifest_cache);

        $versions = new CMSObject;
        $versions->component = $values->version;
        $versions->db_version = $item->db_version;
        return $versions;
    }

    public function install($parent): bool {
        echo '<p>Installing RA Events (com_ra_events) ' . '</p>';
        if (ComponentHelper::isEnabled('com_ra_events', true)) {
            $this->original_version = $this->getVersion();
            echo '<p>com_ra_events found, version ' . $this->original_version;
            echo ', database version ' . $this->getDbVersion() . '</p>';
        }



        return true; ///////////////////////////////////////////////////////////


        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            $tools_required = '3.0.4';
            echo '<p>Version ' . $tools_required . ' of com_ra_tools required<br>';
            if (version_compare($tools_version, $tools_required, 'ge')) {
                echo '<p>Version ' . $tools_version . ' of com_ra_tools found</p>';
            } else {
                echo 'Version ' . $tools_version . ' of com_ra_tools found</p>';
                echo '<p>ERROR: Please install version of com_ra_tools >=' . $tools_required . '</p>';
                return false;
            }
        } else {
            echo 'This component cannot be installed unless component RA Tools (com_ra_tools) is installed first';
            return false;
        }
        return true;
    }

    public function red($text) {
        echo '<p><span style="color: #ff0000;"><strong>';
        echo $text;
        echo '</strong></span></p>';
    }

    public function uninstall($parent): bool {
        echo '<p>Uninstalling RA Events (com_ra_events) version=' . $this->current_version . '<br>';
        return true;
    }

    public function update($parent): bool {
        echo '<p>Updating RA Events (com_ra_events)</p>';
// Jump directly to the dashboard
//        $parent->getParent()->setRedirectURL('index.php?option=com_ra_tools&view=dashboard');
        return true;
    }

    public function postflight($type, $parent) {
        echo '<p>Postflight RA Events (com_ra_events)</p>';

        if ($type == 'uninstall') {
            return true;
        }

        echo '<p>com_ra_events is now at ' . $this->getVersion() . '</p>';

        if ($reconfigure_message == true) {
            $this->red('Please review and update the configuration settings for com_ra_events.');
        }

        echo '<b>Useful links</b><br>';
        echo $this->buildButton('index.php?option=com_ra_tools&view=dashboard', 'Dashboard', 'granite') . '<br>';
        echo $this->buildButton('index.php?option=com_config&view=component&component=com_ra_events', 'Configure');
        return true;
        /*
          // new fields in ra_events
          $this->checkColumn('ra_events ', 'publication_date ', 'A ', 'DATE NOT NULL AFTER attachment_description; ');
          $this->checkColumn('ra_events ', 'shareable ', 'A ', 'INT DEFAULT "0" AFTER publication_date; ');
          $this->checkColumn('ra_events ', 'share_date ', 'A ', 'DATE NOT NULL AFTER shareable; ');
          $this->checkColumn('ra_events ', 'bookable ', 'A ', 'INT DEFAULT "0" AFTER share_date;');
          $this->checkColumn('ra_events ', 'max_bookings ', 'A ', 'INT DEFAULT "20" AFTER bookable; ');
          $this->checkColumn('ra_events ', 'notify_organiser ', 'A ', 'INT DEFAULT "0" AFTER max_bookings; ');
          $this->checkColumn('ra_events ', 'booking_info ', 'A ', 'TEXT NULL AFTER notify_organiser; ');
          // new fields in ra_bookings
          $this->checkColumn('ra_bookings ', 'num_places ', 'A ', 'INT NOT NULL DEFAULT "1" AFTER user_id; ');
          $this->checkColumn('ra_bookings ', 'partner ', 'A ', 'VARCHAR(50) NULL AFTER num_places; ');

          $this->createTables();


          $sql = 'UPDATE #__ra_events SET bookable=0 WHERE bookable IS NULL';
          $this->executeCommand($sql);
          $sql = 'UPDATE #__ra_events SET max_bookings=20 WHERE bookable=1 AND max_bookings IS NULL';
          $this->executeCommand($sql);
          $sql = 'UPDATE #__ra_events SET notify_organiser=0 WHERE notify_organiser IS NULL';
          $this->executeCommand($sql);
          $sql = 'UPDATE #__ra_bookings SET num_places=1 WHERE num_places IS NULL';
          $this->executeCommand($sql);
          $sql = 'UPDATE #__ra_events SET publication_date=created WHERE publication_date IS NULL';
          $this->executeCommand($sql);
          $sql = 'UPDATE #__ra_events SET publication_date=created WHERE publication_date = event_date';
          $this->executeCommand($sql);
          $sql = 'UPDATE #__ra_events SET share_date=created WHERE share_date IS NULL';
          $this->executeCommand($sql);
          $sql = 'UPDATE #__ra_events SET share_date=created WHERE share_date = event_date';
          $this->executeCommand($sql);


          // Ensure a folder exists for Attachments
          $target = JPATH_ROOT . '/images/com_ra_events';
          //        mkdir($target, 0777);
          if (!file_exists($target)) {
          echo 'WARNING: please create ' . $target . ' for storing attachments<br>';
          }
          if ($type == 'install') {
          // You can have the backend jump directly to the newly updated component configuration page
          $parent->getParent()->setRedirectURL('index.php?option = com_ra_events');
          return true;
          }
         */
    }

    public function preflight($type, $parent): bool {
        echo '<p>Preflight RA Events (type=' . $type . ')</p>';
        if ($type == 'uninstall') {
            return true;
        }

        if (!empty($this->minimumPHPVersion) && version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            Log::add(
                    Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion),
                    Log::WARNING,
                    'jerror'
            );
            return false;
        }
        if (!empty($this->minimumJoomlaVersion) && version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            Log::add(
                    Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                    Log::WARNING,
                    'jerror'
            );
            return false;
        }
        if ($type == 'install') {
            return true;
        }

        if (ComponentHelper::isEnabled('com_ra_events', true)) {
            $this->current_version = $this->getVersion();
            echo 'com_ra_events already present, version=' . $this->getVersion();
            echo ', DB version=' . $this->getDbVersion() . '<br>';
        }
        if (!ComponentHelper::isEnabled('com_ra_tools', true)) {
            echo 'Can only be installed if com_ra_tools is already present';
            return false;
        }

        $tools_required = '3.3.5';
        $tools_version = $this->getVersion('com_ra_tools');
        echo $this->red('<p>Version ' . $tools_required . ' of com_ra_tools required');
        if (version_compare($tools_version, $tools_required, 'ge')) {
            echo 'Version ' . $tools_version . ' of com_ra_tools found</p>';
        } else {
            echo 'Version ' . $tools_version . ' of com_ra_tools found</p>';
            echo $this->red('<p>WARNING: Requires version of com_ra_tools >=' . $tools_required);
// If we return false, no message is displayed on the console, just "Custom installation failure"
//           return false;
        }
        $this->version_required = '2.1.7';

        $this->deleteFile('components/com_ra_events/administrator/forms/committee.xml');
        $this->deleteFile('components/com_ra_events/administrator/forms/new.xml');
        $this->deleteFile('components/com_ra_events/administrator/forms/social.xml');
        $this->deleteFile('components/com_ra_events/administrator/forms/training.xml');
        if (version_compare($this->current_version, $this->version_required, 'ge')) {
            echo 'Current version is ' . $this->current_version . ', no additional processing required</p>';
            return true;
        } else {
            echo '<p>Version is currently ' . $this->current_version . ', ';
            echo 'Requires version >= ' . $this->version_required . '</p>';
        }

        if (version_compare($this->current_version, '2.1.7', 'le')) {
            echo 'Upgrading for 2.1.7<br>';
            $this->checkColumn('ra_emails', 'ref', 'U', 'INT DEFAULT "0"; ');
            $this->deleteFolder('/components/com_ra_events/scr/View/Myevents');
            $this->deleteFolder('/components/com_ra_events/tmpl/myevents');
        }
        if (version_compare($this->current_version, '2.1.6', 'le')) {
            echo 'Upgrading for 2.1.6<br>';
            $this->checkColumn('ra_api_sites', 'sub_system', 'A', 'VARCHAR(10) NOT NULL AFTER id; ');

            $this->deleteFile('/components/com_ra_events/src/tmpl/bookingform/default.xml');
            $this->deleteFolder('/components/com_ra_events/tmpl/bookings');
            $this->deleteFolder('/components/com_ra_events/tmpl/profile/');

            $this->deleteFile('/components/com_ra_events/src/Controller/MybookingsController');
            $this->deleteFile('/components/com_ra_events/src/Model/MybookingsModel');
            $this->deleteFolder('/components/com_ra_events/scr/View/Mybookings');
            $this->deleteFolder('/components/com_ra_events/tmpl/mybookings/');
            // 20/07/25
            $this->deleteFile('/components/com_ra_events/src/Controller/BookingformController');
            $this->deleteFile('/components/com_ra_events/src/Model/BookingformModel');
            $this->deleteFolder('/components/com_ra_events/scr/View/Bookingform');
            $this->deleteFolder('/components/com_ra_events/tmpl/bookingform/');
            // ALTER TABLE `dev_ra_events` ADD INDEX `idx_api_site_id` (`api_site_id`);
            //           return true; ///////////////////////////////////////////////////////////


            $target = JPATH_SITE . '/components/com_ra_events/src/View/Myevents';
            if (file_exists($target)) {
                echo "$target found<br>";
                //            File::delete($target);
            } else {
                echo "$target NOT found<br>";
            }
            $target = JPATH_SITE . '/components/com_ra_events/tmpl/myevents';
            if (file_exists($target)) {
                echo "$target found<br>";
//                File::delete($target);
            } else {
                echo "$target NOT found<br>";
            }
        }
        if (version_compare($this->current_version, '2.1.2', 'le')) {
            echo 'Upgrading for 2.1.2<br>';
            $this->checkColumn('ra_events', 'max_bookings', 'A', 'INT DEFAULT "20" AFTER bookable; ');
            $this->checkColumn('ra_events', 'api_site_id', 'A', 'INT NULL AFTER booking_info; ');
            $this->checkColumn('ra_events', 'original_id', 'A', 'INT NULL AFTER api_site_id; ');
            $this->checkColumn('ra_events', 'num_bookings', 'A', 'INT DEFAULT "0" AFTER original_id; ');
//      $this->checkIndex('ra_events', 'api_site_id');
            $details = '`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `sub_system` VARCHAR(10) NOT NULL ,
                `url` VARCHAR(100) NOT NULL ,
                `token` VARCHAR(255) NOT NULL ,
                `colour` VARCHAR(22) NOT NULL ,
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
            $this->checkTable('ra_api_sites', $details);
        }
        return true;
    }

}
