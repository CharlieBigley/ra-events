<?php

/**
 * @version    2.1.7
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 03/05/25 CB correct cancel
 * 08/05/25 CB set up event_id in userstate
 * 26/07/25 Allow both Add and Update
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Booking class.
 *
 * @since  2.0
 */
class BookingformController extends FormController {

    /**
     * Method to abort current operation
     *
     * @return void
     *
     * @throws Exception
     */
    public function cancel($key = NULL) {

        // Get the current edit id.
        $editId = (int) $this->app->getUserState('com_ra_events.edit.booking.id');

        // Get the model.
        $model = $this->getModel('Bookingform', 'Site');

        // Check in the item
        if ($editId) {
            $model->checkin($editId);
        }
        if ($editId > 0) {
            $event_id = $this->lookupEvent($editId);
            $url = 'index.php?option=com_ra_events&task=booking.showBookings&event_id=' . $event_id;
        } else {
            // Parameters will have been saved in the Event view
            $id = Factory::getApplication()->getUserState('com_ra_events.event.id');
            $layout = Factory::getApplication()->getUserState('com_ra_events.event.layout');
            $menu_id = Factory::getApplication()->getUserState('com_ra_events.event.menu_id');
            $url = 'index.php?option=com_ra_events&view=event&id=' . $id;
            $url .= '&Itemid=' . $menu_id . '&layout=' . $layout;
        }
        $this->setRedirect(Route::_($url, false));
        $this->redirect();
    }

    /**
     * Method to check out an item for editing and redirect to the edit form.
     *
     * @return  void
     *
     * @since   2.0
     *
     * @throws  Exception
     */
    public function edit($key = NULL, $urlVar = NULL) {
        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $this->app->getUserState('com_ra_events.edit.booking.id');
        $editId = $this->input->getInt('id', 0);
        $event_id = $this->input->getInt('event_id', 0);

        // Set the user id for the user to edit in the session.
        $this->app->setUserState('com_ra_events.edit.booking.id', $editId);

        // Set the event id for use by the booking form
        $this->app->setUserState('com_ra_events.bookingform.event_id', $event_id);

        // Get the model.
        $model = $this->getModel('Bookingform', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=bookingform&layout=edit', false));
    }

    private function lookupEvent($booking_id) {
        $sql = 'SELECT event_id FROM #__ra_bookings WHERE id=' . $booking_id;
        $toolsHelper = new ToolsHelper;
        echo $sql;
//        die;
        return $toolsHelper->getValue($sql);
    }

    /**
     * Method to save data.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   2.0
     */
    public function save($key = NULL, $urlVar = NULL) {
        // Check for request forgeries.
        $this->checkToken();

        // Initialise variables.
        $model = $this->getModel('Bookingform', 'Site');

        // Get the user data.
        $data = $this->input->get('jform', array(), 'array');

        // Validate the posted data.
        $form = $model->getForm();

        if (!$form) {
            throw new \Exception($model->getError(), 500);
        }

        // Send an object which can be modified through the plugin event
        $objData = (object) $data;
        $this->app->triggerEvent(
                'onContentNormaliseRequestData',
                array($this->option . '.' . $this->context, $objData, $form)
        );

        $data = (array) $objData;

        // Validate the posted data.
        $data = $model->validate($form, $data);
        $error = false;
        if (($data["num_places"] == 2) AND ($data["partner"] == '')) {
            $this->app->enqueueMessage('Name of second person must be given', 'error');
            $error = true;
        }
        // Check for errors.
        if (($data === false) OR ($error == true)) {
            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof \Exception) {
                    $this->app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $this->app->enqueueMessage($errors[$i], 'warning');
                }
            }

            $jform = $this->input->get('jform', array(), 'ARRAY');

            // Save the data in the session.
            $this->app->setUserState('com_ra_events.edit.booking.data', $jform);

            // Redirect back to the edit screen.
            $id = (int) $this->app->getUserState('com_ra_events.edit.booking.id');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=bookingform&layout=edit&id=' . $id, false));

            $this->redirect();
        }
//        die('Saved');
        // Attempt to save the data.
        $return = $model->save($data);

        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $this->app->setUserState('com_ra_events.edit.booking.data', $data);

