<?php

/**
 * @version    2.1.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 16/10/23 CB check Agenda/ reports/Minutes for NULL
 * 26/10/23 CB allow sorting by id
 * 29/10/23 Cb show published and unpublished
 * 31/10/24 CB filter by event_type
 * 02/12/24 CB change description to title
 * 03/12/24 CB added function validate from com_ra_tools/ upload
 * 30/03/25 CB show Special
 * 16/06/25 CB get a.*
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

    protected function getModel() {
        return parent::getModel('Events', 'Administrator', array('ignore_request' => true));
    }

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
        $query->select("DATE_FORMAT(a.event_date, '%d/%c/%y') as pretty_event_date");
        $query->select('datediff(publication_date, CURRENT_DATE) AS publication_to_go');
        $query->select("event_type.description as event_type");
        $query->select('c.name', 'contact');
        $query->select("CASE when (a.minutes IS NULL) THEN" .
                " 'N' ELSE " .
                "'Y' END as minutes");
        $query->select("CASE when (a.reports IS NULL) THEN" .
                " 'N' ELSE " .
                "'Y' END as reports");
        $query->select("CASE when (a.details IS NULL) THEN" .
                " 'N' ELSE " .
                "'Y' END as details");
        $query->from('`#__ra_events` AS a');

        $query->leftJoin('#__ra_event_types AS event_type ON event_type.id = a.event_type_id');
        $query->leftJoin('#__contact_details AS c ON c.id = a.contact_id');

        // Filter by published state
        $published = $this->getState('filter.state', '1');

        if (is_numeric($published)) {
            $query->where('a.state = ' . (int) $published);
        } elseif (empty($published)) {
            $query->where('(a.state IN (0, 1))');
        }
        // Filter by event type
        $event_type_id = $this->getState('filter.event_type_id');
        if ($event_type_id != '') {
            $query->where('a.event_type_id = ' . (int) $event_type_id);
        }


        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $query = ToolsHelper::buildSearchQuery($search, $this->search_fields, $query);
            }
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'event_date');
        $orderDirn = $this->state->get('list.direction', 'DESC');

        if ($orderCol && $orderDirn) {
            $order_clause = $db->escape($orderCol . ' ' . $orderDirn);
            if ($orderCol == 'a.event_date') {
                $order_clause .= ', ' . $db->escape('a.event_time' . ' ' . $orderDirn);
            }
            $query->order($order_clause);
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

        return $items;
    }

    public function validate($form, $data, $group = true) {
        $app = Factory::getApplication();
        return $data;
        /*
          // Permitted MIME types are defined in the menu entry, and saved by the View
          $MIMETypes = $app->getUserState('com_ra_tools.upload_mimes', 'text/plain');

          // following code copied from table / check

          $files = $app->input->files->get('jform', array(), 'raw');
          $array = $app->input->get('jform', array(), 'ARRAY');
          //        var_dump($files);
          //        echo '<br>';
          //        echo $files['file_name'][0];
          //        var_dump($files['file_name'][0]);
          //        echo '<br>';
          //        echo $files['file_name'][0]['name'];
          //        echo '<br>';
          //        echo $files['file_name'][0]['size'];

          if ($files['file_name'][0]['name'] == '') {
          $app->enqueueMessage('Please select a file', 'error');
          return false;
          }
          if ($files['file_name'][0]['size'] == 0) {
          $app->enqueueMessage($files['file_name'][0]['name'] . ' is empty', 'error');
          return false;
          }


          foreach ($files['file_name'] as $singleFile) {
          jimport('joomla.filesystem.file');

          // Check if the server found any error.
          $fileError = $singleFile['error'];
          $message = '';

          if ($fileError > 0 && $fileError != 4) {
          switch ($fileError) {
          case 1:
          $message = Text::_('File size exceeds allowed by the server');
          break;
          case 2:
          $message = Text::_('File size exceeds allowed by the html form');
          break;
          case 3:
          $message = Text::_('Partial upload error');
          break;
          }

          if ($message != '') {
          $app->enqueueMessage($message, 'warning');
          return false;
          }
          } elseif ($fileError == 4) {
          if (isset($array['file_name'])) {
          $this->file_name = $array['file_name'];
          }
          } else {
          // Check for filetype
          $validMIMEArray = explode(',', $MIMETypes);
          $fileMime = $singleFile['type'];

          if (!in_array($fileMime, $validMIMEArray)) {
          $app->enqueueMessage('File <b>' . $singleFile['name'] . '</b>, type <b>' . $fileMime . '</b> is not allowed (must be ' . $MIMETypes . ')', 'warning');

          return false;
          }
          }
          }

         */
        return $data;
    }

}
