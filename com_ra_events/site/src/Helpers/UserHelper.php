<?php

/**
 * @version     4.0.11
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie Bigley <webmaster@bigley.me.uk> - https://www.developer-url.com
 * Invoked from controllers/dataload to import Users, will be passed 3 parameters:
 *  event_id, processing and filename
 * Processing: 0 = report only
 *             1 = Update database
 *
 * 25/03/25 CB Created from com ra_mailman
 */

namespace Ramblers\Component\Ra_events\Site\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use \Joomla\CMS\Factory\MVCFactory;
use Joomla\CMS\Table\Table;
use \Joomla\CMS\User\User;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Helper class to create Joomla Users and profiles
 */
class UserHelper {

    // These five variables are defined by the calling program
    public $component;
    public $group_code;
    public $event_id;
    public $processing;
    public $filename;
    // These are available after processing
    public $error;
    // These variables are used internally
    public $email;
    public $name;
    public $partner;
    public $user_id;
    protected $open;
    protected $toolsHelper;
    protected $error_count;
    protected $record_count;
    protected $users_created;
    protected $users_required;

    public function __construct() {
        $this->record_count = 0;
        $this->users_created = 0;
        $this->toolsHelper = new ToolsHelper;
    }

    public function checkEmail($email, $username, $group_code) {
        // Returns True or an error message
        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT u.id, u.name, u.registerDate, p.home_group ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = u.id ';
        $sql .= 'WHERE u.email="' . $email . '"';
        $item = $toolsHelper->getItem($sql);
        if (!is_null($item)) {
            if ($item->id > 0) {
                return 'This email is already in use for ' . $item->name . '/' . $item->home_group . ' registered ' . $item->registerDate;
            }
        }

        $sql = 'SELECT u.id, u.name, u.registerDate, p.home_group ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = u.id ';
        $sql .= 'WHERE u.name="' . $username . '" ';
        $sql .= 'AND p.home_group="' . $group_code . '" ';
//        echo $sql . '<br>';
//        die($sql);
        $item = $toolsHelper->getItem($sql);
        if (!is_null($item)) {
            if ($item->id > 0) {
                return 'This Name is already in use for ' . $item->email . '/' . $item->home_group . ' registered ' . $item->registerDate;
            }
        }
        return True;
    }

    public function checkExistingUser($email) {
        // Returns details of existing user and profile, if one found
        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT u.id, u.email, p.id as profile_id, p.preferred_name, p.home_group ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = u.id ';
        $sql .= 'WHERE u.email="' . $email . '"';
//        echo $sql;
        return $toolsHelper->getItem($sql);
    }

    public function createProfile() {
        //    Create a record in ra_profiles from internal variables
        $db = Factory::getDbo();
        $user = Factory::getApplication()->getIdentity();
        $query = $db->getQuery(true);
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $query->insert($db->quoteName('#__ra_profiles'))
                ->set('id =' . $db->quote($this->user_id))
                ->set('home_group =' . $db->quote($this->group_code))
                ->set('groups_to_follow  =' . $db->quote($this->group_code))
                ->set('preferred_name =' . $db->quote($this->name))
                ->set('created =' . $db->quote($date))
                ->set('created_by =' . $db->quote($user->id))
        ;
        $db->setQuery($query);
        return $db->execute();
    }

    public function createProfile_1($user_id, $group_code) {
//    Create a record in ra_profiles
        $user = Factory::getApplication()->getIdentity();
        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        // Prepare the insert query.
        $query->set('id =' . $db->quote($user_id))
                ->set('home_group =' . $db->quote($group_code))
                ->set('groups_to_follow  =' . $db->quote($group_code))
                ->set('preferred_name =' . $db->quote($this->name))
        ;
//      Check that record not already present
//      should not be an existing records, but if there is, update it anyway
        $sql = 'SELECT id FROM #__ra_profiles WHERE id=' . $user_id;
        echo $sql;
        $record_exists = $this->toolsHelper->getValue($sql);
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        if ($record_exists > 0) {
            echo 'Yes<br>';
            echo $query->toSql();
            $query->set('modified =' . $db->quote($date))
                    ->set('modified_by =' . $db->quote($user->id))
                    ->update($db->quoteName('#__ra_profiles'));
        } else {
            echo 'No<br>';
            echo 'query=' . (string) $query . '<br>';
            $query->set('created =' . $db->quote($date))
                    ->set('created_by =' . $db->quote($user->id))
                    ->insert($db->quoteName('#__ra_profiles'));
        }
        // $this->error = 'Unable to create User record for ' . $this->group_code . ' ' . $this->name;
    }

