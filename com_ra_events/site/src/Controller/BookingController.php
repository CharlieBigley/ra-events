<?php

/**
 * @version    2.4.15
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 05/03/25 CB Created
 * 03/05/25 CB show counts
 * 26/07/25 CB add callback when editing
 * 06/08/24 CB show user_id if preferred name is not present
 * 09/09/25 CB deleted function notifyOrganiser
 * 01/10/25 CB accept bookings from email
 * 12/10/25 CB show provisional bookings in Summaries
 * 17/10/25 CB when making a booking from email, set callback
 * 05/11/25 CB comment out confirmBooking, does not seem to be used, prepare for booking_date
 * 11/11/25 CB special requests
 * 13/11/25 CB ignore blank special requests
 * 22/02/26 CB ensure user is logged in showBookings, disallow selection of users for past events
 *             resend confirmation email
 * 02/03/26 CB Commented out call to bookingHelper->createBooking
 * 16/06/26 CB correction for showBookings
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
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
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
    protected $current_user_id;
    protected $id;
    protected $table;
    protected $toolsHelper;
    protected $bookingHelper;

//    protected $params;

    public function __construct() {
        parent::__construct();
        $this->app = Factory::getApplication();
        $this->id = $this->app->input->getInt('id', '0');
        $this->current_user_id = Factory::getApplication()->getSession()->get('user')->id;
        $this->table = Factory::getApplication()->bootComponent('com_ra_events')->getMVCFactory()->createTable('Bookings', 'Administrator');
        if ($this->id > 0) {
            $this->table->load($this->id);
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

//    public function confirmBooking() {
//        $id = $this->app->input->getInt('id', '0');
//        $menu_id = $this->app->input->getInt('Itemid', '0');
//        $event_id = $this->app->input->getInt('event_id', '0');
//        $user_id = $this->app->input->getInt('user_id', '0');
//        $this->bookingHelper->confirmBooking($id, $user_id);
//
//        $target = 'index.php?option=com_ra_events&task=booking.showBookings&event_id=';
//        $target .= $event_id . '&Itemid=' . $menu_id;
//        $this->setRedirect(Route::_($target, false));
//        $this->redirect();
//    }

    public function createBooking() {
// invoked from tmpl/event/book
        $current_userid = Factory::getApplication()->getSession()->get('user')->id;
        if ($current_userid == 0) {
            throw new \Exception('You must be logged in to make a booking', 403);
        }
        $event_id = $this->app->input->getInt('event_id', '0');
        $user_id = $this->app->input->getInt('user_id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
// Validate input
        $sql = 'SELECT bookable, contact_id FROM #__ra_events WHERE id=' . $event_id;
        $item = $this->toolsHelper->getItem($sql);
        if ($item->bookable == 0) {
            throw new \Exception('This event cannot be booked', 403);
        }
// Commented out 02/03/26
//        $booking_id = $this->bookingHelper->createBooking($event_id, $user_id);
//        $this->bookingHelper->confirmBooking($booking_id);

// redirect to display form
        $target = 'index.php?option=com_ra_events&view=event&id=' . $event_id . '&Itemid=' . $menu_id;
        $this->redirect($target);
    }

    private function lookupBooking($event_id, $user_id) {
        $sql = 'SELECT id FROM #__ra_bookings WHERE event_id=' . $event_id;
        $sql .= ' AND user_id=' . $user_id;
        $id = $this->toolsHelper->getValue($sql);
        if (is_null($id)) {
            return 0;
        } else {
            return $id;
        }
    }

    public function makeBooking() {
        /*
         * This is invoked from four places:
         * From processEmail (following a link embedded into an email)]
         * From the display of a single booking (via BookingHelper->showBookings)
         * From the display of existing bookings (task=booking.showBookings)
         * From the view profiles (when selecting users)
         */
        $menu_id = $this->app->input->getInt('Itemid', '0');
        $token = $this->app->input->getCmd('token', '');
        if ($token == '') {
            if (Factory::getApplication()->getIdentity()->id == 0) {
                throw new \Exception('You must be logged on to make or edit a booking', 403);
            }
            $id = $this->app->input->getInt('id', '0');
            $event_id = $this->app->input->getInt('event_id', '0');
            $user_id = $this->app->input->getInt('user_id', '0');
            $callback = $this->app->input->getWord('callback', '');
        } else {
            echo "makeBooking: token is $token<br>";
            $bookingHelper = new BookingHelper;
            $bookingHelper->decode($token, $date, $mode, $user_id, $event_id, true);
            $message = 'makeBooking: event=' . $event_id . ', user=' . $user_id;

//            echo "event_id=$event_id<br>";
//            echo "user_id=$user_id<br>";
//            echo "date=$date<br>";
//            echo "mode=$mode<br>";
            $id = $this->lookupBooking($event_id, $user_id);
            $message .= ', id=' . $id;
            Factory::getApplication()->enqueueMessage($message, 'info');
            $callback = 'email';
        }
