<?php

/**
 * Contains functions used in the back end and the front end
 * @version    2.1.9
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 06/03/25 CB created from MailMan
 * 17/03/25 CB optionally create confirmed booking
 * 29/03/25 CB show booking count
 * 08/04/25 CB bookingsForUser
 * 10/04/25 CB validateUser
 * 30/04/25 CB allow partner to book
 * 01/05/25 Cb countBookings - event_id
 * 03/05/25 CB lookupEvent, $this->canDo
 * 08/05/25 CB show list of bookings if logged in
 * 30/06/25 CB extractBookings
 * 04/08/25 CB correction for notify_organiser
 * 06/08/25 CB accept additional parameter to bookingHelper->showBookings
 */

namespace Ramblers\Component\Ra_events\Site\Helpers;

use Joomla\CMS\Factory;
use \Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class BookingHelper {

    protected $canDo;
    protected $current_user_id;
    public $fields_modified;
    public $message;
// database fields
    public $id;
    public $list_id;
    public $user_id;
    public $state;

    function __construct() {
        $this->id = 0;
        $this->list_id = 0;
        $this->user_id = 0;
        $this->state = 0;
//        $this->action = 'Failed';
        $this->message = '';
        $this->current_user_id = Factory::getApplication()->getSession()->get('user')->id;
        $this->toolsHelper = new ToolsHelper;
        $this->canDo = ContentHelper::getActions('com_ra_events');
    }

    public function bookingsForUser($user_id) {
        $sql = 'SELECT COUNT(id) FROM #__ra_bookings WHERE user_id=' . $user_id;
        return $this->toolsHelper->getValue($sql);
    }

    public function cancelBooking($id, $user_id) {
        if ($this->current_user_id == 0) {
            throw new \Exception('You must be logged in to cancel a booking', 403);
        }
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $sql = 'UPDATE #__ra_bookings SET state=-2, ';
        $sql .= 'cancelled_by=' . $this->current_user_id . ', ';
        $sql .= 'cancelled="' . $date . '" ';
        $sql .= 'WHERE id=' . $id;
        $this->toolsHelper->executeCommand($sql);
    }

    public function confirmBooking($id) {
        if ($this->current_user_id == 0) {
            throw new \Exception('You must be logged in to confirm a booking', 403);
        }
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $sql = 'UPDATE #__ra_bookings SET state=1, ';
        $sql .= 'confirmed_by=' . $this->current_user_id . ', ';
        $sql .= 'confirmed="' . $date . '" ';
        $sql .= 'WHERE id=' . $id;
//        echo $sql;
        $this->toolsHelper->executeCommand($sql);
    }

    private function countBookingsSite($event_id) {
// Invoked from showBookings
// Find total number of bookings, return a descriptive string
        $sql = 'SELECT SUM(b.num_places) AS num ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $total = $this->toolsHelper->getValue($sql);
        if (is_null($total)) {
//            echo 'No bookings yet';
            return '0';
        } else {
            $total_message = "$total bookings found: ";
        }
// Get number for each status
        $sql = 'SELECT SUM(b.num_places) AS num, s.title ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state  ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' GROUP BY s.title';
        $sql .= ' ORDER BY s.seq';

        $rows = $this->toolsHelper->getRows($sql);
        $status_count = count($rows);
//        echo "$status_count different statuses<br>";

        $row_count = 0;
        $details = '';
        foreach ($rows as $row) {

            $row_count++;
            $details .= $this->statusDescription($row->num, $row->title);
            if (count($rows) == 1) { //All in the same status
                return $details;
            }
            if ($row_count == 1) {
                $details = $total_message . $details;
            }
            if ($row_count == $status_count) {
                return $details;
            }

            $details .= ' AND ';
        }
    }

    public function createBooking($event_id, $user_id, $state = 0, $partner = '') {
        //       die('Creating booking');
// invoked from BookingHelper
        if ($this->current_user_id == 0) {
            throw new \Exception('You must be logged in to make a booking', 403);
        }
// Validate input
        $sql = 'SELECT e.bookable, e.notify_organiser, c.user_id FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__contact_details AS c ON c.id=e.contact_id ';
        $sql .= 'WHERE e.id=' . $event_id;
        $app = Factory::getApplication();
        $item = $this->toolsHelper->getItem($sql);
        if ($item->bookable == 0) {
            throw new \Exception('This event cannot be booked', 403);
        }

        if (($app->isClient('administrator') || // We are in the backend
                $this->current_user_id == $item->user_id)   // Current user is the Organiser
                || ($this->current_user_id == $user_id)) {  // Current user is self booking
            $table = $app->bootComponent('com_ra_events')->getMVCFactory()->createTable('Bookings', 'Administrator');
            $table->event_id = $event_id;
            $table->user_id = $user_id;
            $table->partner = $partner;
            if ($partner == '') {
                $table->num_places = 1;
            } else {
                $table->num_places = 2;
            }
            $table->state = $state;

            $result = $table->store();
            if (!$result) {
                $message = 'Unable to create booking record';
                return false;
            }
            if ($state == 1) {
                $message = 'Confirmed booking created';
                return $table->id;
            }
            $message = 'Provisional booking created';

// Check if bookings are to be notified to the organiser
            if ($this->item->notify_organiser == 1) {
// check not being booked by the organiser
                if ($this->current_user_id !== $user_id) {
// send a message to the organiser
                    $this->notifyOrganiser($event_id, $user_id);
                    $message .= ', notification sent to organiser';
                }
                $app->enqueueMessage($message, 'info');
            }
        } else {
            throw new \Exception('Only the organiser can make additional bookings', 403);
//            echo 'Sql ' . $sql . '<br>';
//            echo 'Current ' . $this->current_user_id . ', Contact ' . $item->user_id . '<br>';
//            echo "user $user_id ";
//            die;
        }
        return $table->id;
    }

    public function countBookings($id, $status = '') {
        // Unles a status is given this return to number of provisional+confirmed bookings
        $sql = 'SELECT COUNT( id) ';
        $sql .= 'FROM #__ra_bookings ';
        $sql .= 'WHERE event_id=' . $id;

        if ($status == '') {
            $sql .= ' AND state in(0,1) ';
        } else {
            $sql .= ' AND state="' . $status . '"';
        }
//        return $sql;
        return $this->toolsHelper->getValue($sql);
    }

    public function extractBookings($event_id) {

        echo 'Group,Name,Email,Extra<br>';

        $sql = 'SELECT b.partner, p.home_group, u.name, u.email ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id  ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = b.user_id  ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id  ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' ORDER BY e.group_code,u.name';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            echo $row->home_group . ',';
            echo $row->name . ',';
            echo $row->email . ',';
            echo $row->partner . '<br>';
        }
    }

    public function isBooked($event_id, $user_id) {
        $sql = 'SELECT b.id FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' AND b.user_id=' . $user_id;
        $id = $this->toolsHelper->getValue($sql);
        if (is_null($id)) {
            return false;
        } else {
            return true;
        }
    }

    public function lookupContact($id) {
// Finds the "Linked user" for the given contact
        $sql = 'SELECT user_id FROM #__contact_details WHERE id=' . $id;
//       echo "$sql<br>";
        return $this->toolsHelper->getValue($sql);
    }

    public function lookupEmail($email) {
        $sql = 'SELECT id FROM #__users WHERE email="' . $email . '"';
        return $this->toolsHelper->getValue($sql);
    }

    public function lookupEvent($id) {
        $sql = 'SELECT title FROM #__ra_events WHERE id=' . $id;
        return $this->toolsHelper->getValue($sql);
    }

    public function lookupOrganiser($booking_id) {
        $sql = 'SELECT e.contact_id  ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id ';
        $sql .= 'WHERE b.id=' . $booking_id;
        $item = $this->toolsHelper->getItem($sql);
    }

    public function lookupPreferredname($id) {
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . $id;
//       echo "$sql<br>";
        return $this->toolsHelper->getValue($sql);
    }

    public function lookupUsername($id) {
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . $id;
//       echo "$sql<br>";
        return $this->toolsHelper->getValue($sql);
    }

    public function notifyOrganiser($booking_id) {
// Send a message to the event organiser
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $eventHelper = new EventsHelper;

        $sql = 'SELECT b.user_id, b.event_id, e.title, u.email, ';
        $sql .= 'c.name AS `organiser`, p.preferred_name  ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id=b.event_id ';
        $sql .= 'INNER JOIN #__contact_details AS c ON c.id=e.contact_id ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = c.user_id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id=c.user_id ';
        $sql .= 'WHERE b.id=' . $booking_id;
        $item = $this->toolsHelper->getItem($sql);

        // get name and email of the booker
        $sql = 'SELECT p.preferred_name, u.email ';
        $sql .= 'FROM #__ra_profiles AS p ';
        $sql .= 'INNER JOIN #__users AS u ON u.id= p.id ';
        $sql .= 'WHERE p.id=' . $item->user_id;
        $new_booker = $this->toolsHelper->getItem($sql);
        //       var_dump($new_booker);
        //       die;
        $title = 'New booking for ' . $item->title;

        $body = $eventHelper->emailHeader($item->event_id, '3');
        $body .= $new_booker->preferred_name . ' made a booking at ' . HTMLHelper::_('date', $date, 'H:i');
        $body .= ' on ' . HTMLHelper::_('date', $date, 'd M yy') . '<br><br>';
        $body .= 'The list of bookings is now:<br>';
//
        $sql = 'SELECT p.preferred_name, s.title ';
        $sql .= 'FROM #__ra_profiles AS p ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.user_id=p.id ';
        $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state ';
        $sql .= 'WHERE b.event_id=' . $item->event_id;
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
//        echo 'emailing to ' . $item->email . '<br>';
//      Log the email
        $user_id = Factory::getApplication()->getIdentity()->id;
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query
                ->insert($db->quoteName('#__ra_emails'))
                ->set('sub_system ="RA Events"')
                ->set('record_type=2')
                ->set('ref =' . $db->quote($item->event_id))
                ->set('date_sent =' . $db->quote($date))
                ->set('sender_name =' . $db->quote($new_booker->preferred_name))
                ->set('sender_email =' . $db->quote($new_booker->email))
                ->set('addressee_name =' . $db->quote($item->organiser))
                ->set('addressee_email =' . $db->quote($item->email))
                ->set('title =' . $db->quote($title))
                ->set('body =' . $db->quote($body))
                ->set('state =1')
                ->set('created =' . $db->quote($date))
                ->set('created_by =' . $db->quote($user_id));
        echo $query;
        $db->setQuery($query);
        $return = $db->execute();
//        die;
// send the email
        $this->toolsHelper->sendEmail($item->email, $item->email, $title, $body);
    }

    public function showBookings($bookable, $event_id, $callback, $buttons = true) {
        /*
         * invoked from tmpl/event/default.php to generates a literal with details of
         * current bookings, plus action buttons as appropriate
         *
         * callback will the layout to list events, or an event_type_id
         */

        if ($bookable == 0) {
            return '';
        }
// get any bookings
        $sql = 'SELECT SUM(b.num_places) AS `tot` ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.event_id = e.id  ';
        $sql .= 'WHERE e.id=' . $event_id . ' ';
        $sql .= 'AND e.state=1 ';
        $sql .= 'AND b.state=1 ';
        $tot_bookings = $this->toolsHelper->getValue($sql);
// get details of the Event
        $sql = 'SELECT e.bookable, e.max_bookings, c.user_id, e.api_site_id ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'WHERE e.id=' . $event_id . ' ';

        $event = $this->toolsHelper->getItem($sql);
//        echo $sql . '<br>';
//        echo 'max ' . $event->max_bookings . '<br>';
        if (is_null($tot_bookings)) {
            $available = ' ' . $event->max_bookings;
        } else {
            $available = ' ' . ($event->max_bookings - $tot_bookings);
        }
// Find any existing bookings
        $details = '<b>' . $this->countBookingsSite($event_id) . '</b>';

        $details .= ', ' . $available;
        $details .= ($available > 1) ? ' spaces' : ' space';
        $details .= ' available';
        if ($buttons == false) {
            // Admin application, just display the literal with no buttons
            return $details;
        }
        if ($this->current_user_id == 0) {
            $details .= '<br>Login to make a booking or manage an existing booking<br>';
            return $details;
        }

        // User is logged in

        $target = 'index.php?option=com_ra_events&Itemid=' . $this->menu_id;
// See if current user is logged in
        if (($this->current_user_id > 0) AND ($tot_bookings > 0)) {
            $link = $target . '&task=booking.showBookings&event_id=' . $event_id;
            $details .= $this->toolsHelper->imageButton('I', $link);
        }

// See if current user is the organiser
// if so, show link to list members who have booked
        if (($this->current_user_id == $event->user_id) || ($this->canDo->get('core.edit'))) {
            // See in any emails have been sent or received
            $sql = 'SELECT COUNT(id) FROM #__ra_emails ';
            $sql .= 'WHERE sub_system="RA Events" ';
            $sql .= 'AND ref=' . $event_id . ' ';
            $email_count = $this->toolsHelper->getValue($sql);
            if ($email_count > 0) {
                $label = 'Show ' . $email_count . ' emails';
                $link = 'index.php?option=com_ra_events&Itemid=' . $this->menu_id;
                $link .= '&task=event.showEmails&id=' . $event_id;
                $link .= '&Itemid=' . $this->menu_id;
                $link .= '&callback=' . $callback;
                $details .= $this->toolsHelper->buildButton($link, $label, false, 'sunset');
            }

            if ($available > 0) {
                $select = $target . '&task=booking.selectUsers&event_id=' . $event_id;
                $details .= '<a>' . $this->toolsHelper->buildButton($select, 'Select Users') . '</a>';
            }
            if ($tot_bookings > 0) {
                $label = 'Send email';
                $link = 'index.php?option=com_ra_tools&Itemid=' . $this->menu_id;
                $link .= '&task=system.eventAttendees';
                $link .= '&id=' . $event_id;
                $details .= $this->toolsHelper->buildButton($link, $label, True, 'orange');
                // index.php?option=com_ra_events&task=event.extractBookings&id=3
                $label = 'Extract details';
                $link = 'index.php?option=com_ra_events&Itemid=' . $this->menu_id;
                $link .= '&task=event.extractBookings&id=' . $event_id;
                $details .= $this->toolsHelper->buildButton($link, $label, True, 'darkgreen');
            }
        }
        $details .= '<br><br>';
// See if this Event has been imported from another site
        if ($event->api_site_id > 0) {
            $sql = 'SELECT url FROM #__ra_api_sites WHERE id=' . $event->pi_site_id;
            //$url = $this->toolsHelper->getValue($sql);
            $url = $sql;
            echo 'Site is ' . $url . '<br>';
            $details .= $this->toolsHelper->buildButton($url, 'Visit ' . $url, True, 'red');
            return;
        }
// See if current user has already booked
        $sql = 'SELECT s.title, b.created, b.created_by, b.confirmed, b.confirmed_by,  ';
        $sql .= 'b.cancelled, b.cancelled_by, b.state ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state  ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' AND b.user_id=' . $this->current_user_id;
//        echo $sql;
        $booking = $this->toolsHelper->getItem($sql);

        if (!is_null($booking)) {
// The current user has made a booking
            if ($booking->state == 0) {
                $details .= 'A provisional booking was made on ';
                $details .= $booking->created;
                $details .= ' by ';
                if ($booking->created_by == $this->current_user_id) {
                    $details .= 'you';
                } else {
                    $details .= $this->lookupPreferredname($booking->created_by);
                }
            } elseif ($booking->state == 1) {
                $details .= 'Your booking was confirmed on ';
                $details .= $booking->confirmed;
                $details .= ' by ';
                $details .= $this->lookupPreferredname($booking->confirmed_by);
            } elseif ($booking->state == -2) {
                $details .= 'Your booking was cancelled on ';
                $details .= $booking->cancelled;
                $details .= ' by ';
                $details .= $this->lookupPreferredname($booking->cancelled_by);
            }

            $details .= '<br>If you have changed your mind, please contact the organiser<br>';
        } else {
            if ($available > 0) {
                $label = 'Make a booking';
                $target .= '&task=booking.makeBooking';
                $target .= '&event_id=' . $event_id;
                $details .= $this->toolsHelper->buildButton($target, $label, False, 'red');
            }
        }

        return $details;
    }

    public function showBookingsAdmin($bookable, $event_id) {
        if ($bookable == 0) {
            return '-';
        }


        $sql = 'SELECT SUM(num_places) FROM #__ra_bookings WHERE event_id=' . $event_id;
//        $details = "$sql<br>";
        $count = $this->toolsHelper->getValue($sql);
        $details = (is_null($count) ? '0' : $count);
        $sql = 'SELECT max_bookings FROM #__ra_events WHERE id=' . $event_id;
//        echo $sql;
        $details .= '/' . $this->toolsHelper->getValue($sql);
        if ($count > 0) {
            $target = 'administrator/index.php?option=com_ra_events&task=events.';
            $link = $target . 'showBookings&id=' . $event_id;
            $details .= $this->toolsHelper->imageButton('I', $link);
            $link = $target . 'extractBookings&id=' . $event_id;
            $details .= $this->toolsHelper->imageButton('D', $link);
        }
        return $details;
    }

    public function showEmails($event_id) {
        $sql = 'SELECT record_type, date_sent, title ';
        $sql .= 'FROM #__ra_emails ';
        $sql .= 'WHERE sub_system="RA Events" ';
        $sql .= 'AND ref=' . $event_id . ' ';
        $sql .= 'ORDER BY record_type ';
        $this->toolsHelper->showQuery($sql);
        return;
        $emails = $this->toolsHelper->getRows($sql);
        if (count($emails) > 0) {
            foreach ($emails as $email) {
                echo $email->date_sent;
            }
        }
    }

    public static function showState($state) {
        if ($state == '') {
            return '';
        } elseif ($state == 0) {
            return '<p style="color:orange">Provisional</p>';
        } elseif ($state == 1) {
            return '<p style="color:green">Confirmed</p>';
        } elseif ($state == -2) {
            return '<p style="color:red">Cancelled</p>';
        } else {

        }
    }

    private function statusDescription($count, $status) {
        $details = $count . ' ' . $status;
        $details .= ' booking';
        if ($count > 1) {
            $details .= 's';
        }
        return $details;
    }

    public function today() {
// Returns current date, formatted correctly
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'));
        return substr($date->toSql(true), 0, 10);
    }

    public function validateEmail($id, $email) {
// invoked from profileform Controller when creating a new user
// ensures the given email is not already in use
        if ($id > 0) {
            return true;
        }
        $app = Factory::getApplication();
        $helper = New ToolsHelper;

        $sql = 'SELECT name,  block, requireReset FROM #__users ';
        $sql .= 'WHERE email="' . $email . '"';
        $user = $helper->getItem($sql);
        if (!is_null($user)) {
            $message = '';
            if ($user->block == 1) {
                $message .= 'Blocked ';
            }
            $message .= 'User ' . $user->name . ' already present with this email address ';
            if ($user->requireReset == 1) {
                $message .= ' (Requires password reset)';
            }
            $app->enqueueMessage($message, 'error');
            return false;
        }
//        // Validate against profiles
//        $sql = 'SELECT home_group, preferred_name  FROM #__ra_profiles ';
//        $sql .= 'WHERE email="' . $email . '"';
//        $user = $helper->getItem($sql);
//        if (!is_null($user)) {
//            $message = '';
//
//            $message .= 'User ' . $user->name . ' already present with this email address ';
//
//            $app->enqueueMessage($message, 'error');
//            return false;
//        }
        return true;
    }

    public function validateUser($email, $username) {
        /*  Returns one of three possibilities:
          // 1. If neither email or username is being used, returns zero
         * 2. If valid user exists with this email, OR with this username, user id is returned
          // 3. If User is blocked or awaiting password reset, returns false (if User exist with the given)and sets up ->message)
         *
         */
        $app = Factory::getApplication();
        $db = Factory::getDbo();
        $helper = New ToolsHelper;
// Check email is not already in user
        $error = false;
        $sql = 'SELECT id, name, block, requireReset FROM #__users ';
        $sql .= 'WHERE email=' . $db->quote($email);
//        echo $sql . '<br>';
        $user = $helper->getItem($sql);
        if (!is_null($user->id)) {
//            echo 'found ' . $user->id . ' for ' . $email . '<br>';
            $this->message = '';
            if ($user->block == 1) {
                $this->message .= 'Blocked ';
                $error = true;
            }
            $this->message .= 'User ' . $user->name . ' already present with this email address ';
            if ($user->requireReset == 1) {
                $this->message .= ' (Requires password reset)';
                $error = true;
            }
            if ($error == false) {
                return $user->id;
            }
        }
//      See if username is already in use
        $sql = 'SELECT id, name, block, requireReset FROM #__users ';
        $sql .= 'WHERE name=' . $db->quote($username);
        $user = $helper->getItem($sql);
        if (!is_null($user->id)) {
            $this->message = '';
            if ($user->block == 1) {
                $this->message .= 'Blocked ';
                $error = true;
            }
            $this->message .= 'User already present with this name, email=' . $user->email;
            if ($user->requireReset == 1) {
                $this->message .= ' (Requires password reset)';
                $error = true;
            }
            if ($error == false) {
                return $user->id;
            } else {
                return false;
            }
        }
        return $user->id;
    }

}