    public function createProfile_2($user_id, $group_code) {
        // Fails to find Instance of table
        $data = array(
            'id' => $user_id,
            'home_group' => $db->quote($group_code),
            'groups_to_follow' => $db->quote($group_code),
            'preferred_name' => $db->quote($this->name),
        );
        $table = Table::getInstance('Profile', 'Table');
        if (!$table->bind($data)) {
            $app->enqueueMessage('could not bind', 'error');
            return false;
        }
        if (!$table->check()) {
            $app->enqueueMessage('could not validate', 'error');
            return false;
        }
        if (!$table->store(true)) {
            $app->enqueueMessage('could not store', 'error');
            return false;
        }
    }

    public function createUser() {
        /*
         * This uses Joomla objects to create a User record (and send them a message about the new password)
         * MAILMAN
         * It is used from the front-end (controllers/profile) and from the backend (mailman User / New
         * However, if used from the back end it seems only to work the first time it is invoked
         * PATHS
         * It is used from the front-end for self registration and admin registration
         *          * 23/10/23 add field sendEmail, pass array of groups rather than call linkUser
         */

        if ($this->name == 'Email Address') {
            // this is the first line of a MailChimp export
            return;
        }
        $this->user_id = 0;

        $password = '$2y$10$PCUXW4xpLTsLGmdJJ4NqUuuNSnpq7fBkZxB4XiqUNFq8tP1Ha3FHa'; // unspecifiedpassword
        // This code only seems to work for the first user
        $user = new User();   // Write to database
        $data = array(
            "name" => $this->name,
            "username" => $this->email,
            "password" => $password,
            "password2" => $password,
            "sendEmail" => '1',
            "group" => array('1', '2'), // Public & Registered
            //          "require_reset" =>1,
            "email" => $this->email
        );
        if (!$user->bind($data)) {
            $this->error = 'Could not validate data - Error: ' . $user->getError();
            return false;
        }

        if (!$user->save()) {
            // throw new Exception("Could not save user. Error: " . $user->getError());
            $this->error = 'Could not create user - Error: ' . $user->getError();
            return false;
        }
        $this->user_id = $user->id;
//        $this->linkUser();
        Factory::getSession()->clear('user', "default");
        return true;
    }

    public function createUserDirect($block = 1) {
        // writes a record to the users table
        if ($this->name == 'Email Address') {
// this is the first line of a MailChimp export
            return;
        }
        $this->user_id = 0;

        $date = Factory::getDate();
        $params = '{"admin_style":"","admin_language":"","language":"","editor":"","timezone":""}';
        $password = '$2y$10$PCUXW4xpLTsLGmdJJ4NqUuuNSnpq7fBkZxB4XiqUNFq8tP1Ha3FHa'; // unspecifiedpassword
        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        // Prepare the insert query.
        $query
                ->insert($db->quoteName('#__users'))
                ->set('name =' . $db->quote($this->name))
                ->set('username =' . $db->quote($this->email))
                ->set('email =' . $db->quote($this->email))
                ->set('password =' . $db->quote($password))
                ->set('registerDate =' . $db->quote($date->toSQL()))
                ->set("activation =''")
                ->set("block =" . $block)
                ->set('params =' . $db->quote($params))
                ->set("otpKey =''")
                ->set("otep =''")
        //                ->set('requireReset=' . $db->quote($requireReset))
        ;
//        echo $query . '<br>';
        //      Set the query using our newly populated query object and execute it.
        $db->setQuery($query);
        $db->execute();
        // $db_insertid can be flakey
//        $this->user_id = $db->insertid();
// Factory::getApplication()->enqueueMessage('Unable to create User record for ' . $this->group_code . ' ' . $this->name, 'Error');
        if ($this->lookupUser()) {
            Factory::getApplication()->enqueueMessage('Created user record for ' . $this->group_code . ' ' . $this->name, 'Info');

            $this->linkUser(1);  // Public
            $this->linkUser(2);  // Registered
            if ($this->component == 'com_ra_paths') {
                // link to group com_ra_paths_user
                $sql = 'SELECT id FROM #__usergroups WHERE title="com_ra_paths_user"';
                $group_id = (int) $this->toolsHelper->getValue($sql);
                if ($group_id == 0) {
                    $this->error = 'Unable to link ' . $this->group_code . ' ' . $this->name . ' to com_ra_paths_user';
                    return false;
                }
                $this->linkUser($group_id);
            }
            $this->sendEmail();
            $this->createProfile();
            return true;
        }
        $this->error = 'Unable to create User record for ' . $this->group_code . ' ' . $this->name;
        return false;
    }

