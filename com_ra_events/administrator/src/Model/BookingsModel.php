<?php

/**
 * @version    2.2.4
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 21/07/25 CB show all bookings
 * 15/09/25 CB remove diagnostic display
 * 22/09/25 CB defaut sequence to id DESC
 */

namespace Ramblers\Component\Ra_events\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;

/**
 * Methods supporting a list of Bookings records.
 *
 * @since  2.0
 */
class BookingsModel extends ListModel {

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see        JController
     * @since      1.6
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
//                'created_by', 'a.created_by',
//                'modified_by', 'a.modified_by',
//                'created', 'a.created',
                'modified', 'a.modified',
                'state', 'a.state',
                'e.title',
                'p.preferred_name',
                'a.num_places',
                'a.partner',
                'id', 'a.id',
            );
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   Elements order
     * @param   string  $direction  Order direction
     *
     * @return void
     *
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState('id', 'DESC');

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);

        // Split context into component and optional section
        if (!empty($context)) {
            $parts = FieldsHelper::extract($context);

            if ($parts) {
                $this->setState('filter.component', $parts[0]);
                $this->setState('filter.section', $parts[1]);
            }
        }
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string A store id.
     *
     * @since   2.0
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   2.0
     */
    protected function getListQuery() {
        // Create a new query object.
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
                $this->getState(
                        'list.select', 'DISTINCT a.*'
                )
        );
        $query->from('`#__ra_bookings` AS a');

        // Join over the users for the checked out user
        $query->select("e.title AS event");
        $query->join("INNER", "#__ra_events AS e ON e.id=a.event_id");
//        // Join over the user field 'created_by'
        $query->select('`p`.preferred_name');
        $query->join('LEFT', '#__ra_profiles AS `p` ON `p`.id = a.`user_id`');

        // Join over the user field 'modified_by'
        //       $query->select('`modified_by`.name AS `modified_by`');
        //       $query->join('LEFT', '#__users AS `modified_by` ON `modified_by`.id = a.`modified_by`');
        // Filter by published state
        $published = $this->getState('filter.state');
        if (is_numeric($published)) {
            $query->where('a.state = ' . (int) $published);
        }

        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
                $query->where('( a.partner LIKE ' . $search . '  OR  p.preferred_name LIKE ' . $search . '  OR  e.title LIKE ' . $search . ' )');
            }
        }


        // Filtering num_places
        $filter_num_places = $this->state->get("filter.num_places");

        if ($filter_num_places !== null && (is_numeric($filter_num_places) || !empty($filter_num_places))) {
            $query->where("a.`num_places` = '" . $db->escape($filter_num_places) . "'");
        }
        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'id');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('sql = ' . $this->_db->replacePrefix($query), 'notice');
        }
        return $query;
    }

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function getItems() {
        $items = parent::getItems();

        //     foreach ($items as $oneItem) {
        //         $oneItem->num_places = ($oneItem->num_places == '') ? '' : Text::_('COM_RA_EVENTS_BOOKINGS_NUM_PERSONS_OPTION_' . strtoupper(str_replace(' ', '_', $oneItem->num_places)));
        //     }

        return $items;
    }

}
