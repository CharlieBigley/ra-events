<?php

/**
 * @version    2.4.7
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 22/03/25 CB replace getIdentity
 * 04/08/25 CB notify organiser
 * 22/09/25 CB show fields custom1 and custom2
 * 29/09/25 CB Don't check user access
 * 03/11/25 CB change filter from word to string
 * 13/11/25 CB correct declaration of toolsHelper
 * 24/02/26 Send email confirmation
 */

namespace Ramblers\Component\Ra_events\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table;
use \Joomla\CMS\MVC\Model\FormModel;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Ra_events model.
 *
 * @since  2.0
 */
class BookingformModel extends FormModel implements CurrentUserInterface {

    private $item = null;

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return  void
     *
     * @since   2.0
     *
     * @throws  Exception
     */
    protected function populateState() {
        $app = Factory::getApplication('com_ra_events');

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_ra_events.edit.booking.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_ra_events.edit.booking.id', $id);
        }

        $this->setState('booking.id', $id);

        // Load the parameters.
        $params = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('booking.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to get an ojbect.
     *
     * @param   integer $id The id of the object to get.
     *
     * @return  Object|boolean Object on success, false on failure.
     *
     * @throws  Exception
     */
    public function getItem($id = null) {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('booking.id');
            }

            // Get a level row instance.
            $table = $this->getTable();
            $properties = $table->getProperties();
            $this->item = ArrayHelper::toObject($properties, CMSObject::class);

            if ($table !== false && $table->load($id) && !empty($table->id)) {
                $user = $this->getCurrentUser();
                $id = $table->id;

//             May be creating a booking from a web-link
//                $canEdit = $user->authorise('core.edit', 'com_ra_events') || $user->authorise('core.create', 'com_ra_events');
//
//                if (!$canEdit && $user->authorise('core.edit.own', 'com_ra_events')) {
//                    $canEdit = $user->id == $table->created_by;
//                }
//
//                if (!$canEdit) {
//                    throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
//                }
                // Check published state.
                if ($published = $this->getState('filter.published')) {
                    if (isset($table->state) && $table->state != $published) {
                        return $this->item;
                    }
                }

                // Convert the Table to a clean CMSObject.
                $properties = $table->getProperties(1);
                $this->item = ArrayHelper::toObject($properties, CMSObject::class);
            }
        }

