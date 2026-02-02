<?php

/**
 * @version    2.2.3
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 17/03/25 CB if creating confirmed booking, set confirmed date
 * 23/03/25 CB set email to lower case
 * 15/09/25 CB correct creation of Profiles
 * 19/09/25 CB Default groups_to_follow
 */

namespace Ramblers\Component\Ra_events\Administrator\Table;

// No direct access
defined('_JEXEC') or die;

use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Access\Access;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table as Table;
use \Joomla\CMS\User\User;
use \Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use \Joomla\Database\DatabaseDriver;
use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Filesystem\File;
use \Joomla\Registry\Registry;
use \Joomla\CMS\Helper\ContentHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Profile table
 *
 * @since 2.0
 */
class ProfileTable extends Table implements VersionableTableInterface, TaggableTableInterface {

    use TaggableTableTrait;

    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    protected $_supportNullValue = true;
    public $real_name;
    public $preferred_name;
    public $user_email;
    public $message;

    /**
     * Constructor
     *
     * @param   JDatabase  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db) {
        $this->typeAlias = 'com_ra_events.profile';
        parent::__construct('#__ra_profiles', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    private function generatePreferred() {
        $names = explode(' ', $this->real_name);
        $parts = count($names);
        $preferred_name = $names[0];
        if (count($names) > 1) {
            $preferred_name .= ' ' . substr($names[$parts - 1], 0, 1);
        }
        $this->preferred_name = $preferred_name;
    }

    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   2.0
     */
    public function getTypeAlias() {
        return $this->typeAlias;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array  $array   Named array
     * @param   mixed  $ignore  Optional array or list of parameters to ignore
     *
     * @return  boolean  True on success.
     *
     * @see     Table:bind
     * @since   2.0
     * @throws  \InvalidArgumentException
     */
    public function bind($array, $ignore = '') {
        $date = Factory::getDate();
        $task = Factory::getApplication()->input->get('task');
        $user = Factory::getApplication()->getIdentity();
//        echo '<br>table/bind<br>';
        //       var_dump($array);
        //       die;
// store user details for use in function store
        $this->real_name = $array['real_name'];
        $this->user_email = $array['email'];

        $input = Factory::getApplication()->input;
        $task = $input->getString('task', '');

        if (isset($array['params']) && is_array($array['params'])) {
            $registry = new Registry;
            $registry->loadArray($array['params']);
            $array['params'] = (string) $registry;
        }

        if (isset($array['metadata']) && is_array($array['metadata'])) {
            $registry = new Registry;
            $registry->loadArray($array['metadata']);
            $array['metadata'] = (string) $registry;
        }

        if (!$user->authorise('core.admin', 'com_ra_events.profile.' . $array['id'])) {
            $actions = Access::getActionsFromFile(
                            JPATH_ADMINISTRATOR . '/components/com_ra_events/access.xml',
                            "/access/section[@name='profile']/"
            );
            $default_actions = Access::getAssetRules('com_ra_events.profile.' . $array['id'])->getData();
            $array_jaccess = array();

            foreach ($actions as $action) {
                if (key_exists($action->name, $default_actions)) {
                    $array_jaccess[$action->name] = $default_actions[$action->name];
                }
            }

            $array['rules'] = $this->JAccessRulestoArray($array_jaccess);
        }

// Bind the rules for ACL where supported.
        if (isset($array['rules']) && is_array($array['rules'])) {
            $this->setRules($array['rules']);
        }

        return parent::bind($array, $ignore);
    }

    public function load($keys = null, $reset = true) {
        $response = parent::load($keys, $reset);
        if (($response) AND ($this->id > 0)) {
            $sql = 'SELECT name, email, block, requireReset ';
            $sql .= 'FROM #__users ';
            $sql .= 'WHERE id=' . $this->id;
            $helper = New ToolsHelper;
            $user = $helper->getItem($sql);
            $this->real_name = $user->name;
            $this->email = $user->email;
            $this->block = $user->block;
            $this->requireReset = $user->requireReset;
        }
        return $response;
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   2.0
     */
    public function store($updateNulls = true) {
        $app = Factory::getApplication();
        $user = Factory::getApplication()->getSession()->get('user');
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);

        $this->home_group = strtoupper($this->home_group);
        $this->user_email = strtolower($this->user_email);

        if ($this->id == 0) {
            $this->created = $date;
            $this->created_by = $user->id;
        }
        if ($this->preferred_name == '') {
            $this->generatePreferred();
        }
//        // Default groups_to_follow
//        if ($this->groups_to_follow == '') {
//            $this->groups_to_follow = $this->home_group;
//        }
        if ($this->id == 0) {
            if (JDEBUG) {
                echo "Creating user: id=$this->id<br>";
                echo "real name=$this->real_name<br>";
                echo "preferred name $this->preferred_name<br>";
                echo "email=$this->user_email<br>";
                echo "group=$this->home_group<br>";
            }
            $this->message = 'Creating user ' . $this->real_name . ' in group ' . $this->home_group . ':';
//            $app->enqueueMessage($this->message, 'info');
// Create a User record
            $password = '$2y$10$PCUXW4xpLTsLGmdJJ4NqUuuNSnpq7fBkZxB4XiqUNFq8tP1Ha3FHa'; // unspecifiedpassword
            $user = new User();   // Write to database
            $data = array(
                "name" => $this->real_name,
                "username" => $this->user_email,
                "password" => $password,
                "password2" => $password,
                "sendEmail" => '1',
                "group" => array('1', '2'), // Public & Registered
                "require_reset" => 1,
                "email" => $this->user_email
            );
            if (!$user->bind($data)) {
                $this->message .= ' Could not validate user data - Error: ' . $user->getError();
                $app->enqueueMessage($this->message, 'error');
                return false;
            }

            if (!$user->save()) {
                // throw new Exception("Could not save user. Error: " . $user->getError());
                $this->message = ' Could not create user - Error: ' . $user->getError();
                $app->enqueueMessage($this->message, 'error');
                return false;
            }
            // The Store method does not seem to link the user to groups as expected
            $this->checkUserlinks($user->id);
            // get id of the record just created
//            $this->id = $user->id;
//            $app->enqueueMessage('Creating profile ' . $this->id . ' for ' . $this->preferred_name . ' ', 'info');
//        } else {
//            $app->enqueueMessage('Updating profile ' . $this->id . ' for ' . $this->preferred_name . ' ', 'info');
        }
        // 21/03/25 Although the call to parent::store returns true, no record is created in the database
//        $response = parent::store($updateNulls);
//        var_dump($response);
//        die;
        $response = $this->writeProfile($this->id, $user->id);
        if ($response == true) {
            return $user->id;
        } else {
            return $response;
        }
    }

    /**
     * This function convert an array of Access objects into an rules array.
     *
     * @param   array  $jaccessrules  An array of Access objects.
     *
     * @return  array
     */
    private function JAccessRulestoArray($jaccessrules) {
        $rules = array();

        foreach ($jaccessrules as $action => $jaccess) {
            $actions = array();

            if ($jaccess) {
                foreach ($jaccess->getData() as $group => $allow) {
                    $actions[$group] = ((bool) $allow);
                }
            }

            $rules[$action] = $actions;
        }

        return $rules;
    }

    /**
     * Overloaded check function
     *
     * @return bool
     */
    public function check() {
        return parent::check();
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see Table::_getAssetName
     */
    protected function _getAssetName() {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Returns the parent asset's id. If you have a tree structure, retrieve the parent's id using the external key field
     *
     * @param   Table   $table  Table name
     * @param   integer  $id     Id
     *
     * @see Table::_getAssetParentId
     *
     * @return mixed The id on success, false on failure.
     */
    protected function _getAssetParentId($table = null, $id = null) {
// We will retrieve the parent-asset from the Asset-table
        $assetParent = Table::getInstance('Asset');

// Default: if no asset-parent can be found we take the global asset
        $assetParentId = $assetParent->getRootId();

// The item has the component as asset-parent
        $assetParent->loadByName('com_ra_events');

// Return the found asset-parent-id
        if ($assetParent->id) {
            $assetParentId = $assetParent->id;
        }

        return $assetParentId;
    }

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

//XXX_CUSTOM_TABLE_FUNCTION

    /**
     * Delete a record by id
     *
     * @param   mixed  $pk  Primary key value to delete. Optional
     *
     * @return bool
     */
    public function delete($pk = null) {
        $this->load($pk);
        $result = parent::delete($pk);

        return $result;
    }

    private function writeProfile($profile_id, $user_id) {
        $app = Factory::getApplication();
        $current_user = Factory::getApplication()->getSession()->get('user');
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);

        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        $query->set("home_group = " . $db->quote($this->home_group))
                ->set("groups_to_follow = " . $db->quote($this->home_group))
                ->set("preferred_name = " . $db->quote($this->preferred_name))
        ;
        if (is_null($current_user)) {
            $creator = $user_id;
        } else {
            $creator = $current_user->id;
        }
        if ($profile_id == 0) {
            $query->set("id = " . $db->quote($user_id))
                    ->set("created = " . $db->quote($date))
                    ->set("created_by = " . $db->quote($creator));
            $query->insert('#__ra_profiles');
            $result = $db->setQuery($query)->execute();
            $app->enqueueMessage('Created User and Profile records for ' . $this->preferred_name . ' ', 'info');
        } else {

            $app->enqueueMessage($message, 'info');
            $query->set("modified = " . $db->quote($date))
                    ->set("modified_by = " . $db->quote($creator))
                    ->update('#__ra_profiles')
                    ->where('id=' . $profile_id);
            $result = $db->setQuery($query)->execute();
            $app->enqueueMessage('Updated profile ' . $profile_id . ' for ' . $this->preferred_name . ' ', 'info');
        }
        return $result;
    }

}
