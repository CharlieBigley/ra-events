<?php

/**
 * @version    2.2.1
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 05/12/24 CB set event-type_id readonly if not a new record
 * 29/03/25 CB set up default for max_bookings, validation group_code
 * 16/06/25 CB If event is from a different site, make fields read-only
 * 07/07/25 CB disallow unshare
 * 27/07/25 Don't attempt to set up defaults
 * 11/09/25 CB set share_)date read only if from another site
 * 06/11/25 CB set reports/minutes read-only for shared events
 */

namespace Ramblers\Component\Ra_events\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use \Joomla\CMS\Table\Table;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Event model.
 *
 * @since  1.0.1
 */
class EventModel extends AdminModel {

    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  1.0.1
     */
    protected $text_prefix = 'COM_RA_EVENTS';

    /**
     * @var    string  Alias to manage history control
     *
     * @since  1.0.1
     */
    public $typeAlias = 'com_ra_events.event';

    /**
     * @var    null  Item data
     *
     * @since  1.0.1
     */
    protected $item = null;

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string  $type    The table type to instantiate
     * @param   string  $prefix  A prefix for the table class name. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  Table    A database object
     *
     * @since   1.0.1
     */
    public function getTable($type = 'Event', $prefix = 'Administrator', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      An optional array of data for the form to interogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \JForm|boolean  A \JForm object on success, false on failure
     *
     * @since   1.0.1
     */
    public function getForm($data = array(), $loadData = true) {

// Get the form.
        $form = $this->loadForm(
                'com_ra_events.event',
                'event',
                array(
                    'control' => 'jform',
                    'load_data' => $loadData
                )
        );
        $id = $form->getvalue('id');
        $event_type_id = $form->getvalue('event_type_id');

        if (empty($form)) {
            echo "form is empty<br>";
            return false;
        }
        $api_site_id = $form->getvalue('api_site_id');
        if ((is_null($api_site_id)) OR ($api_site_id == '0')) {
            // This Event has NOT been imported from another site
            $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
// Get the component parameters
            $params = ComponentHelper::getParams('com_ra_events');
            $form->setFieldAttribute('max_bookings', 'default', $params['max_bookings']);
            $form->setFieldAttribute('group_code', 'default', $params['default_group']);
            if ($params['show_group'] == 1) {
                $form->setFieldAttribute('group_code', 'required', 'true');
                $form->setFieldAttribute('group_code', 'validate', 'Areagroupcode');
            }
            $id = (int) $form->getvalue('id');
            if ($id > 0) {
//$form->setFieldAttribute('event_type_id', 'readonly', true);   // gives error!
                $event_type_id = (int) $form->getvalue('event_type_id');
                $form->setFieldAttribute('event_type_id', 'type', 'text');
                $form->setFieldAttribute('event_type_id', 'label', 'Type ID');
                $form->setFieldAttribute('event_type_id', 'readonly', 'true');
                $form->setFieldAttribute('publication_date', 'default', $date);
                $form->setFieldAttribute('share_date', 'default', $date);

                if ($event_type_id == '1') {
                    $form->setFieldAttribute('details', 'label', 'Agenda');
//                    $form->setFieldAttribute('details', 'type', 'editorfieldattribute');
                }
                // Don't allow Event to be un-shared
                $shareable = (int) $form->getvalue('shareable');
                if ($shareable == 1) {
                    $form->setFieldAttribute('shareable', 'readonly', 'true');
                }
//            if (($event_type_id == '1') OR ($event_type_id == '3')) { //Meeting / Training
//                $form->setFieldAttribute('group_code', 'required', 'true');
//                $form->setFieldAttribute('group_code', 'validate', 'Areagroupcode');
//            }
            }
        } else {
            // This Event has been imported from another site
            $form->setFieldAttribute('event_date', 'readonly', 'true');
            $form->setFieldAttribute('event_date_end', 'readonly', 'true');
            $form->setFieldAttribute('event_time', 'readonly', 'true');
            $form->setFieldAttribute('title', 'readonly', 'true');
            $form->setFieldAttribute('location', 'readonly', 'true');
            $form->setFieldAttribute('contact_id', 'type', 'hidden');
            $form->setFieldAttribute('contact_id', 'readonly', 'true');
            $form->setFieldAttribute('group_code', 'readonly', 'true');
            $form->setFieldAttribute('attachments', 'readonly', 'true');
            $form->setFieldAttribute('attachment_description', 'readonly', 'true');
            $form->setFieldAttribute('url', 'readonly', 'true');
            $form->setFieldAttribute('url_description', 'readonly', 'true');
            $form->setFieldAttribute('details', 'readonly', 'true');
            $form->setFieldAttribute('reports', 'readonly', 'true');
            $form->setFieldAttribute('minutes', 'readonly', 'true');
            $form->setFieldAttribute('bookable', 'readonly', 'true');
            $form->setFieldAttribute('max_bookings', 'readonly', 'true');
            $form->setFieldAttribute('notify_organiser', 'readonly', 'true');
            $form->setFieldAttribute('status', 'readonly', 'true');
            $form->setFieldAttribute('publication_date', 'readonly', 'true');
            $form->setFieldAttribute('shareable', 'readonly', 'true');
            $form->setFieldAttribute('share_date', 'readonly', 'true');
            $form->setFieldAttribute('event_type_id', 'type', 'hidden');
            $form->setFieldAttribute('event_type_id', 'readonly', 'true');
        }
        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   1.0.1
     */
    protected function loadFormData() {
        $mode = Factory::getApplication()->getUserState('com_ra_events.edit.event.mode');
//        $id = Factory::getApplication()->getUserState('com_ra_events.edit.event.id');
//        if ($id == 0) {
//        if ($mode == 'A') {
//            $prefill_data = array("details" => $params['default_agenda']);
//            var_dump($prefill_data);
//            }
//           die('Model, mode =' . $mode . ', ' . $id);
//        }
// Check the session for previously entered form data.
//        $data = Factory::getApplication()->getUserState('com_ra_events.edit.event.data', $prefill_data);
        $data = Factory::getApplication()->getUserState('com_ra_events.edit.event.data', array());
        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;
//            echo "mode $mode<br>";
//            echo 'Agenda ' . $data->details . '<br>';
//              die('Model, data from this->item');
        }
        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed    Object on success, false on failure.
     *
     * @since   1.0.1
     */
    public function getItem($pk = null) {

        if ($item = parent::getItem($pk)) {
            if (isset($item->params)) {
                $item->params = json_encode($item->params);
            }

// Do any procesing on fields here if needed
        }

        return $item;
    }

    /**
     * Method to duplicate an Event
     *
     * @param   array  &$pks  An array of primary key IDs.
     *
     * @return  boolean  True if successful.
     *
     * @throws  Exception
     */
    public function duplicate(&$pks) {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

// Access checks.
        if (!$user->authorise('core.create', 'com_ra_events')) {
            throw new \Exception(Text::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
        }

        $context = $this->option . '.' . $this->name;

// Include the plugins for the save events.
        PluginHelper::importPlugin($this->events_map['save']);

        $table = $this->getTable();

        foreach ($pks as $pk) {

            if ($table->load($pk, true)) {
// Reset the id to create a new record.
                $table->id = 0;

                if (!$table->check()) {
                    throw new \Exception($table->getError());
                }

                if (!empty($table->event_type_id)) {
                    if (is_array($table->event_type_id)) {
                        $table->event_type_id = implode(',', $table->event_type_id);
                    }
                } else {
                    $table->event_type_id = '';
                }


// Trigger the before save event.
                $result = $app->triggerEvent($this->event_before_save, array($context, &$table, true, $table));

                if (in_array(false, $result, true) || !$table->store()) {
                    throw new \Exception($table->getError());
                }

// Trigger the after save event.
                $app->triggerEvent($this->event_after_save, array($context, &$table, true));
            } else {
                throw new \Exception($table->getError());
            }
        }

// Clean cache
        $this->cleanCache();

        return true;
    }

    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param   Table  $table  Table Object
     *
     * @return  void
     *
     * @since   1.0.1
     */
    protected function prepareTable($table) {
        jimport('joomla.filter.output');
    }

}