    protected function addJoomlaUser() {
        $password = self::randomkey(8);
        $data = array(
            "name" => $this->name,
            "username" => $this->email,
            "password" => $password,
            "password2" => $password,
            "email" => $this->email,
            "reset" => 1
        );

        // $user = clone(Factory::getUser());
        $user = new User();
        //Write to database
        if (!$user->bind($data)) {
            throw new Exception("Could not bind data. Error: " . $user->getError());
        }
        if (!$user->save()) {
            throw new Exception("Could not save user. Error: " . $user->getError());
        }

        return $user->id;
    }

    protected function book($list_id, $user_id) {
        $db = Factory::getDbo();
//        if ($this->partner == '') {
//            $count =1;
//        } else {
//            $count =2;
//        }
//        $sql = 'INSERT INTO #__ra_bookings (list_id,user_id,num_paces,partner) values(';
//        $sql .= '(' . $list_id . ',' . $user_id . ',' . $count . ',';
//         $sql .= '"' . $$db->quote($this->partner) . '")';
        $sql = 'INSERT INTO #__ra_bookings (list_id,user_id) values(';
        $sql .= '(' . $list_id . ',' . $user_id . ')';
        echo $sql . '<br>';
        $id = $this->toolsHelper->executeCommand($sql);
    }

    protected function isBooked($list_id, $user_id) {
        $sql = 'SELECT id FROM #__ra_bookings ';
        $sql .= 'WHERE list_id=' . $list_id;
        $sql .= ' AND user_id=' . $user_id;
        $id = $this->toolsHelper->getValue($sql);
        if (is_null($id)) {
            return false;
        } else {
            return true;
        }
    }

    protected function linkUser($group_id) {
        //  Links User to given group
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query
                ->insert($db->quoteName('#__user_usergroup_map'))
                ->set('user_id =' . $db->quote($this->user_id))
                ->set('group_id=' . $db->quote($group_id));
        $db->setQuery($query);
//        echo $query . '<br>';
        $return = $db->execute();

        if ($return == false) {
            $this->error = 'Unable to link ' . $this->user_id . ' to ' . $group_id;
            Factory::getApplication()->enqueueMessage('Unable to link user ' . $group_id, 'Warning');
        }
        return $return;
    }

    protected function lookupUser() {
        $this->user_id = 0;
        $sql = 'SELECT id FROM #__users WHERE email="' . $this->email . '"';
//        echo $sql . '<br>';
        $this->user_id = (int) $this->toolsHelper->getValue($sql);
        return $this->user_id;
//        $db = JFactory::getDbo();
//        $query = $db->getQuery(true);
//        $query->select('a.id');
//        $query->from('`#__users` AS a');
//        $query->where($db->qn('a.email') . ' = ' . $db->q($email));
//        $db->setQuery($query);
//        return $db->loadResult();
    }

    protected function parseLine($data) {
        /*
         * Sets up the intername field name, email etc
         * The format of the line depends on the type of data being loaded
         */

        if ($this->record_count == 1) {
            echo 'Ignoring header row<br>';
            return 0;
        } else {
            $return = 1;
            $this->group_code = $data[0];
            $this->name = $data[1];
            $this->email = $data[2];
            $this->partner = $data[3];
            if ($this->group_code == '') {
                $this->error_count++;
                echo '<b>First column (Group code) is blank' . "</b><br>";
                $return = 0;
            }

            if ($this->name == '') {
                $this->error_count++;
                $message = '<b>Second column (name) is blank</b>';
                $message .= ', email=' . $this->email;
                echo $message . "<br>";
                $return = 0;
            }

            if ($this->email == '') {
                $this->error_count++;
                echo '<b>Third column (email) is blank' . "</b><br>";
                $return = 0;
            }
            return $return;
        }
    }