        return $this->item;
    }

    /**
     * Method to get the table
     *
     * @param   string $type   Name of the Table class
     * @param   string $prefix Optional prefix for the table class name
     * @param   array  $config Optional configuration array for Table object
     *
     * @return  Table|boolean Table if found, boolean false on failure
     */
    public function getTable($type = 'Bookings', $prefix = 'Administrator', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Get an item by alias
     *
     * @param   string $alias Alias string
     *
     * @return int Element id
     */
    public function getItemIdByAlias($alias) {
        $table = $this->getTable();
        $properties = $table->getProperties();

        if (!in_array('alias', $properties)) {
            return null;
        }

        $table->load(array('alias' => $alias));
        $id = $table->id;

        return $id;
    }

    /**
     * Method to check in an item.
     *
     * @param   integer $id The id of the row to check out.
     *
     * @return  boolean True on success, false on failure.
     *
     * @since   2.0
     */
    public function checkin($id = null) {
        // Get the id.
        $id = (!empty($id)) ? $id : (int) $this->getState('booking.id');

        if ($id) {
            // Initialise the table
            $table = $this->getTable();

            // Attempt to check the row in.
            if (method_exists($table, 'checkin')) {
                if (!$table->checkin($id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Method to check out an item for editing.
     *
     * @param   integer $id The id of the row to check out.
     *
     * @return  boolean True on success, false on failure.
     *
     * @since   2.0
     */
    public function checkout($id = null) {
        // Get the user id.
        $id = (!empty($id)) ? $id : (int) $this->getState('booking.id');

        if ($id) {
            // Initialise the table
            $table = $this->getTable();

            // Get the current user object.
            $user = $this->getCurrentUser();

            // Attempt to check the row out.
            if (method_exists($table, 'checkout')) {
                if (!$table->checkout($user->get('id'), $id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param   array   $data     An optional array of data for the form to interogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  Form    A Form object on success, false on failure
     *
     * @since   2.0
     */
    public function getForm($data = array(), $loadData = true) {
        // Get the form.
        $form = $this->loadForm('com_ra_events.booking', 'bookingform', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
        $id = Factory::getApplication()->getUserState('com_ra_events.bookingform.id', 0);
        $event_id = Factory::getApplication()->getUserState('com_ra_events.bookingform.event_id', 0);
        $user_id = Factory::getApplication()->getUserState('com_ra_events.bookingform.user_id', 0);
        $callback = Factory::getApplication()->getUserState('com_ra_events.bookingform.callback', '');
//        $message = 'model: callback=' . $callback . '.event=' . $event_id . ', user=' . $user_id . ', id=' . $id;
//        Factory::getApplication()->enqueueMessage($message, 'info');
        if ($user_id == 0) {
//            Factory::getApplication()->enqueueMessage('Model: Selecting current user', 'info');
            $user_id = $this->getCurrentUser()->id;
            if ($user_id == 0) {
                Factory::getApplication()->enqueueMessage('User id not defined', 'error');
                return false;
            }
        }

        if ($this->getCurrentUser()->id == 0) {
            $form->setFieldAttribute('state', 'readonly', true);
        }
        $form->setFieldAttribute('event_id', 'default', $event_id);
        $form->setFieldAttribute('user_id', 'default', $user_id);
        if (($id > 0) OR ($callback == 'profiles')) {
            $form->removeField('terms');
//           Factory::getApplication()->enqueueMessage('Terms removed', 'info');
        }
        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT booking1, booking1_hint,booking2, booking2_hint FROM #__ra_events WHERE id=' . $event_id;
        $event = $toolsHelper->getItem($sql);
        // see https://manual.joomla.org/docs/general-concepts/forms/manipulating-forms/
        if ($event->booking1 == '') {
            $form->removeField('custom1');
        } else {
            $xml = $this->buildField(1, $event->booking1, $event->booking1_hint);
            $form->setField($xml);
        }
        if ($event->booking2 !== '') {
            $xml = $this->buildField(2, $event->booking2, $event->booking2_hint);
            $form->setField($xml);
        }
        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array  The default data is an empty array.
     * @since   2.0
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_events.edit.booking.data', array());

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {


            return $data;
        }

        return array();
    }

    /**
     * Method to save the form data.
     *
     * @param   array $data The form data
     *
     * @return  bool
     *
     * @throws  Exception
     * @since   2.0
     */
    public function save($data) {
        $id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('booking.id');
        $state = (!empty($data['state'])) ? 1 : 0;
//        var_dump($data);
//        $user = $this->getCurrentUser();
//        if ($user->id == 0) {
//            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
//        }
//        if ($id) {
//            // Check the user can edit this item
//            $authorised = $user->authorise('core.edit', 'com_ra_events') || $authorised = $user->authorise('core.edit.own', 'com_ra_events');
//        } else {
//            // Check the user can create new items in this section
//            $authorised = $user->authorise('core.create', 'com_ra_events');
//        }
//
//        if ($authorised !== true) {
//            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
//        }

        $table = $this->getTable();
        $toolsHelper = new ToolsHelper;
        $bookingHelper = New BookingHelper;
        if (empty($id)) {
            // creating a new record
            // See if we need to notify the organiser
            $sql = 'SELECT notify_organiser FROM #__ra_events WHERE id=' . $data['event_id'];         
            $notify_organiser = $toolsHelper->getValue($sql);
        } else {
            $table->load($id);
            $notify_organiser = '0';
        }
//       die('notify_organiser = ' . $notify_organiser);
        try {
            if ($table->save($data) === true) {
//                Factory::getApplication()->enqueueMessage('Model:  table id=' . $table->id, 'info');
                Factory::getApplication()->setUserState('com_ra_events.bookingform.id', $table->id);
                if ($notify_organiser == '1') {
                    $mode = 2;
                } else {
                    $mode = 1;
                }
                $bookingHelper->sendAcknowledgement($table->id, $mode);
//                Factory::getApplication()->enqueueMessage('Model:  send acknowledgement for booking id=' . $table->id, 'info');
                return $table->id;
            } else {
                Factory::getApplication()->enqueueMessage($table->getError(), 'error');
                return false;
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Method to delete data
     *
     * @param   int $pk Item primary key
     *
     * @return  int  The id of the deleted item
     *
     * @throws  Exception
     *
     * @since   2.0
     */
    public function delete($id) {
        $user = $this->getCurrentUser();

        if (empty($id)) {
            $id = (int) $this->getState('booking.id');
        }

        if ($id == 0 || $this->getItem($id) == null) {
            throw new \Exception(Text::_('COM_RA_EVENTS_ITEM_DOESNT_EXIST'), 404);
        }

        if ($user->authorise('core.delete', 'com_ra_events') !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $table = $this->getTable();

        if ($table->delete($id) !== true) {
            throw new \Exception(Text::_('JERROR_FAILED'), 501);
        }

        return $id;
    }

    /**
     * Check if data can be saved
     *
     * @return bool
     */
    public function getCanSave() {
        $table = $this->getTable();

        return $table !== false;
    }

    private function buildField($num, $label, $description) {
        $xml = new \SimpleXMLElement('<field name="custom' . $num . '" type="text" filter="string" label="' . $label . '" required="true" description="' . $description . '" />');
        return $xml;
    }

}
