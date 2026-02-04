<?php

/**
 * @version    2.4.6
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 16/10/23 CB derive organiser from contact_details
 * 26/10/23 CB support for Bookings
 * 30/10/23 CB add days_to_go
 * 25/11/23 CB always sort by event_date, DESC for committee meetings, else ASC
 * 02/12/24 CB change description to title
 * 09/12/24 CB show past events for Inspections
 * 10/12/24 CB correct sort
 * 05/03/25 CB select bookable
 * 30/03/25 CB don't show Events until their publication date
 * 14/04/25 CB correction for location
 * 16/06/25 CB get a.*
 * 01/10/25 CB allow sorting
 * 19/01/26 CB debug sorting (grid.sort)
 * 20/01/26 CB remove diagnostic display
 * 04/02/26 CB return actual fields for details/reports/minutes
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
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Methods supporting a list of Ra_events records.
 *
 * @since  1.0.1
 */
class EventsModel extends ListModel {

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see    JController
     * @since  1.0.1
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'a.event_date',
                'a.event_time',
                'a.title',
                'a.location',
                'a.details',
                'a.reports',
                'a.minutes',
                'a.url',
                'a.attachments',
                'a.group_code',
                // additional sort fields when showing all events
                'event_type.description',
                'c.name',
                'a.bookable',
                
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
     * @since   1.0.1
     */
    protected function populateState($ordering = null, $direction = null) {
        // Find the type of event
        $app = Factory::getApplication();
        $event_type_id = $app->input->getInt('event_type_id', '0');

        // List state information.
        parent::populateState('a.event_date', 'ASC');

        $list = array();
        if (($event_type_id > 1) AND ($event_type_id < 5)) {
            $number_to_show = 5;
        } else {
            $number_to_show = 25; // Committee Meetings / WM / Bookings
        }
        $list['limit'] = $number_to_show;
        $this->setState('list.limit', $number_to_show);

        $value = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $value);

        // Get user sort order and direction
        $ordering = $this->getUserStateFromRequest($this->context . '.filter_order', 'filter_order', 'a.event_date');
//         Factory::getApplication()->enqueueMessage( 'Ordering requested: ' . $ordering  );
        $direction = strtoupper($this->getUserStateFromRequest($this->context . '.filter_order_Dir', 'filter_order_Dir', ''));

        // If user hasn't chosen a direction, force DESC for committee meetings, else ASC
        if (empty($direction)) {
            if ($event_type_id == 1) {
                $direction = 'DESC';
            } else {
                $direction = 'ASC';
            }
        }

        $this->setState('list.ordering', $ordering);
        $this->setState('list.direction', $direction);

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
     * @since   1.0.1
     */
    protected function getListQuery() {
        // Find the type of event
        $this->event_type_id = Factory::getApplication()->input->getInt('event_type_id', '0');

        $query = $this->_db->getQuery(true);

        $query->select('a.*');
        $query->select("event_type.description as event_type");
        $query->select('c.name');
        $query->select('DATEDIFF(a.event_date, CURRENT_DATE) AS days_to_go');
        $query->select("a.details as full_details");
        $query->from('`#__ra_events` AS a');
        $query->select('c.name', 'contact');
        $query->leftJoin('#__ra_event_types AS event_type ON event_type.id = a.event_type_id');
        $query->leftJoin('#__contact_details AS c ON c.id = a.contact_id');
        $query->where('a.state = 1');
        // The Event Type will have been set up in the __construct function, depending on the menu parameter
        if ($this->event_type_id != 0) {
            $query->where('a.event_type_id=' . $this->event_type_id);
        }
        // Don't show events until their publication date
        $query->where('DATEDIFF(a.publication_date, CURRENT_DATE)<=0');
        // Except for Committee meetings & Inspections, only show future events
        if ($this->event_type_id !== 1) {
            $query->where('DATEDIFF(a.event_date, CURRENT_DATE)>=0');
            if (JDEBUG) {
                Factory::getApplication()->enqueueMessage('DATEDIFF(a.event_date, CURRENT_DATE)>=0');
            }
        }
        // Search for this word
        $searchWord = $this->getState('filter.search');

        // Search in these columns
        $searchColumns = array(
            'event_type.description',
            'a.title',
            'c.name',
            'a.location',
            'a.details',
            'a.group_code',
        );

        if (!empty($searchWord)) {
            if (stripos($searchWord, ' id:') === 0) {
                // Build the ID search
                $idPart = (int) substr($searchWord, 3);
                $query->where($this->_db->qn('a .id') . ' = ' . $this->_db->q($idPart));
            } else {
                $query = ToolsHelper::buildSearchQuery($searchWord, $searchColumns, $query);
            }
        }

        $orderCol = $this->state->get('list.ordering', 'a.event_date');
        $orderDirn = $this->state->get('list.direction', 'ASC');
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('Sorting by ' . $orderCol . ' ' . $orderDirn);    
        }           
        // Validate orderCol against filter_fields
        $validOrderCols = $this->filter_fields;
        // filter_fields can be key=>value or flat, so flatten if needed
        if (array_values($validOrderCols) !== $validOrderCols) {
            $validOrderCols = array_values($validOrderCols);
        }
        if (!in_array($orderCol, $validOrderCols, true)) {
            Factory::getApplication()->enqueueMessage('Requested sort field "' . $orderCol . '" not found in filter_fields. Falling back to a.event_date.', 'warning');
            $orderCol = 'a.event_date';
        }
     
        $query->order($this->_db->escape($orderCol . ' ' . $orderDirn));
        if (JDEBUG) {
            echo $this->_db->replacePrefix($query) . '<br>';
            Factory::getApplication()->enqueueMessage($this->_db->replacePrefix($query));
//            Factory::getApplication()->enqueueMessage( $query->__toString());
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

            if (isset($item->event_type_id)) {

                $values = explode(', ', $item->event_type_id);
                $textValue = array();

                foreach ($values as $value) {
                    $db = $this->getDbo();
                    $query = $db->getQuery(true);
                    $query
                            ->select('`description`')
                            ->from($db->quoteName('#__ra_event_types'))
                            ->where($db->quoteName('id') . ' = ' . $db->quote($db->escape($value)));

                    $db->setQuery($query);
                    $results = $db->loadObject();

                    if ($results) {
                        $textValue[] = $results->description;
                    }
                }

                $item->event_type_id = !empty($textValue) ? implode(', ', $textValue) : $item->event_type_id;
            }
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
            $app->enqueueMessage(Text::_("Invalid date format"), "warning");
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
