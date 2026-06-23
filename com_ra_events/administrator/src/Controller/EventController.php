<?php

/**
 * @version    2.5.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 02/02/24 CB delete unwanted code
 * 23/10/25 CB implement delete
 * 09/03/26 CB show type of Event when deleting
 * 04/04/26 CB add forceSend function to allow immediate sending of mailshot
 * 08/04/26 CB delete any mailshots associated with an Event when it is deleted
 */

namespace Ramblers\Component\Ra_events\Administrator\Controller;

\defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Event controller class.
 *
 * @since  1.0.1
 */
class EventController extends FormController {

    protected $view_list = 'events';
    protected $toolsHelper;
    protected $eventsHelper;

    public function __construct(array $config = array(), \Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null) {
//        die('Mail_lstController');
        parent::__construct($config, $factory);
        $this->toolsHelper = new ToolsHelper;
        $this->eventsHelper = new EventsHelper;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function delete() {
        // Check for request forgeries
        $this->checkToken();
        $canDo = ContentHelper::getActions('com_ra_events');
        if (!$canDo->get('core.delete')) {
            throw new \Exception('Access not permitted', 401);
        }
        // Get items to remove from the request.
        $cid = (array) $this->input->get('cid', array(), 'int');
        // Remove zero values resulting from input filter
        $cid = array_filter($cid);
        if (empty($cid)) {
            $this->app->getLogger()->warning(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), array('category' => 'jerror'));
        } else {
            if (count($cid) > 1) {
                Factory::getApplication()->enqueueMessage('Events can only be deleted one at a time (' . count($cid) . ' selected)', 'error');
                $this->setRedirect(Route::_('index.php?option=com_ra_events&view=events'));
            } else {
                $event_id = $cid[0];
            }
        }

        if ($event_id == 0) {
            return;
        }
        $sql = 'SELECT e.state, e.event_date, e.title, t.description ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'LEFT JOIN #__ra_event_types AS t ON t.id = e.event_type_id ';
        $sql .= 'WHERE e.id=' . $event_id;

        $event = $this->toolsHelper->getItem($sql);
        $display = false;

        echo '<h2>Event ' . $event->event_date . '/' . $event->title . '</h2>';
        echo 'Type <b>' . $event->description . '</b><br>';
        echo 'Status <b>' . $event->state . '</b><br>';
        $sql = 'SELECT COUNT(*) ';
        $sql .= 'FROM  `#__ra_bookings` ';
        $sql .= 'WHERE event_id=' . $event_id;
        $count_bookings = $this->toolsHelper->getValue($sql);

        $sql = 'SELECT COUNT(*) ';
        $sql .= 'FROM  `#__ra_emails` ';
        $sql .= 'WHERE ref=' . $event_id;
        $count_emails = $this->toolsHelper->getValue($sql . ' AND state=1');

        $sql = 'SELECT COUNT(*) ';
        $sql .= 'FROM  `#__ra_mail_shots` ';
        $sql .= 'WHERE list_id=' . $event_id;
        $count = $this->toolsHelper->getValue($sql);
        echo '<li>' . $count . ' Mailshots</li>';

        if (($count_bookings > 0) OR ($count_emails > 0)) {
            echo 'There are other records present for this event:<br>';
            echo '<ul>';
            if ($count_bookings > 0) {
                echo '<li>Details of ' . $count_bookings . ' booking</li>';
            }
            if ($count_emails > 0) {
                echo '<li>Details of ' . $count_emails . ' emails</li>';
            }
            echo '</ul>';
            echo 'If you delete this Event, all these associated records will also be irrevocably lost.<br>';
        }

        $back = 'administrator/index.php?option=com_ra_events&view=events';
        echo $this->toolsHelper->buildButton($back,'Cancel', False, 'grey');
        $target = 'administrator/index.php?option=com_ra_events&task=event.purge&event_id=' . $event_id;
        echo $this->toolsHelper->buildButton($target, 'Confirm delete', False, 'red');
    }

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

    public function forceSend(){    
        $mailshot_id = Factory::getApplication()->input->getInt('id', 0);
        if ($mailshot_id == 0) {
            Factory::getApplication()->enqueueMessage('Mailshot ID is required', 'error');
            return;
        }
        $this->eventsHelper->sendEmails($mailshot_id,'Y');
        foreach ($this->eventsHelper->messages as $message) {
            Factory::getApplication()->enqueueMessage($message, 'info');    
   
        }   
//          Factory::getApplication()->enqueueMessage('Mailshot ' . $mailshot_id . ' has been sent', 'info');       
        $target = 'index.php?option=com_ra_events&view=events' ;
        $this->setRedirect(Route::_($target, false));
    }

    public function purge() {
        //       die('event/purge');
        $canDo = ContentHelper::getActions('com_ra_events');
        if (!$canDo->get('core.delete')) {
            throw new \Exception('Access not permitted', 401);
        }
        $event_id = Factory::getApplication()->input->getInt('event_id', 0);
        if ($event_id == 0) {
            return;
        }

        $sql = 'DELETE FROM `#__ra_bookings` ';
        $sql .= 'WHERE event_id=' . $event_id;
//        echo "$sql<br>";
        //       $this->toolsHelper->executeCommand($sql);
        //       $sql = 'DELETE FROM  `#__ra_import_reports` ';
        //       $sql .= 'WHERE list_id=' . $event_id;
        //       $this->objHelper->executeCommand($sql);

        $sql = 'DELETE FROM  `#__ra_events` ';
        $sql .= 'WHERE id=' . $event_id;
        $this->toolsHelper->executeCommand($sql);
        Factory::getApplication()->enqueueMessage('Event ' . $event_id . ' and any associated records have been deleted', 'info');
        $this->setRedirect('index.php?option=com_ra_events&view=events');
    }

}