    public function processFile() {
        if (JDEBUG) {
            $diagnostic = ' processing=' . $this->processing . ', filename=' . $this->filename;
            Factory::getApplication()->enqueueMessage("Helper: " . $diagnostic, 'Message');
        }
        if (!file_exists($this->filename)) {
            echo $this->filename . ' not found';
            Factory::getApplication()->enqueueMessage("Helper: " . $this->filename . ' not found', 'Error');
            return 0;
        }
        if (substr(JPATH_ROOT, 14, 6) == 'joomla') {
            echo '<h4>deleting test data</h4> ';
            $this->purgeTestData();
        }

        $sql = "Select group_code, title from `#__ra_events` "
                . "WHERE id='" . $this->event_id . "'";
        $item = $this->toolsHelper->getItem($sql);
        $this->group_code = $item->group_code;
        $title = $item->group_code . ' ' . $item->name;

        if ($this->processing == 1) {
            echo '<h2>Processing ';
        } else {
            echo '<h2>Validating ';
        }
        echo 'CSV';

        echo '<h4>List=' . $title . '<br>';
        echo 'File=' . $this->filename . '</h4>';
        $this->processRecords();
        echo '<br>' . $this->record_count . ' records read<br>';
        if ($this->error_count > 0) {
            echo "<b>$this->error_count errors</b><br>";
        }
        echo $this->users_required . ' Users required<br>';
        echo $this->users_created . ' Users created<br>';
    }

