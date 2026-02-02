<?php

/**
 * @version    2.1.3
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 20/03/25 CB use CurrentUserInterface;
 */

namespace Ramblers\Component\Ra_events\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Layout\FileLayout;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\User\CurrentUserInterface;

/**
 * Methods supporting a list of Ra_events records.
 *
 * @since  2.0
 */
class BookingsModel extends ListModel implements CurrentUserInterface {

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see    JController
     * @since  2.0
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'created_by', 'a.created_by',
//                'modified_by', 'a.modified_by',
                'created', 'a.created',
//                'modified', 'a.modified',
                'id', 'a.id',
                'state', 'a.state',
                'partner', 'a.partner',
                'p.preferred_name',
                'a.title',
                'num_places', 'a.num_places',
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
     * @return  void
     *
     * @throws  Exception
     *
     * @since   2.0
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState('a.user_id', 'ASC');

        $app = Factory::getApplication();
        $list = $app->getUserState($this->context . '.list');

        $value = $app->getUserState($this->context . '.list.limit', $app->get('list_limit', 25));
        $list['limit'] = $value;

        $this->setState('list.limit', $value);

        $value = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $value);

        $ordering = $this->getUserStateFromRequest($this->context . '.filter_order', 'filter_order', 'a.user_id');
        $direction = strtoupper($this->getUserStateFromRequest($this->context . '.filter_order_Dir', 'filter_order_Dir', 'ASC'));

        if (!empty($ordering) || !empty($direction)) {
            $list['fullordering'] = $ordering . ' ' . $direction;
        }

        $app->setUserState($this->context . '.list', $list);

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

//        // Join over the users for the checked out user.
//        $query->select('uc.name AS uEditor');
//        $query->join('LEFT', '#__users AS uc ON uc.id=a.checked_out');



        if (!$this->getCurrentUser()->authorise('core.edit', 'com_ra_events')) {
            $query->where('a.state = 1');
        } else {
            $query->where('(a.state IN (0, 1))');
        }

//			// Filter by search in title
//			$search = $this->getState('filter.search');
//
//			if (!empty($search))
//			{
//				if (stripos($search, 'id:') === 0)
//				{
//					$query->where('a.id = ' . (int) substr($search, 3));
//				}
//				else
//				{
//					$search = $db->Quote('%' . $db->escape($search, true) . '%');
//					$query->where('( a.partner LIKE ' . $search . '  OR  a.user_id LIKE ' . $search . '  OR  a.event_id LIKE ' . $search . ' )');
//				}
//			}
        // Filtering num_places
        $filter_num_places = $this->state->get("filter.num_places");

        if ($filter_num_places !== null && (is_numeric($filter_num_places) || !empty($filter_num_places))) {
            $query->where("a.`num_places` = '" . $db->escape($filter_num_places) . "'");
        }



        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'a.user_id');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    /**
     * Method to get an array of data items
     *
     * @return  mixed An array of data on success, false on failure.
     */
    public function getItems() {
        $items = parent::getItems();

//        foreach ($items as $item) {
//            $item->num_places = empty($item->num_places) ? '' : Text::_('COM_RA_EVENTS_BOOKINGS_NUM_PERSONS_OPTION_' . strtoupper(str_replace(' ', '_', $item->num_places)));
//        }

        return $items;
    }

    /**
     * Overrides the default function to check Date fields format, identified by
     * "_dateformat" suffix, and erases the field if it's not correct.
     *
     * @return void
     */
    protected function loadFormData() {
        $app = Factory::getApplication();
        $filters = $app->getUserState($this->context . '.filter', array());
        $error_dateformat = false;

        foreach ($filters as $key => $value) {
            if (strpos($key, '_dateformat') && !empty($value) && $this->isValidDate($value) == null) {
                $filters[$key] = '';
                $error_dateformat = true;
            }
        }

        if ($error_dateformat) {
            $app->enqueueMessage(Text::_("COM_RA_EVENTS_SEARCH_FILTER_DATE_FORMAT"), "warning");
            $app->setUserState($this->context . '.filter', $filters);
        }

        return parent::loadFormData();
    }

    /**
     * Checks if a given date is valid and in a specified format (YYYY-MM-DD)
     *
     * @param   string  $date  Date to be checked
     *
     * @return bool
     */
    private function isValidDate($date) {
        $date = str_replace('/', '-', $date);
        return (date_create($date)) ? Factory::getDate($date)->format("Y-m-d") : null;
    }

}
