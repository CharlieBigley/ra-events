<?php

/**
 * @version    2.4.12
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
 * 10/02/26 CB changed namesspace, removed some fields, changed selection criteria
 * 27/02/26 GPT Changed version to 2.4.12
 * 27/02/26 GPT Moved tofolder src, renamed to EventsModel, added logging of number of records selected, added caching of contact names, added days_to_go field, corrected event type selection to get description, added selection criteria to only show shared events that are due to be shared and not from another site, added sorting by event date ASC    
 */

namespace Ramblers\Component\Ra_events\Api\Model;

// No direct access.
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Layout\FileLayout;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

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
                'c.name',
                'a.title',
                'url', 'a.url',
                'attachments', 'a.attachments',
                'event_date', 'a.event_date',
                'event_type_id', 'a.event_type_id',
                'title', 'a.title',
                'group_code', 'a.group_code',
                'location', 'a.location',
                'details', 'a.details',
                'a.bookable',
                'url_description', 'a.url_description',
                'event_type.description',
                'event_time', 'a.event_time',
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
        $app = Factory::getApplication();
        $number_to_show = 25;
        $default_sort_direction = 'ASC';

        // List state information.
        parent::populateState('a.event_date', $default_sort_direction);

        $list = $app->getUserState($this->context . '.list');

//        $value = $app->getUserState($this->context . '.list.limit', $app->get('list_limit', $number_to_show));
//        $list['limit'] = $value;

        $list['limit'] = $number_to_show;

        $this->setState('list.limit', $number_to_show);

        $value = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $value);

    //    $ordering = $this->getUserStateFromRequest($this->context . '.filter_order', 'filter_order', 'a.event_date');
    //    $direction = strtoupper($this->getUserStateFromRequest($this->context . '.filter_order_Dir', 'filter_order_Dir', $default_sort_direction));

        if (!empty($ordering) || !empty($direction)) {
            $list['fullordering'] = $ordering . ' ' . $direction;
            Factory::getApplication()->enqueueMessage('type ' . $event_type_id . ' Sort ' . $ordering . ' ' . $direction);
        }

        $app->setUserState($this->context . '.list', $list);


        // No search or event type filtering for API output.
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   1.0.1
     */
    protected function getListQuery() {
        $query = $this->_db->getQuery(true);

        $query->select('a.*');
        $query->select("event_type.description as event_type");
        $query->select('c.name AS contact_name');
        $query->select('DATEDIFF(a.event_date, CURRENT_DATE) AS days_to_go');

        $query->from('`#__ra_events` AS a');
        // Expose contact name explicitly for the API payload
        $query->leftJoin('#__ra_event_types AS event_type ON event_type.id = a.event_type_id');
        $query->leftJoin('#__contact_details AS c ON c.id = a.contact_id');
        $query->where('a.state = 1');
        // Only show shared events 
        $query->where('shareable=1');
        // Dont show shared events from another site
        $query->where('api_site_id IS NULL');
        // Don't show events until their share date
        $query->where('DATEDIFF(a.share_date, CURRENT_DATE)<=0');
        // Only show future events
        $query->where('DATEDIFF(a.event_date, CURRENT_DATE)>=0');

        $query->order($this->_db->escape('a.event_date ASC'));
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

        $db = $this->getDbo();
        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__ra_logfile'))
            ->set('sub_system = ' . $db->quote('RA Events'))
            ->set('record_type = ' . $db->quote(10))
            ->set('ref = ' . $db->quote('events'))
            ->set('message = ' . $db->quote('Records selected: ' . count($items)));
        $db->setQuery($query)->execute();
        // $eventHelper->createLog(10, 'events', 'Records selected: ' . count($items)); --- IGNORE ---

        $contactNameById = array();

        foreach ($items as $item) {

            $contactId = isset($item->contact_id) ? (int) $item->contact_id : 0;

            if (!isset($item->contact_name) && $contactId > 0) {
                if (array_key_exists($contactId, $contactNameById)) {
                    $item->contact_name = $contactNameById[$contactId];
                } else {
                    $db = $this->getDbo();
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('name'))
                        ->from($db->quoteName('#__contact_details'))
                        ->where($db->quoteName('id') . ' = ' . (int) $contactId);

                    $db->setQuery($query);
                    $contactNameById[$contactId] = (string) $db->loadResult();
                    $item->contact_name = $contactNameById[$contactId];
                }
            }

            // Hide internal contact_id in API payloads
            if (isset($item->contact_id)) {
                unset($item->contact_id);
            }

            if (!isset($item->contact_name) && isset($item->contact)) {
                $item->contact_name = $item->contact;
            }

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