    protected function processRecords() {


//        $table = $this->factory->createTable('Profile', 'Administrator');
//        $table = Factory::createTable('Profile', 'Administrator'); FAILS
        $factory = new MVCFactory('\\Ramblers\\Component\\Ra_events');
        $table = $factory->createTable($name, $prefix, $config);
        $table->load(1);
        echo '1 ' . $table->home_group . '<br>';
        echo $table->preferred_name . '<br>';
//        echo $table->latitude . '<br>';
        return;
        $this->record_count = 0;
        $this->users_required = 0;
        $this->subscription_count = 0;
        $handle = fopen($this->filename, "r");
        if ($handle == 0) {
            echo 'Unable to open ' . $this->filename . '<br>';
            return 0;
        }
//        die('File ' . $this->filename . ' opened OK');
        $sql_lookup = 'SELECT id FROM #__users WHERE email="';
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $this->record_count++;
            if (JDEBUG) {
                echo $this->record_count . ': ';
            }
            if ($this->record_count == 1) {
                echo 'Ignoring header row<br>';
            } elseif (substr($data[0], 0, 1) == '#') {
                echo 'Ignoring comment ' . $data[0] . ',' . $data[1], '<br>';
            } else {
                /*
                 * After $this->parseLine, the following variables will have been set up:
                 *     $this->group_code
                 *     $this->name
                 *     $this->email
                 */
                if (($this->parseLine($data))) {
                    if (JDEBUG) {
                        echo 'group=' . $this->group_code . ', name=' . $this->name . ', email=' . $this->email . "<br>";
                    }
                    $booking_required = false;
                    $message = '';
                    $user_id = (int) $this->lookupUser();
                    if ($user_id == 0) {
                        $this->users_required++;
                        $message .= 'User ' . $this->name . ' <b>not present</b> (' . $this->email . ')';
                        if ($this->processing == 1) {
                            $response = $this->createUserDirect();
                            if ($response) {
                                $user_id = $this->user_id;
                                $message .= ', User created';
                                if (JDEBUG) {
                                    $message .= ', id=' . $user_id;
                                }
                                $this->users_created++;
                                $booking_required = true;
                            } else {
                                $booking_required = false;
                                $message .= ', Error creating User ' . $this->name . '/' . $this->email;
                            }
                        }
                    } else {
                        $message .= 'User ' . $this->name . ' exists for ' . $this->email;
                        $method = $this->isSBooked($this->list_id, $user_id);
                        if ($method == '') {
                            $message .= ', Subscription <b>not present</b>';
                            $booking_required = true;
                            $this->subscription_count++;
                        } else {
                            $message .= ', subscription <b>present</b>';
                            $booking_required = false;
                        }
                    }

                    if (($booking_required) AND ($this->processing == 1)) {
                        $this->book($this->list_id, $user_id);
//                        echo $this->record_count . ": Subscription created OK" . '<br>';
                        $message .= ', Subscription created';
                    }
                    echo $message . '<br>';
                }
            }
//            if (($this->record_count == 30) AND (substr(JPATH_ROOT, 14, 6) == 'joomla')) {            // Development
//                return;
//            }
        }
        fclose($handle);
    }

    public function purgeTestData() {
        // First check user is a Super-User
        if (!$this->toolsHelper->isSuperuser()) {
            Factory::getApplication()->enqueueMessage('Invalid access', 'error');
            $target = 'index.php?option=com_ramblers&view=mail_lsts';
            $this->setRedirect(Route::_($target, false));
        }
        /*
          //update field created in ra_profiles
          $sql = 'SELECT id,created,modified from #__ra_profiles';
          $rows = $this->toolsHelper->getRows($sql);
          foreach ($rows as $row) {
          echo $row->created . '<br>';
          if (($row->created == '0000-00-00') OR ($row->created == '0000-00-00 00:00:00')) {
          $this->toolsHelper->executeCommand('DELETE FROM #__ra_profiles WHERE id=' . $row->id);
          } else {
          if (strlen($row->created) == 10) {
          $new = $row->created . ' 00:00:00';
          $update = 'UPDATE #__ra_profiles SET created="' . $new . '" WHERE id=' . $row->id;
          echo "$update<br>";
          $this->toolsHelper->executeCommand($update);
          }
          }
          }
         */
        // For test
        //$start_user = 1026;  // After Andrea Parton
        //$start_subs = 54;
        // For dev
        $start_user = 980;  // After Barry Collis
        $start_subs = 12;

        // delete details of any emails sent
        $sql = 'DELETE FROM #__ra_mail_recipients WHERE user_id>' . $start_user;
        echo $sql . '<br>';
        $this->toolsHelper->executeCommand($sql);

        // Delete any subscriptions
        $sql = 'DELETE FROM #__ra_mail_subscriptions_audit WHERE object_id>' . $start_subs;
        echo $sql . '<br>';
        $rows = $this->toolsHelper->executeCommand($sql);
        $sql = 'DELETE FROM #__ra_mail_subscriptions WHERE user_id>' . $start_user;
        echo $sql . '<br>';
        $this->toolsHelper->executeCommand($sql);

        // delete profile audit records
//        $sql = 'DELETE FROM #__ra_profiles_audit WHERE object_id>' . $start_user;
//        echo $sql . '<br>';
//        $this->toolsHelper->executeCommand($sql);
        // delete the profile record itself
        $sql = 'DELETE FROM #__ra_profiles WHERE id>' . $start_user;
        echo $sql . '<br>';
        $this->toolsHelper->executeCommand($sql);

        // Delete the users
        $sql = 'DELETE FROM #__user_usergroup_map WHERE user_id>' . $start_user;
        echo $sql . '<br>';
        $this->toolsHelper->executeCommand($sql);
        $sql = 'DELETE FROM #__users WHERE id>' . $start_user;
        echo $sql . '<br>';
        $this->toolsHelper->executeCommand($sql);

        echo 'Test data deleted<br>';
    }

    public function sendEmail() {
        // send email to the administrator
        $params = ComponentHelper::getParams($this->component);
        $notify_id = $params->get('email_new_user', '0');

        if ($notify_id > 0) {
            $sql = 'SELECT email FROM #__users WHERE id=' . $notify_id;
            $to = $this->toolsHelper->getValue($sql);
            if ($to == '') {
                Factory::getApplication()->enqueueMessage('Unable to find email address for user ' . $notify_id, 'Warning');
            }
            $title = 'A new user has been registered for ' . $this->component;
            $body = 'New user registration:' . '<br>';
            $body .= 'Name <b>' . $this->name . '</b><br>';
            $body .= 'Group <b>' . $this->group_code . '</b><br>';
            $body .= 'Email <b>' . $this->email . '</b><br>';
            $response = $this->toolsHelper->sendEmail($to, $to, $title, $body);
            if ($response) {
                Factory::getApplication()->enqueueMessage('Notification sent to ' . $to, 'Info');
            }
        }
    }

    private function validEmailFormat() {
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
//            echo "Email address '$this->email' is considered valid.\n";
            return true;
        }
        $this->error_count++;
        echo "User $this->name: email address '$this->email' is considered invalid<br>";
        return false;
    }

    /**
     *   Random Key
     *
     *   @returns a string
     * */
    public static function randomKey($size) {
        // Created 26/04/22 from https://stackoverflow.com/questions/1904809/how-can-i-create-a-new-joomla-user-account-from-within-a-script
        $bag = "abcefghijknopqrstuwxyzABCDDEFGHIJKLLMMNOPQRSTUVVWXYZabcddefghijkllmmnopqrstuvvwxyzABCEFGHIJKNOPQRSTUWXYZ";
        $key = array();
        $bagsize = strlen($bag) - 1;
        for ($i = 0; $i < $size; $i++) {
            $get = rand(0, $bagsize);
            $key[] = $bag[$get];
        }
        return implode($key);
    }

}
