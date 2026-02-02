<?php

/**
 * @version    2.0.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 04/03/25 CB created
 * 24/03/25 CB support for null created + cancelled
 * 10/04/25 CB if partner specified, set num_places to 2
 */

namespace Ramblers\Component\Ra_events\Administrator\Table;

// No direct access
defined('_JEXEC') or die;

use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Access\Access;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table as Table;
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
 * Event table
 *
 * @since 1.0.1
 */
class BookingsTable extends Table implements VersionableTableInterface, TaggableTableInterface {

    use TaggableTableTrait;

    /**
     * Constructor
     *
     * @param   JDatabase  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db) {
        $this->typeAlias = 'com_ra_events.event';
        parent::__construct('#__ra_bookings', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   1.0.1
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
     * @since   1.0.1
     * @throws  \InvalidArgumentException
     */
    public function bind($array, $ignore = '') {

//        echo 'bind: event_type_id ' . $this->event_type_id . '/' . $array['event_type_id'] . '<br>';
//        echo 'bind: url ' . $this->url . '/' . $array['url'] . '<br>';
//        die('bind' . var_dump($array));
//        $date = Factory::getDate();
        $task = Factory::getApplication()->input->get('task');
        $user = Factory::getApplication()->getIdentity();

        if ($this->partner !== '') {
            $this->num_places = 2;
        }
        // Support for fields that must be null
        if ($array['confirmed'] == '') {
            $array['confirmed'] = NULL;
            $this->confirmed = NULL;
        }
        if ($array['cancelled'] == '') {
            $array['cancelled'] = NULL;
            $this->cancelled = NULL;
        }

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

        if (!$user->authorise('core.admin', 'com_ra_events.event.' . $array['id'])) {
            $actions = Access::getActionsFromFile(
                            JPATH_ADMINISTRATOR . '/components/com_ra_events/access.xml',
                            "/access/section[@name='event']/"
            );
            $default_actions = Access::getAssetRules('com_ra_events.event.' . $array['id'])->getData();
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

    /**
     * Overloaded check function
     *
     * @return bool
     */
    public function check() {
        $app = Factory::getApplication();

        $id = (int) $array['id'];
        if (($this->num_place == 2) AND ($this->partner == '')) {
            throw new \Exception('Name of second person must be given');
        }
//        var_dump($array);
//        echo 'check: event_type_id ' . $this->event_type_id . '/' . $array['event_type_id'] . '<br>';


        return parent::check();
    }

    protected function prepareTable($table) {

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
     * @since   1.0.1
     */
    public function store($updateNulls = true) {

        $user = Factory::getApplication()->getSession()->get('user');
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        if ($this->id == 0) {
            $this->created = $date;
            $this->created_by = $user->id;
        } else {
            if (($this->state == 1) AND ($this->confirmed_by == 0)) {
                $this->confirmed = $date;
                $this->confirmed_by = $user->id;
            } elseif (($this->state == -2) AND ($this->cancelled_by == 0)) {
                $this->cancelled = $date;
                $this->cancelled_by = $user->id;
            }
        }
//        echo 'id ' . $this->id . '<br>';
//        echo 'Created ' . $this->created . '<br>';
//        echo 'Created by ' . $this->created_by . '<br>';
//
//        echo 'Event_id ' . $this->event_id . '<br>';
//        echo 'User_id ' . $this->user_id . '<br>';
//        echo 'confirmed ' . $this->confirmed . '<br>';
//        echo 'confirmed by ' . $this->confirmed_by . '<br>';
//        echo 'State ' . $this->state . '<br>';
//        $message = 'Setting status to ';
//        if ($this->state == 0) {
//            $message .= 'Provisional';
//        } elseif ($this->state == 1) {
//            $message .= 'Confirmed';
//        } elseif ($this->state == -2) {
//            $message .= 'Cancelled';
//        }
//        echo $message;
//        //       die('Table store');
//        Factory::getApplication()->enqueueMessage($message, 'info');
        return parent::store($updateNulls);
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

}
