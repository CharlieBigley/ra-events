<?php

/**
 * @version    2.1.6
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 07/05/25 CB include name of partner from bookings
 * 07/07/25 CB use sub-query
 * 21/07/25 CB correct setup of subquery
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

/**
 * Methods supporting a list of Ra_events records.
 *
 * @since  2.0
 */
class ProfilesModel extends ListModel {

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
                'id', 'u.id',
                'p.home_group',
                'title', 'b.title',
                'other', 'b.partner',
                'preferred_name', 'p.preferred_name',
                'created_by.preferred_name',
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
        parent::populateState('p.preferred_name', 'ASC');

        $app = Factory::getApplication();
        $list = $app->getUserState($this->context . '.list');

        $value = $app->getUserState($this->context . '.list.limit', $app->get('list_limit', 25));
        $list['limit'] = $value;

        $this->setState('list.limit', $value);

        $value = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $value);

        $ordering = $this->getUserStateFromRequest($this->context . '.filter_order', 'filter_order', 'a.preferred_name');
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

        $event_id = Factory::getApplication()->getUserState('com_ra_events.profiles.event_id', 0);
        $booking_subquery = $this->_db->getQuery(true);
        $booking_subquery->select('user_id, b.id, b.state,s.title, partner')
                ->from('`#__ra_bookings` AS b')
                ->innerjoin('`#__ra_event_states` AS s ON s.id = b.state ')
                ->where('event_id = ' . $event_id);

        $query = $this->_db->getQuery(true);
        $query->select('u.id, u.name, u.email,u.username, u.requireReset')
                ->select('p.home_group,p.preferred_name')
                ->select('b.user_id, b.id AS booking_id, b.partner, b.state, b.title')
                ->from('`#__users` AS `u`')
                ->leftjoin('(' . $booking_subquery . ') AS b ON b.user_id = u.id')
                ->innerjoin('`#__ra_profiles`' . ' AS p ON p.id = u.id')
                // Only look for active Users
                ->where('`u`.`block`= 0');
        /*
          $sql = 'SELECT u.id, u.name, u.email,u.username, u.requireReset, ';
          $sql .= 'p.home_group,p.preferred_name, ';
          $sql .= 'b.user_id, b.id AS booking_id, b.state, b.created, b.partner  ';
          $sql .= 'FROM #__users AS u  ';
          $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = u.id  ';
          $sql .= 'LEFT JOIN (';
          $sql .= '    SELECT user_id, id, state,created, partner FROM #__ra_bookings where event_id=3 ) ';
          $sql .= '   AS b ON b.user_id = u.id ';
          // Only look for active Users
          $sql .= 'WHERE `u`.`block`= 0 ';
          echo $sql;
          $query = $this->_db->getQuery(true);
          //        $this->_db->setQuery($sql);
         */
// Filter by search in title
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('u.id = ' . (int) substr($search, 3));
            } else {
                $search = $this->_db->quote('%' . $this->_db->escape($search, true) . '%');
                $query->where('( p.home_group LIKE ' . $search . '  OR  p.preferred_name LIKE ' . $search .
                        ' OR b.partner LIKE ' . $search . ' )');
            }
        }


// Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'a.preferred_name');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($this->_db->escape($orderCol . ' ' . $orderDirn));
        }
// Create a new query object.
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage($this->_db->replacePrefix($query));
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

        foreach ($items as $item) {
            $item->num_persons = empty($item->num_persons) ? '' : Text::_('COM_RA_EVENTS_PROFILES_NUM_PERSONS_OPTION_' . strtoupper(str_replace(' ', '_', $item->num_persons)));
        }

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
