<?php

/**
 * @version    2.4.6
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 06/03/25 CB created from MailMan
 * 17/03/25 CB create confirmed booking
 * 07/07/25 CB stub for multiBook
 * 04/11/26 CB set id=0 for view bookingform
 * 04/02/26 CB Multibook: use sub-query to check for existing booking, and allow provisional booking to be confirmed
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * profiles list controller class.
 *
 * @since  1.0.3
 */
class ProfilesController extends FormController {

    protected $app;
    protected $view_item = 'event';
    protected $view_list = 'profiles';
    protected $bookingHelper;
    protected $toolsHelper;

    public function __construct() {
        parent::__construct();
        $this->app = Factory::getApplication();

        $this->toolsHelper = new ToolsHelper;
        $this->bookingHelper = new BookingHelper;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancel($key = null, $urlVar = null) {
//      event_id cannot be passed to the view directly, so it is stored in the State
        $event_id = $this->app->getUserState('com_ra_events.profiles.event_id', 0);
        $target = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
//        $target .= '&Itemid=' . $menu->id;
        $this->setRedirect($target);
    }

    public function cancelBooking() {
        $id = $this->app->input->getInt('id', '0');
        $user_id = $this->app->input->getInt('user_id', '0');
        $event_id = $this->app->input->getInt('event_id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');

        $this->bookingHelper->cancelBooking($id, $user_id);

        $target = 'index.php?option=com_ra_events&view=profiles&event_id=';
        $target .= $event_id . '&Itemid=' . $menu_id;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    public function confirmBooking() {
        $id = $this->app->input->getInt('id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
//        die('user_id=' . $user_id);
        $this->bookingHelper->confirmBooking($id);

        $target = 'index.php?option=com_ra_events&view=profiles&event_id=';
        $target .= $event_id . '&Itemid=' . $menu_id;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    public function createBooking() {
        // invoked from view profiles
        $current_userid = $this->app->getSession()->get('user')->id;
        if ($current_userid == 0) {
            throw new \Exception('You must be logged in to make a booking', 403);
        }
        $event_id = $this->app->input->getInt('event_id', '0');
        $user_id = $this->app->input->getInt('user_id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        // Validate input
        $sql = 'SELECT bookable, contact_id, booking1,booking2 FROM #__ra_events WHERE id=' . $event_id;
        $item = $this->toolsHelper->getItem($sql);
        if ($item->bookable == 0) {
            throw new \Exception('This event cannot be booked', 403);
        }
        if (($item->booking1 == '') AND ($item->booking2 == '')) {
            // No custom fields
            $booking_id = $this->bookingHelper->createBooking($event_id, $user_id);
            $this->bookingHelper->confirmBooking($booking_id);
            // redirect to display form
            $target = 'index.php?option=com_ra_events&view=profiles&event_id=';
            $target .= $event_id . '&Itemid=' . $menu_id;
            $this->setRedirect(Route::_($target, false));
            $this->redirect();
        } else {
            // Invoke the edit form
            // event_id cannot be passed to the view directly, so it stored in the State
            $this->app->setUserState('com_ra_events.bookingform.id', 0);
            $this->app->setUserState('com_ra_events.bookingform.event_id', $event_id);
            $this->app->setUserState('com_ra_events.bookingform.user_id', $user_id);
            $this->app->setUserState('com_ra_events.bookingform.callback', 'profiles');
            $target = 'index.php?option=com_ra_events&view=bookingform&id=0';
        }
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    Optional. Model name
     * @param   string  $prefix  Optional. Class prefix
     * @param   array   $config  Optional. Configuration array for model
     *
     * @return  object	The Model
     *
     * @since   1.0.3
     */
    public function getModel($name = 'Userselect', $prefix = 'Administrator', $config = array()) {
        return parent::getModel($name, $prefix, array('ignore_request' => true));
    }

    public function book() {
        // 16/10/25: This function never seems to be invoked
        $this->app->enqueueMessage('book', 'info');
        // Get the input
        $input = Factory::getApplication()->input;
        $primary_keys = $input->post->get('cid', array(), 'array');
        // Sanitize the input
        ArrayHelper::toInteger($primary_keys);
        // Retrieve the event id from the globals
        $event_id = $this->app->getUserState('com_ra_events.profiles.event_id', 0);

        echo 'Event id= ' . $event_id . '<br>';
        $toolsHelper = new ToolsHelper;
        $bookingHelper = new BookingHelper;
        foreach ($primary_keys AS $user_id) {
            $sql = 'SELECT b.id, b.state, p.preferred_name FROM #__ra_profiles AS p ';
            $sql .= 'LEFT JOIN  #__ra_bookings AS b ON b.user_id = p.id ';
            $sql .= 'WHERE b.user_id=' . $user_id;
            $sql .= ' AND b.event_id=' . $event_id;
            $item = $toolsHelper->getItem($sql);
            if (is_null($item)) {
                $new = $bookingHelper->lookupUsername($user_id);
                $booking_id = $bookingHelper->createBooking($event_id, $user_id);
                $bookingHelper->confirmBooking($booking_id);
                $message = $new . ' has been booked';
                $this->app->enqueueMessage($message, 'info');
            } elseif ($item->state == 0) {
                $bookingHelper->confirmBooking($item->id);
                $message = $item->preferred_name . ' has been confirmed';
                $this->app->enqueueMessage($message, 'info');
            } elseif ($item->state == 1) {
                $message = $item->preferred_name . ' is already booked';
                $this->app->enqueueMessage($message, 'error');
            } elseif ($item->state == -2) {
                $bookingHelper->confirmBooking($item->id);
                $message = $item->preferred_name . ' has been reinstated';
                $this->app->enqueueMessage($message, 'info');
            }
            echo $message . '<br>';
        }
//        die;
        $this->setRedirect('index.php?option=com_ra_events&view=profiles&list_id=' . $list_id);
    }

    public function multiBook() {
          // Retrieve the event id from the globals
        $event_id = $this->app->getUserState('com_ra_events.profiles.event_id', 0);

//        echo 'Event id= ' . $event_id . '<br>';      
          $primary_keys = $this->input->post->get('cid', array(), 'array');
//          var_dump($primary_keys);
          foreach ($primary_keys as $id) {
                      $this->multiBookEntry($event_id,$id);     
          }

        $target = 'index.php?option=com_ra_events&view=profiles&event_id=';
        $target .= $event_id . '&Itemid=' . $menu_id;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    private function multiBookEntry($event_id,$id) {
//        $this->app->enqueueMessage('id=' . $id, 'info');
        $sql = 'SELECT b.id, b.state, p.preferred_name FROM #__ra_profiles AS p ';
        $sql .= 'LEFT JOIN  #__ra_bookings AS b ON b.user_id = p.id ';
        $sql .= 'WHERE b.user_id=' . $id;
        $sql .= ' AND b.event_id=' . $event_id;
        $item = $this->toolsHelper->getItem($sql);
        if (is_null($item)) {
            $new = $this->bookingHelper->lookupUsername($id);
            $booking_id = $this->bookingHelper->createBooking($event_id, $id);
            $this->bookingHelper->confirmBooking($booking_id);
            $message = $new . ' has been booked';
            $this->app->enqueueMessage($message, 'info');
        } elseif ($item->state == 0) {
//               $bookingHelper->confirmBooking($item->id);
            $message = $item->preferred_name . ' already has a provisional booking';
            $this->app->enqueueMessage($message, 'info');
        } elseif ($item->state == 1) {
            $message = $item->preferred_name . ' already has confirmed booking';
            $this->app->enqueueMessage($message, 'error');
        } elseif ($item->state == -2) {
//                $bookingHelper->confirmBooking($item->id);
            $message = $item->preferred_name . ' has been cancelled';
            $this->app->enqueueMessage($message, 'info');
        }
        echo $message . '<br>';
    }    
}
