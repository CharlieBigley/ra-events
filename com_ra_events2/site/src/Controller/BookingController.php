<?php

/**
 * @version    2.1.9
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 05/03/25 CB Created
 * 03/05/25 CB show counts
 * 26/07/25 CB add callback when editing
 * 06/08/24 CB show user_id if preferred name is not present
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

//use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use \Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Booking class.
 *
 * @since  4.1.0
 */
class BookingController extends FormController {

    protected $app;
    protected $id;
    protected $table;
    protected $toolsHelper;
    protected $bookingHelper;

//    protected $params;

    public function __construct() {
        parent::__construct();
        $this->app = Factory::getApplication();
        $this->id = $this->app->input->getInt('id', '0');
        $this->table = Factory::getApplication()->bootComponent('com_ra_events')->getMVCFactory()->createTable('Bookings', 'Administrator');
        if ($id > 0) {
            $this->table->load($id);
        }
        $this->toolsHelper = new ToolsHelper;
        $this->bookingHelper = new BookingHelper;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancelBooking() {
        $id = $this->app->input->getInt('id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        $event_id = $this->app->input->getInt('event_id', '0');

        $this->bookingHelper->cancelBooking($id, $user_id);

        $target = 'index.php?option=com_ra_events&task=booking.showBookings&event_id=';
        $target .= $event_id . '&Itemid=' . $menu_id;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    public function confirmBooking() {
        $id = $this->app->input->getInt('id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        $event_id = $this->app->input->getInt('event_id', '0');
        $this->bookingHelper->confirmBooking($id, $user_id);

        $target = 'index.php?option=com_ra_events&task=booking.showBookings&event_id=';
        $target .= $event_id . '&Itemid=' . $menu_id;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    public function createBooking() {
        // invoked from tmpl/event/book
        $current_userid = Factory::getApplication()->getSession()->get('user')->id;
        if ($current_userid == 0) {
            throw new \Exception('You must be logged in to make a booking', 403);
        }
        $event_id = $this->app->input->getInt('event_id', '0');
        $user_id = $this->app->input->getInt('user_id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        $current_userid = $this->app->getSession()->get('user')->id;
        // Validate input
        $sql = 'SELECT bookable, contact_id FROM #__ra_events WHERE id=' . $event_id;
        $item = $this->toolsHelper->getItem($sql);
        if ($item->bookable == 0) {
            throw new \Exception('This event cannot be booked', 403);
        }
        $booking_id = $this->bookingHelper->createBooking($event_id, $user_id);
        $this->bookingHelper->confirmBooking($booking_id, $user_id);

        // redirect to display form
        $target = 'index.php?option=com_ra_events&view=event&id=' . $event_id . '&Itemid=' . $menu_id;
        $this->redirect($target);
    }

    public function makeBooking() {
        $id = $this->app->input->getInt('id', '0');
        $event_id = $this->app->input->getInt('event_id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        // event_id cannot be passed to the view directly, so in stored in the State
        $this->app->setUserState('com_ra_events.bookingform.event_id', $event_id);

        // redirect to selection form
        $target = 'index.php?option=com_ra_events&view=bookingform&Itemid=' . $menu_id;
        $target .= '&event_id=' . $event_id . '&id=' . $id;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    public function notifyOrganiser($event_id, $user_id) {

        // Send a message to the event organiser
        // get name of booker
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . $user_id;
//       echo "$sql<br>";
        $new = $this->toolsHelper->getValue($sql);
        $sql = 'SELECT e.title, u.email ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id=b.event_id ';
        $sql .= 'INNER JOIN #__contact_details AS c ON c.id=e.contact_id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id=c.user_id ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $event = $this->toolsHelper->getItem($sql);

        $sql = 'SELECT b.created, p.preferred_name ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id=b.created_by ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id=b.event_id ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $item = $this->toolsHelper->getItem($sql);

        $title = 'New booking for ' . $event->title;
        $body = $new . ' made a booking at ' . HTMLHelper::_('date', $item->created, 'H:i');
        $body .= ' on ' . HTMLHelper::_('date', $item->created, 'd M yy');
        $body .= ' by ' . $item->preferred_name . '<br>';

        $body .= 'The list of bookings is now:<br>';

        $sql = 'SELECT p.preferred_name, s.title ';
        $sql .= 'FROM #__ra_profiles AS p ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.user_id=p.id ';
        $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' ORDER BY p.preferred_name';
        $rows = $this->toolsHelper->getRows($sql);
        $provisional = 0;
        foreach ($rows as $row) {
            if ($row->state == 0) {
                $provisional++;
            }
            $body .= $row->preferred_name . ', ' . $row->title . '<br>';
        }
        if ($provisional > 0) {
            $body .= 'Logon to confirm ' . $provisional . ' bookings<br>';
        }
        echo 'emailing to ' . $event->email . '<br>';
//        return;
        // send the email
        $this->toolsHelper->sendEmail($event->email, $event->email, $title, $body);
    }

    public function selectUsers() {
        $event_id = $this->app->input->getInt('event_id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        // event_id cannot be passed to the view directly, so in stored in the State
        $this->app->setUserState('com_ra_events.profiles.event_id', $event_id);

        // redirect to selection form
        $target = 'index.php?option=com_ra_events&view=profiles&Itemid=' . $menu_id;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    public function showBookings() {

        $event_id = $this->app->input->getInt('event_id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        $print = $this->app->input->getWord('print', 'N');
        // Set up callback so after editing a booking, control passes back to here
        $this->app->setUserState('com_ra_events.event.callback', 'showBookings');
        $sql = 'SELECT e.title, c.user_id FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'WHERE e.id=' . $event_id;
        $item = $this->toolsHelper->getItem($sql);
        $title = $item->title;
        echo '<h2>Bookings for ' . $title . '</h2>';
        $target = 'index.php?option=com_ra_events&task=booking.ShowBookings&print=Y&tmpl=component';
        $target .= '&event_id=' . $event_id . '&Itemid=' . $menu_id;

        echo $this->toolsHelper->showPrint($target);
        $canEdit = false;
        if ($print == 'N') {
            $canDo = ContentHelper::getActions('com_ra_events');
            if ($canDo->get('core.edit')) {
                $canEdit = true;
            } else {
                $current_user = Factory::getApplication()->getSession()->get('user')->id;
                if ($item->user_id == $current_user) {
                    $canEdit = true;
                }
            }
        }
//        if ($current->user->id !== $this->item->contact_id) {
//            throw new \Exception('This function only available to the event organiser', 403);
//        }
        $table = new ToolsTable;
        $header = 'Status, Name, Places, Other, Booked';
        if ($canEdit) {
            $header .= ', Action';
        }
        $table->add_header($header);

        $sql = 'SELECT b.id, b.event_id, b.state, b.created, b.num_places, b.partner, ';
        $sql .= 'p.preferred_name, s.title, b.user_id ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id  ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = b.user_id  ';
        $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state  ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' ORDER BY s.seq, p.preferred_name';

        //$target_edit = 'index.php?option=com_ra_events&task=booking.makeBooking&Itemid=' . $menu_id;
        $target_edit = 'index.php?option=com_ra_events&task=bookingform.edit&Itemid=' . $menu_id;
        $target_edit .= '&callback=showBookings';
        $rows = $this->toolsHelper->getRows($sql);
        $count_bookings = 0;
        $count_places = 0;
        foreach ($rows as $row) {
            if ($row->state == 1) {
                $count_bookings++;
                $count_places = $count_places + $row->num_places;
            }
            //$table->add_item($row->title);
            $table->add_item(BookingHelper::showState($row->state));
            if ($row->preferred_name == '') {
                $table->add_item('<b>User ' . $row->user_id . '</b>');
                $message = 'Please check Backend>RA Dashboard>MailMan Reports>Contacts Report for user ' . $row->user_id;
                $this->app->enqueueMessage($message, 'warning');
            } else {
                $table->add_item($row->preferred_name);
            }
            $table->add_item($row->num_places);
            $table->add_item($row->partner);
            $table->add_item(HTMLHelper::_('date', $row->created, 'd M y H:i'));
            if ($canEdit) {
                $target = $target_edit . '&event_id=' . $row->event_id . '&id=' . $row->id;
                $actions = $this->toolsHelper->buildButton($target, 'Edit', False, 'sunset');
                $table->add_item($actions);
            }
            $table->generate_line();
        }
        $table->generate_table();
        echo $count_bookings . ' confirmed Bookings, ' . $count_places . ' confirmed Places<br>';

        $back = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
        $back .= '&Itemid=' . $menu_id;
        echo $this->toolsHelper->backButton($back);
    }

    public function test() {
        $helper = new BookingHelper;

//        $table = Factory::getApplication()->bootComponent('com_ra_events')->getMVCFactory()->createTable('Profile', 'Administrator');
//        $table->home_group = 'ns01';
//        $table->real_name = 'Robin Bigley';
//        $table->user_email = 'Robin@Bigley.me.uk';
//        $table->store();
//        return;

        $file = 'StoneCommittee.csv';
        $filename = 'images/com_ra_events/' . $file;
        if (file_exists($filename)) {
            echo $filename . "  found";
        } else {
            echo $filename . " not found";
        }
        echo '<br>';
        $filename = '/images/com_ra_events/' . $file;
        if (file_exists($filename)) {
            echo $filename . "  found";
        } else {
            echo $filename . " not found";
        }
        return;
// index.php?option=com_ra_events&task=booking.createBooking&user_id=994&event_id=4
        $id = 1;
//        $this->table = Factory::getTable('#__ra_bookings', 'Administrator');
        if ($id > 0) {
            $this->table->load($id);
        }
        $this->confirmBooking();
//$this->cancel();


        return;

//
//        $this->toolsHelper->executeCommand($sql);
        return;
// index.php?option=com_ra_events&task=booking.createBooking&user_id=994&event_id=4
        $id = 1;
//        $this->table = Factory::getTable('#__ra_bookings', 'Administrator');
        if ($id > 0) {
            $this->table->load($id);
        }
        $this->confirmBooking();
    }

}
