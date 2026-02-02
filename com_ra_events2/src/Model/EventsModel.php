<?php

/**
 * @version    2.2.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt

 * 07/06/25 MK Copied from release 2.0.2 and modified for use as WebAPI model, simplified to return all published events
 * 01/09/25 CB Use bespoke Api model, include contact details, all states, only shareable
 */

namespace Ramblers\Component\Ra_events\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Methods supporting a list of Events records.
 *
 * @since  1.0.1
 */
class EventsModel extends ListModel {

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
                'a.id',
                'a.state',
                'c.name',
                'a.event_date',
                'a.event_time',
                'event_type.description',
                'a.title',
                'a.group_code',
                'a.location',
                'a.url',
                'a.attachments',
                'a.url_description',
                'a.attachment_description',
            );
            $this->search_fields = $config['filter_fields'];
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
        parent::populateState('event_date', 'DESC');

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

// populateState()

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
     * @since   1.0.1
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

// getStoreId(

    /**
     * Method to get the model refernce.
     *
     * @since   1.0.1
     */
    protected function getModel() {
        return parent::getModel('Events', 'Api', array('ignore_request' => true));
    }

// getModel()

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   1.0.1
     */
    protected function getListQuery() {
        // Create a new query object.
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select('a.*');
        $query->select('p.preferred_name AS contact_name', 'u.email');
        $query->from('`#__ra_events` AS a');

        $query->leftJoin('#__ra_event_types AS event_type ON event_type.id = a.event_type_id');
        $query->leftJoin('#__contact_details AS c ON c.id = a.contact_id');
        $query->leftJoin('#__ra_profiles AS p ON p.id = c.user_id');
        $query->leftJoin('#__users AS u ON u.id = c.user_id');
        $query->where('(a.shareable =1)');

        //       $query->order('title');

        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('sql = ' . $this->_db->replacePrefix($query), 'notice');
        }
        return $query;
    }

// getListQuery()

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function getItems() {
        $items = parent::getItems();

        return $items;
    }

//getItems()

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function validate($form, $data, $group = true) {
        $app = Factory::getApplication();

        return $data;
    }

// validate()
}
