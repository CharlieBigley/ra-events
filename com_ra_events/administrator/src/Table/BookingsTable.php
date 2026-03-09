<?php

/**
 * @version    2.4.13
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 04/03/25 CB created
 * 24/03/25 CB support for null created + cancelled
 * 10/04/25 CB if partner specified, set num_places to 2
 * 28/09/25 CB update events / num_bookings
 * 03/10/25 CB of creating a booking from email, log created_by as user from booking
 * 14/11/25 CB set special_request & confirmed as Null
 * 26/11/25 CB reset partner name if only one place
 * 02/03/26 CB don't set created, let database do it
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
        $task = Factory::getApplication()->input->get('task');
        $user = Factory::getApplication()->getIdentity();
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        
        if ($array['num_places'] == 1) {
            $this->partner = '';
            $array['partner'] = '';
        }
        // Support for fields that must be null
       if ($array['created'] == '') {
            $array['created'] = $date;
            $this->created = $date;
        }
        if ($array['special_requests'] == '') {
            $array['special_requests'] = NULL;
            $this->special_requests = NULL;
        }
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

    protected function lookupBooking($event_id, $user_id) {
 //       die('lookupBooking: event_id ' . $event_id . ' user_id ' . $user_id);

        $sql = 'SELECT id FROM #__ra_bookings WHERE event_id=' . (int) $event_id . ' AND user_id=' . (int) $user_id;
 //       die($sql);
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        $query->select('id')
                ->from('#__ra_bookings')
                ->where('event_id=' . (int) $event_id)
                ->where('user_id=' . (int) $user_id);
        return $db->setQuery($query)->loadResult();
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
//        Factory::getApplication()->enqueueMessage('Storing booking, id=' . $this->id    , 'error');  
        $user = Factory::getApplication()->getSession()->get('user');
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
       
        if ($this->id != 0) {
            if (($this->state == 1) AND ($this->confirmed_by == 0)) {
                $this->confirmed = $date;
                $this->confirmed_by = $user->id;
            } elseif (($this->state == -2) AND ($this->cancelled_by == 0)) {
                $this->cancelled = $date;
                $this->cancelled_by = $user->id;
            }
        } else {
            if ($user->id == 0) {
                // Being created for an email link (no user logged in)
                $this->created_by = $this->user_id;
            } else {
                $this->created_by = $user->id;
            }
        }
        echo 'id ' . $this->id . '<br>';
        if ($this->created == '') {
            $message= 'store: created is empty string, setting to ' . $date . '<br>';
            Factory::getApplication()->enqueueMessage($message, 'info');
            $this->created = $date;
        }
        echo 'created_by ' . $this->created_by . '<br>';
//
        echo 'Event_id ' . $this->event_id . '<br>';
//        echo 'User_id ' . $this->user_id . '<br>';
//        echo 'custom1 ' . $this->custom1 . '<br>';
//        echo 'custom2 ' . $this->custom2 . '<br>';
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
        echo $message;
//        die('Table store');
//return;
        $response = parent::store($updateNulls);
        if ($response) {
            if (is_null($this->event_id)) {
                Factory::getApplication()->enqueueMessage('event is null', 'error');
                return $response;
            }
            $sql = 'SELECT SUM(b.num_places) AS `tot` ';
            $sql .= 'FROM #__ra_events AS e ';
            $sql .= 'INNER JOIN #__ra_bookings AS b ON b.event_id = e.id  ';
            $sql .= 'WHERE e.id=' . $this->event_id . ' ';
            $sql .= 'AND e.state=1 ';
            $sql .= 'AND b.state=1 ';
//        Factory::getApplication()->enqueueMessage($sql, 'error');
            $toolsHelper = new ToolsHelper;
            $count = $toolsHelper->getValue($sql);
            $sql = 'UPDATE #__ra_events SET num_bookings=' . (int) $count;
            $sql .= ' WHERE id=' . $this->event_id;
//            Factory::getApplication()->enqueueMessage($sql, 'info');
            $toolsHelper->executeCommand($sql);
        } else {
            Factory::getApplication()->enqueueMessage('Error from parent class storing booking: ' . $this->getError(), 'error');
        }
        return $response;
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
