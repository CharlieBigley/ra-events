<?php

/**
 * @version    1.2.6
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 02/02/24 CB delete unwanted code
 */

namespace Ramblers\Component\Ra_events\Administrator\Controller;

\defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Event controller class.
 *
 * @since  1.0.1
 */
class EventController extends FormController {

    protected $view_list = 'events';

    /**
     * Method to check out an item for editing and redirect to the edit form.
     *
     * @return  void
     *
     * @since   1.0.2
     *
     * @throws  Exception
     */
    public function edit($key = NULL, $urlVar = NULL) {
        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $this->app->getUserState('com_ra_events.edit.event.id');
        $editId = $this->input->getInt('id', 0);

        // Set the user id for the user to edit in the session.
        $this->app->setUserState('com_ra_events.edit.event.id', $editId);

        // see if editing Agenda/Reports/Minutes
        $mode = Factory::getApplication()->input->getWord('mode', '');
        // Set this mode for use in the model to determine which form to use.
        $this->app->setUserState('com_ra_events.edit.event.mode', $mode);

        // Get the model.
        $model = $this->getModel('Event', 'Administrator');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $target = 'index.php?option=com_ra_events&view=event&layout=edit&id=' . $editId;
        $this->setRedirect(Route::_($target, false));
    }

}