//        Factory::getApplication()->enqueueMessage('id=' . $id, 'info');
// event_id cannot be passed to the view directly, so it is stored in the State
        $this->app->setUserState('com_ra_events.bookingform.id', $id);
        $this->app->setUserState('com_ra_events.bookingform.event_id', $event_id);
        $this->app->setUserState('com_ra_events.bookingform.user_id', $user_id);
        $this->app->setUserState('com_ra_events.bookingform.callback', $callback);

// redirect to edit form
        $target = 'index.php?option=com_ra_events&view=bookingform&Itemid=' . $menu_id;
        $target .= '&event_id=' . $event_id . '&id=' . $id . '&token=' . $token;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    public function processEmail() {
        /*
         * This will be invoked by the user clicking on a link from an email
         * they have been sent by a batch job containing a token
         *
         */
        $objApp = Factory::getApplication();
        $token = $objApp->input->getCmd('token', '');
//        echo "Controller: token is $token<br>";
        $bookingHelper = new BookingHelper;
// For diagnostics, set final parameter to True
        if ($bookingHelper->decode($token, $date, $mode, $user_id, $event_id, false) === false) {
            $message = "Sorry, this seems be be in invalid token";
            Factory::getApplication()->enqueueMessage($message, 'error');
            $this->setRedirect('index.php');
            return;
        }

// Check that the Event is still valid
        $sql = 'SELECT e.id, e.title, e.state, ';
        $sql .= 'DATEDIFF(CURRENT_DATE,e.event_date) as days_to_go, ';
        $sql .= 'publication_date, DATEDIFF(e.publication_date,CURRENT_DATE) as publish_to_go ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'WHERE e.id=' . $event_id;
        $item = $this->toolsHelper->getItem($sql);
        if (is_null($item)) {
            $message = 'Event ' . $event_id . ' not found';
            Factory::getApplication()->enqueueMessage($message, 'error');
            $this->setRedirect('index.php');
            return;
        }
        if ($item->days_to_go > 0) {
            $message = 'Event ' . $item->title . ' was ' . $item->days_to_go . ' days ago';
            Factory::getApplication()->enqueueMessage($message, 'error');
            $this->setRedirect('index.php');
            return;
        }
// Check that the Publication date has been reached
        if ($item->publish_to_go > 0) {
            $message = 'Event ' . $item->title . ': Booking not accepted until ' . HTMLHelper::_('date', $item->publication_date, 'd M y');
            $message .= ' (' . $item->publish_to_go . ' days to go)';
            Factory::getApplication()->enqueueMessage($message, 'error');
            $this->setRedirect('index.php');
            return;
        }
        if ($item->state < 1) {
            $message = 'Event ' . $item->title . ' is no longer valid: state= ' . $item->state;
            Factory::getApplication()->enqueueMessage($message, 'error');
            $this->setRedirect('index.php');
            return;
        }
// Check that the User is still valid
        $sql = 'SELECT u.name, u.block, p.preferred_name ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = u.id ';
        $sql .= 'WHERE u.id=' . $user_id;
        $user = $this->toolsHelper->getItem($sql);
        if (is_null($user)) {
            $message = 'User ' . $user_id . ' not found';
            Factory::getApplication()->enqueueMessage($message, 'error');
            $this->setRedirect('index.php');
            return;
        }
        if (is_null($user->preferred_name)) {
            $message = 'Preferred name not found for ' . $user->name . ' (' . $user_id . ')';
            Factory::getApplication()->enqueueMessage($message, 'error');
            $this->setRedirect('index.php');
            return;
        }
        if ($user->block == 1) {
            $message = 'Sorry ' . $user->preferred_name . ', you  have been blocked from logging on. ';
            $message .= 'Please contact the membership secretary.';
            Factory::getApplication()->enqueueMessage($message, 'error');
            $this->setRedirect('index.php');
            return;
        }
        $id = $this->lookupBooking($event_id, $user_id);
        if ($id > 0) {
            $message .= 'You are already booked onto this Event';
            Factory::getApplication()->enqueueMessage($message, 'warning');
            $target = 'index.php?option=com_ra_events&task=booking.showBooking&id=' . $id;
        } else {
            $target = 'index.php?option=com_ra_events&task=booking.makeBooking&token=' . $token;
            $target .= '&event_id' . $event_id;
            $target .= 'callback=email';
        }
        $this->setRedirect($target);
    }

    public function resendConfirmation() {
        $id = $this->app->input->getInt('id', '0');
        $event_id = $this->app->input->getInt('event_id', '0');
        $menu_id= $this->app->input->getInt('Itemid', '0');
        if (($id == 0) || ($event_id == 0)) {
            Factory::getApplication()->enqueueMessage('Invalid booking id or event id', 'error');
        } else {
            $this->bookingHelper->sendAcknowledgement($id,1);
            Factory::getApplication()->enqueueMessage('Confirmation resent', 'info');
        }
        $target = 'index.php?option=com_ra_events&task=booking.showBookings&event_id=' . $event_id;
        $target .= '&Itemid=' . $menu_id;
        $this->setRedirect($target);
    }

    public function selectUsers() {
        $event_id = $this->app->input->getInt('event_id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        
        // Check if event is in the future
        $sql = 'SELECT event_date FROM #__ra_events WHERE id=' . $event_id;
        $event = $this->toolsHelper->getItem($sql);
        if (!is_null($event)) {
            $event_date = new \DateTime($event->event_date);
            $today = new \DateTime('today');
            if ($event_date < $today) {
                Factory::getApplication()->enqueueMessage('Cannot select users for past events', 'error');
                $target = 'index.php?option=com_ra_events&task=booking.showBookings&event_id=' . $event_id;
                $target .= '&Itemid=' . $menu_id;
                $this->setRedirect(Route::_($target, false));
                $this->redirect();
                return;
            }
        }
        
// event_id cannot be passed to the view directly, so it is stored in the State
        $this->app->setUserState('com_ra_events.profiles.event_id', $event_id);
        $this->app->setUserState('com_ra_events.profiles.menu_id', $menu_id);
// redirect to selection form
        $target = 'index.php?option=com_ra_events&view=profiles&Itemid=' . $menu_id;
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    public function showBooking() {
        $booking_id = $this->app->input->getInt('id', '0');
        $bookingHelper = new BookingHelper;
        $sql = 'SELECT b.event_id, p.preferred_name ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = b.user_id ';
        $sql .= 'WHERE b.id=' . $booking_id;
        $item = $this->toolsHelper->getItem($sql);
        if (is_null($item)) {
            echo 'No details found for booking ' . $booking_id . '<br>';
        } else {
            echo 'Hi ' . $item->preferred_name . '<br>';
            echo $bookingHelper->getBookingDetails($booking_id);
            $target = 'index.php?option=com_ra_events&view=event&id=' . $item->event_id;
            echo $this->toolsHelper->buildLink($target, 'Show Event') . '<br>';
        }

        $target = 'index.php?option=com_ra_events&view=events';
        echo $this->toolsHelper->buildLink($target, 'Show All Events');
    }

    public function showBookings() {
        // Check User is logged in
        if ($this->current_user_id == 0) {
            $message = 'You must be logged in to view bookings';
            Factory::getApplication()->enqueueMessage($message, 'warning');
             $this->setRedirect('index.php');
             $this->redirect();
             return;
        }  
        $event_id = $this->app->input->getInt('event_id', '0');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        $print = $this->app->input->getWord('print', 'N');
// Set up callback so after editing a booking, control passes back to here
        $this->app->setUserState('com_ra_events.event.callback', 'showBookings');
        $sql = 'SELECT e.id, e.title, e.booking1, e.booking2, e.event_date, c.user_id ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'WHERE e.id=' . $event_id;
        $item = $this->toolsHelper->getItem($sql);
        $title = $item->title;
        
        // Check if event is in the future
        $event_date = new \DateTime($item->event_date);
        $today = new \DateTime('today');
        $is_future_event = ($event_date >= $today);
        echo '<h2>Bookings for ' . $title . '</h2>';

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
		$target_email = 'index.php?option=com_ra_tools&task=system.eventAttendees&id=';
        $target_resend = 'index.php?option=com_ra_events&task=booking.resendConfirmation&id=';

        $table = new ToolsTable;
        $header = 'Status, Name, Places, Other ';
        if ($item->booking1 !== '') {
            $header .= ',' . $item->booking1;
        }
        if ($item->booking2 !== '') {
            $header .= ',' . $item->booking2;
        }
        $header .= ', Booked';
        if ($canEdit) {
            $header .= ', Action';
        }
        $table->add_header($header);

        $sql = 'SELECT b.*, ';
        $sql .= 'p.preferred_name, s.title ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id  ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = b.user_id  ';
        $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state  ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' ORDER BY s.seq, p.preferred_name';

        $target_edit = 'index.php?option=com_ra_events&task=booking.makeBooking&Itemid=' . $menu_id;
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
            	$target = $target_email . $event_id . '&booking_id=' . $row->id;
            	$class = '<span class="icon-envelope" aria-hidden="true"></span>';
                $link = $this->toolsHelper->buildLink($target , $class, false);
                $table->add_item($row->preferred_name . $link);
            }
            $table->add_item($row->num_places);
            $table->add_item($row->partner);
            if ($item->booking1 !== '') {
                $table->add_item($row->custom1);
            }
            if ($item->booking2 !== '') {
                $table->add_item($row->custom2);
            }

            $table->add_item(HTMLHelper::_('date', $row->created, 'd M y H:i'));
            if ($canEdit) {
                $target = $target_edit . '&event_id=' . $row->event_id . '&user_id=' . $row->user_id;
                $target .= '&id=' . $row->id;
                $actions = $this->toolsHelper->buildButton($target, 'Edit', False, 'sunset');
                $target = $target_resend . '&event_id=' . $row->event_id . '&menu_id=' . $row->user_id;
                $target .= '&id=' . $row->id;
                if ($row->state == 0) {
                    $actions .= $this->toolsHelper->buildButton($target, 'Resend acknowledgement', False, 'lightgreen');
                }  elseif ($row->state == 1) {
                    $actions .= $this->toolsHelper->buildButton($target, 'Resend confirmation', False, 'sunrise');             
                }
                 $table->add_item($actions);
                }
            $table->generate_line();
        }
        $table->generate_table();
        echo $count_bookings . ' confirmed Bookings, ' . $count_places . ' confirmed Places<br>';
// Show any special requests
        $sql = 'SELECT b.special_request, p.preferred_name ';
        $sql .= 'FROM #__ra_bookings as b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p  ON p.id = b.user_id';
        $sql .= ' WHERE (special_request IS NOT NULL) ';
        $sql .= ' AND NOT (special_request = "") ';
        $sql .= 'AND b.event_id=' . $event_id;
        $sql .= ' ORDER BY special_request, p.preferred_name';
        $rows = $this->toolsHelper->getRows($sql);
        if ($rows) {   
            echo '<h4>Special requests</h4>';
            $header = 'Request,Name';
            $table = new ToolsTable;
            $table->add_header($header);
            foreach ($rows as $row) {
                $table->add_item($row->special_request);
                $table->add_item($row->preferred_name);

                $table->generate_line();
            }
            $table->generate_table();
        } else {
         echo 'No special requests<br>';
        }
        // Show summaries of custom fields
        if ($item->booking1 !== '') {
            $this->showSummary($item->id, 1, $item->booking1);
            echo '<br>';
        }
        if ($item->booking2 !== '') {
            $this->showSummary($item->id, 2, $item->booking2);
            echo '<br>';
        }

        $back = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
        $back .= '&Itemid=' . $menu_id;
        echo $this->toolsHelper->backButton($back);
    }

    private function showSummary($id, $num, $field) {
        echo "<b>Summary for $field</b><br>";
        $sql1 = 'SELECT `custom' . $num . '` AS `value`, COUNT(id) AS `count`, ';
        $sql1 .= 'SUM(num_places) AS `nr` ';
        $sql1 .= 'FROM #__ra_bookings ';
        $sql1 .= 'WHERE event_id=' . $id . '  ';
        $sql_where = 'AND state in (0,1) ';
        $sql2 = 'GROUP BY `custom' . $num . '` ';
        $sql2 .= 'ORDER BY `custom' . $num . '` ';
        $rows = $this->toolsHelper->getRows($sql1 . $sql_where . $sql2);
//        $report .= $sql;
        $table = new ToolsTable;
        $header = 'Value,All bookings,All places,Confirmed bookings, Confirmed places,Provisional bookings, Provisional places ';
        $table->add_header($header);
        foreach ($rows as $row) {
            $table->add_item($row->value);

            $table->add_item($row->count);
            $table->add_item($row->nr);

            $sql_where = 'AND state=1 AND `custom' . $num . '` ="' . $row->value . '" ';
            $item = $this->toolsHelper->getItem($sql1 . $sql_where . $sql2);
            $table->add_item($item->count);
            $table->add_item($item->nr);

            $sql_where = 'AND state=0 AND `custom' . $num . '` ="' . $row->value . '" ';
            $item = $this->toolsHelper->getItem($sql1 . $sql_where . $sql2);
            $table->add_item($item->count);
            $table->add_item($item->nr);

            $table->generate_line();
        }
        $table->generate_table();
    }

    public function test() { // 127.0.0.0/index.php?option=com_ra_events&task=booking.test
        echo 'Test<br>';

        $helper = new BookingHelper;
        $helper->notifyOrganiser(2);
        return;
// event 64 = website training
        $token = $helper->encode(64, 935, 1);  // event_id, user_id, mode
        $target = 'index.php?option=com_ra_events&task=booking.processEmail&token=' . $token;
        $this->setRedirect(Route::_($target, false));
        return;

        echo "test: token is $token<br>";
        $helper->decode($token, $date, $mode, $user_id, $event_id, false);
        echo "event_id = $event_id<br>";
        echo "user_id = $user_id<br>";
        echo "date = $date<br>";
        echo "mode = $mode<br>";
        //      return;
        echo "Generating invitation<br>";
        echo $helper->generateInvitation('', 63, 979);
//        $table = Factory::getApplication()->bootComponent('com_ra_events')->getMVCFactory()->createTable('Profile', 'Administrator');
//        $table->home_group = 'ns01';
//        $table->real_name = 'Robin Bigley';
//        $table->user_email = 'Robin@Bigley.me.uk';
//        $table->store();
//        return;

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

    public function test2() {
        $bookingHelper = new BookingHelper;
        // test acknowledgement
        $booking_id = 103;
        $bookingHelper->sendAcknowledgement($booking_id,1);   
  
    return;
        // test invitation
        $user_id = 1213; //
        $event_id = 63; // Ludlow
         echo $bookingHelper->generateInvitation($event_id, $user_id);
//        $token = $bookingHelper->generateInvitation($event_id, $user_id);
//        $target = 'index.php?option=com_ra_events&task=booking.processEmail&token=' . $token . ')';
//        echo $this->toolsHelper->buildLink($target, 'Go',true);
       
    }

}