            // Redirect back to the edit screen.
            $id = (int) $this->app->getUserState('com_ra_events.edit.booking.id');
            $this->setMessage(Text::sprintf('Save failed', $model->getError()), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=bookingform&layout=edit&id=' . $id, false));
            $this->redirect();
        }

        // Check in the profile.
        if ($return) {
            $model->checkin($return);
        }

        // Clear the profile id from the session.
        $this->app->setUserState('com_ra_events.edit.booking.id', null);

        // Redirect to the list screen.
        if (!empty($return)) {
            $this->setMessage(Text::_('Booking updated'));
        }

//      event_id cannot be passed to the view directly, so in stored in the State
        $event_id = $this->app->getUserState('com_ra_events.bookingform.event_id', 0);
        if ($event_id == 0) {
            throw new \Exception("Can't find event number", 403);
        }
        $callback = Factory::getApplication()->getUserState('com_ra_events.event.callback');

        if ($callback == 'showBookings') {
            $menu = $this->app->getMenu();
//        $item = $menu->getActive();
//        var_dump($item);
            //       die;
            // $target = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
            $target = 'index.php?option=com_ra_events&Itemid=&task=booking.showBookings&event_id=' . $event_id;
            $target .= '&Itemid=' . $menu->id;
//        $this->app->enqueueMessage('Redirecting to ' . $target, 'info');
        } else {
            // Creating a new booking, parameters will have been saved in the Event view
            $id = Factory::getApplication()->getUserState('com_ra_events.event.id');
            $layout = Factory::getApplication()->getUserState('com_ra_events.event.layout');
            $menu_id = Factory::getApplication()->getUserState('com_ra_events.event.menu_id');
            $target = 'index.php?option=com_ra_events&view=event&id=' . $id;
            $target .= '&Itemid=' . $menu_id . '&layout=' . $layout;
        }
        $this->setRedirect(Route::_($target, false));

        // Flush the data from the session.
        $this->app->setUserState('com_ra_events.edit.booking.data', null);

        // Invoke the postSave method to allow for the child class to access the model.
        $this->postSaveHook($model, $data);
        $this->redirect();
    }

    /**
     * Method to remove data
     *
     * @return  void
     *
     * @throws  Exception
     *
     * @since   2.0
     */
    public function remove() {
        $model = $this->getModel('Bookingform', 'Site');
        $pk = $this->input->getInt('id');

        // Attempt to save the data
        try {
            // Check in before delete
            $return = $model->checkin($return);
            // Clear id from the session.
            $this->app->setUserState('com_ra_events.edit.booking.id', null);

            $menu = $this->app->getMenu();
            $item = $menu->getActive();
            $url = (empty($item->link) ? 'index.php?option=com_ra_events&view=bookings' : $item->link);

            if ($return) {
                $model->delete($pk);
                $this->setMessage(Text::_('COM_RA_EVENTS_ITEM_DELETED_SUCCESSFULLY'));
            } else {
                $this->setMessage(Text::_('COM_RA_EVENTS_ITEM_DELETED_UNSUCCESSFULLY'), 'warning');
            }


            $this->setRedirect(Route::_($url, false));
            // Flush the data from the session.
            $this->app->setUserState('com_ra_events.edit.booking.data', null);
        } catch (\Exception $e) {
            $errorType = ($e->getCode() == '404') ? 'error' : 'warning';
            $this->setMessage($e->getMessage(), $errorType);
            $this->setRedirect('index.php?option=com_ra_events&view=bookings');
        }
    }

    /**
     * Function that allows child controller access to model data
     * after the data has been saved.
     *
     * @param   BaseDatabaseModel  $model      The data model object.
     * @param   array              $validData  The validated data.
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function postSaveHook(BaseDatabaseModel $model, $validData = array()) {

    }

}
