<?php

/**
 * @version    2.1.3
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 17/03/25 CB remove function to delete a profile
 * 24/03/25 CB return to main menu after cancelling a new user
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use \Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;

/**
 * Profile class.
 *
 * @since  2.0
 */
class ProfileformController extends FormController {

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
        $previousId = (int) $this->app->getUserState('com_ra_events.edit.profile.id');
        $editId = $this->input->getInt('id', 0);
        $user = Factory::getApplication()->getSession()->get('user');
        if (!is_null($user->id)) {
            $canDo = ContentHelper::getActions('com_ra_events');
            if (!$canDo->get('core.create')) {
                Factory::getApplication()->enqueueMessage('Sorry, you don\'t have permission to create new Users', 'error');
                $this->setRedirect(Route::_('index.php?option=com_ra_events&view=events', false));
                return;
            }
        }
// Set the user id for the user to edit in the session.
        $this->app->setUserState('com_ra_events.edit.profile.id', $editId);

// Get the model.
        $model = $this->getModel('Profileform', 'Site');

// Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

// Check in the previous user.
        if ($previousId) {
            $model->checkin($previousId);
        }

// Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=profileform&layout=edit', false));
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
        $model = $this->getModel('Profileform', 'Site');

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

// See if the specified email address is already in use
        $helper = New BookingHelper;
        if ($helper->validateEmail($data['id'], $data['email']) == false) {
            $error = true;
        }

        $data = (array) $objData;
//        echo 'Controller<br>';
//        var_dump($data);
//        echo '<br>';
        $error = false;

// Validate the posted data.
        $data = $model->validate($form, $data);

// Check for errors.
        if (($data === false) OR ($error == true)) {
// Get the validation messages.
            $errors = $model->getErrors();

// Push up to three validation messages out to the user.
            for ($i = 0, $n = count($errors);
                    $i < $n && $i < 3;
                    $i++) {
                if ($errors[$i] instanceof \Exception) {
                    $this->app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $this->app->enqueueMessage($errors[$i], 'warning');
                }
            }

            $jform = $this->input->get('jform', array(), 'ARRAY');

// Save the data in the session.
            $this->app->setUserState('com_ra_events.edit.profile.data', $jform);

// Redirect back to the edit screen.
            $id = (int) $this->app->getUserState('com_ra_events.edit.profile.id');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=profileform&layout=edit&id=' . $id, false));

            $this->redirect();
        }

// Attempt to save the data.
        $return = $model->save($data);

// Check for errors.
        if ($return === false) {
// Save the data in the session.
            $this->app->setUserState('com_ra_events.edit.profile.data', $data);

// Redirect back to the edit screen.
            $id = (int) $this->app->getUserState('com_ra_events.edit.profile.id');
            $this->setMessage(Text::sprintf('Save failed', $model->getError()), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=profileform&layout=edit&id=' . $id, false));
            $this->redirect();
        }

// Check in the profile.
        if ($return) {
            $model->checkin($return);
        }

// Clear the profile id from the session.
        $this->app->setUserState('com_ra_events.edit.profile.id', null);

// Redirect to the list screen.
        if (!empty($return)) {
            $this->app->enqueueMessage('Profile created successfully', 'info');
        }

        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        $url = (empty($item->link) ? 'index.php?option=com_ra_events&view=profiles' : $item->link);
        $this->setRedirect(Route::_($url, false));

// Flush the data from the session.
        $this->app->setUserState('com_ra_events.edit.profile.data', null);

// Invoke the postSave method to allow for the child class to access the model.
        $this->postSaveHook($model, $data);
    }

    /**
     * Method to abort current operation
     *
     * @return void
     *
     * @throws Exception
     */
    public function cancel($key = NULL) {
// Get the current edit id.
        $editId = (int) $this->app->getUserState('com_ra_events.edit.profile.id');

// Get the model.
        $model = $this->getModel('Profileform', 'Site');

// Check in the item
        if ($editId) {
            $model->checkin($editId);
        }

//        $menu = Factory::getApplication()->getMenu();
//        $item = $menu->getActive();
//        $url = (empty($item->link) ? 'index.php?option=com_ra_events&view=profiles' : $item->link);
//        $this->setRedirect(Route::_($url, false));

        $this->setRedirect('index.php');
        $this->redirect();
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
