<?php

/**
 * Contains functions used in the back end and the front end
 * @version    2.4.7
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 06/03/25 CB created from MailMan
 * 06/08/25 CB accept additional parameter to bookingHelper->showBookings
 * 11/09/25 CB Allow SuperUsers to make additional bookings from the front end
 * 23/09/25 CB extractBookings as table
 * 01/10/25 CB pass user_id to makeBookings
 * 03/10/25 CB message if can't find user for invitation
 * 02/11/25 CB lookupBooking
 * 03/11/25 CB accept menu_id in show_bookings
 * 05/11/25 CB allow for confirmed bookings without "confirmed by"
 * 14/11/25 CB correct display of booking date
 * 06/12/25 CB show special_request on extract & email notification
 * 22/02/26 CB ensure user is logged in showBookings, disallow selection of users for past events
 * 25/02/26 CB changes to confirmation email
 */

namespace Ramblers\Component\Ra_events\Site\Helpers;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use \Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

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
        //$sql = 'SELECT SUM(b.num_places) AS num ';
        $sql = 'SELECT COUNT(b.id) AS num ';
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
                || ($this->toolsHelper->isSuperuser())      // Current user is a SuperUser
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
        $sql = 'SELECT COUNT(id) ';
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

    public function decode($token, &$date, &$mode, &$user_id, &$event_id, $debug = False) {
        /*
          function decode($token, &$walk_id, &$user_id, &$id, &$days, &$mode, $debug = False) {
         * Takes the string that has been obfuscated by function "encode",
         *  and splits it into constituents
         */
        if ($debug) {
            echo "<br>...helper/decode: " . $token . "<br>";
        }

        $temp = $token;
        $temp = strrev(substr($temp, 0, strlen($temp) - 1));
        $chunks = "";
        for ($i = 0; $i < strlen($temp); $i++) {
            $char = substr($temp, $i, 1);
            $chunks .= (hexdec($char) - 6);
        }
        if ($debug) {
            echo "<b>After decoding:</b><br> " . $chunks . "<br>";
            echo '012345678901234567890123456<br>';
        }
        $start_pos = 0;
// get the first variable length integer
// getChunk returns the next value, stating from the given pointer. It then increments the pointer
        $random1 = $this->getChunk($chunks, $start_pos);
        $event_id = $this->getChunk($chunks, $start_pos);
        $date = $this->getChunk($chunks, $start_pos);
// date is dmY
//        $dmy = substr($chunks, 6, 8);
        $date = substr($date, 4, 4) . '-' . substr($date, 2, 2) . '-' . substr($date, 0, 2);
        $date_created = date_create($date);
        $now = date_create(date('Y-m-d'));
        $interval = date_diff($now, $date_created);
        $days = $interval->format('%R%a');
        if ($debug) {
            echo "Encode date= $date, days=$days<br>"; //date_created=$date_created,
//           echo substr($chunks, 10, 4) . '-' . substr($chunks, 8, 2) . '-', substr($chunks, 6, 2);
        }
        $mode = $this->getChunk($chunks, $start_pos);
        $user_id = $this->getChunk($chunks, $start_pos);
        $random2 = $this->getChunk($chunks, $start_pos);
        if ($debug) {
            echo "date=" . $date . ", event_id=" . $event_id . ", User=" . $user_id . ", Mode=" . $mode . "<br>";
        }
        return; //<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
//      Split the string into two parts
//        $length_part1 = substr($parts, 0, 2);
        $event_id = substr($parts, 0, 6);
//        echo $part1 . '<br>' . '0123456789 123456789 123456<br>';
// date is dmY
        $dmy = substr($parts, 6, 8);
        $date = substr($parts, 10, 4) . '-' . substr($parts, 8, 2) . '-' . substr($parts, 6, 2);
        $date_created = date_create($date);
        $now = date_create(date('Y-m-d'));
//        $interval = date_diff($now, $date_created);
//        $days = $interval->format('%R%a');
        if ($debug) {
            echo "Encode date= $date, days=$days<br>"; //date_created=$date_created,
//           echo substr($parts, 10, 4) . '-' . substr($parts, 8, 2) . '-', substr($parts, 6, 2);
        }
        $user_id = substr($parts, 14, 6);
        $mode = substr($parts, 20, 1);
    }

    public function encode($event_id, $user_id, $mode) {
        /*
          public function encode($user_id, $mode) {
         * $user_id is the identifier for the member to whom an email is being sent
          This creates a token to identify an event in such a manner it can be sent in an email and subsequently used to update the record without the need to log in.

          It is built up in several parts:
          Six digits for ("event_id" * 7)
          Eight digits from the current date
          Six digits of the "user_id"
          A single digit flag for the mode (initially only mode=1 is used)
          Four random digits

          The string thus generated is obfuscated in two stages by processing each digit in turn,
          firstly by adding 6, then changing its representation to Hexadecimal Thus 0123789 would become 678def

          Finally the whole string is reversed
         */
        echo "<br>user_id=" . $user_id . ", event_id=" . $event_id . ",mode=$mode<br>";
// construct the token from a series of chunks, each prefixed by a length byte
        $random1 = rand(1, 999);
        $parts = $this->makeChunk($random1);
//        echo "random1 $parts<br>";
        $parts .= $this->makeChunk($event_id);
//        echo "$event_id $parts<br>";
        $date = date_create()->format('dmY');
//        echo "date is $date<br>";
        $parts .= $this->makeChunk($date);
        //       echo "$date $parts<br>";
        $parts .= $this->makeChunk($mode);
        $parts .= $this->makeChunk($user_id);
        //       echo "$user_id $parts<br>";
// Generate random 3 digit number
        $random2 = rand(900, 999);
        $parts .= $this->makeChunk($random2);
//        echo "parts $parts<br>";
        $token = "";
        for ($i = 0; $i < strlen($parts); $i++) {
//            echo $i . substr($token, $i, 1) . " " . dechex(substr($token, $i, 1) + 6) . "<br>";
            $token .= dechex(substr($parts, $i, 1) + 6);
        }
//        echo "<b>Encoded token: " . $token . "</b><br>";
//        echo "<b>Reversed token: " . strrev($token) . 'E' . "</b><br>";
        return strrev($token) . 'E';  // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<

        if (strlen($event_id) < 8) {
// add any required leading zeroes to give 8 characters in length
            $part1 = str_pad($event_id, 6, "0", STR_PAD_LEFT);
        } else {
            $part1 = substr($event_id, 0, 6);
        }

        $part2 = date_create()->format('dmY');
        if (strlen($user_id) < 8) {
// add any required leading zeroes to give 8 characters in length
            $part3 = str_pad($user_id, 6, "0", STR_PAD_LEFT);
        } else {
            $part3 = substr($user_id, 0, 6);
        }
        $part4 = $mode;
        $part5 = substr(time(), 0, 4);  // Pseudo random numbers

        $parts = $part1 . $part2 . $part3 . $part4 . $part5;
        echo "Before encoding:" . $part1 . "-" . $part2 . "-" . $part3 . "-" . $part4 . "-" . $part5 . '<br>';
        $token = "";
        for ($i = 0; $i < strlen($parts); $i++) {
//            echo $i . substr($token, $i, 1) . " " . dechex(substr($token, $i, 1) + 6) . "<br>";
            $token .= dechex(substr($parts, $i, 1) + 6);
        }
        echo "<b>Encoded token: " . $token . "</b><br>";
        echo "<b>Reversed token: " . strrev($token) . 'E' . "</b><br>";
        return strrev($token) . 'E';
    }

    public function extractBookings($event_id, $table = 'N') {
        $sql = 'SELECT booking1, booking2 FROM #__ra_events WHERE id=' . $event_id;
        $event = $this->toolsHelper->getItem($sql);

        $sql = 'SELECT b.partner,b.custom1, b.custom2, p.home_group, u.name, u.email ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = b.user_id  ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' ORDER BY e.group_code,u.name';
        $rows = $this->toolsHelper->getRows($sql);
        $title = 'Group,Name,Email,Extra,Special';
        if ($event->booking1 !== '') {
            $title .= ',' . $event->booking1;
        }
        if ($event->booking2 !== '') {
            $title .= ',' . $event->booking2;
        }
        if ($table == 'Y') {
            $objTable = new ToolsTable();
            $objTable->add_header($title);
            foreach ($rows as $row) {
                $objTable->add_item($row->home_group);
                $objTable->add_item($row->name);
                $objTable->add_item($row->email);
                $objTable->add_item($row->partner);
                $objTable->add_item($row->special_request);
                if ($event->booking1 !== '') {
                    $objTable->add_item($row->custom1);
                }
                if ($event->booking2 !== '') {
                    $objTable->add_item($row->custom2);
                }
                $objTable->generate_line();
            }
            $objTable->generate_table();
        } else {
            echo $title . '<br>';
            foreach ($rows as $row) {
                echo $row->home_group . ',';
                echo $row->name . ',';
                echo $row->email . ',';
                echo $row->partner;
                if ($event->booking1 !== '') {
//$table->add_item($row->custom1);
                    echo ',' . $row->custom1;
                }
                if ($event->booking2 !== '') {
//$table->add_item($row->custom2);
                    echo ',' . $row->custom2;
                }
                echo '<br>';
            }
        }
    }

    public function generateInvitation($website_base, $event_id, $user_id) {
        $toolsHelper = new ToolsHelper;
        $token = $this->encode($event_id, $user_id, 1);
        echo "generateInvitation: event is $event_id, user is $user_id<br>";
        echo "generateInvitation: token is $token<br>";
        $sql = 'SELECT COUNT(id) FROM #__ra_profiles WHERE id=' . $user_id;
        if ($toolsHelper->getValue($sql) == 0) {
            Factory::getApplication()->enqueueMessage('No profile found for ' . $user_id, 'error');
            return 'Sorry, no profile information found for profile ' . $user_id;
        }

        $sql = 'SELECT id FROM #__ra_bookings ';
        $sql .= 'WHERE event_id=' . $event_id . ' AND user_id=' . $user_id;
        $booking_id = $toolsHelper->getValue($sql);
        if (!is_null($booking_id)) {
            return $this->getBookingDetails($booking_id);
        }
        $target = 'index.php?option=com_ra_events&task=booking.processEmail&token=' . $token;
        $sql = 'SELECT title FROM #__ra_events WHERE id=' . $event_id;
        echo "generateInvitation: target is $target<br>";
        $title = $toolsHelper->getValue($sql);
        echo "generateInvitation: title is $title<br>";

        return $toolsHelper->buildLink($website_base . $target, 'Book place on ' . $title);
    }

    public function getBookingDetails($booking_id) {
        $sql = 'SELECT b.*, e.id, e.event_date, e.title, e.booking1, e.booking2, ';
        $sql .= 'e.contact_id, b.num_places, p.preferred_name ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id ';
        $sql .= 'INNER JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = b.user_id ';
        $sql .= 'WHERE b.id=' . $booking_id;
        $item = $this->toolsHelper->getItem($sql);
        if (is_null($item)) {
            return 'Booking ' . $booking_id . ' not found';
        }

        $details .= 'You are booked onto this Event, and the details are as follows:<br>';
        $details .= '<div style="padding-left: 19px;">';
        $details .= '<b>Booking reference:</b>: ' . $booking_id . '/' . $item->event_id . '<br>';
        $details .= '<b>Event:</b> ' . $item->title . '<br>';
        $details .= '<b>Date:</b> ' . HTMLHelper::_('date', $item->event_date, 'd M y') . '<br>';
        $details .= '<h3>Your booking details:</h3>';
        $details .= '<b>Number of bookings:</b> ';
        if ($item->num_places == 1) {
            $details .= '1';
        } else {
            $details .= '2 - you plus ' . $item->partner;
        }
        $details .= '<br>';
        if ($item->booking1 !== '') {
            $details .= '<b>' . $item->booking1 . ':</b> ' . $item->custom1 . '<br>';
        }
        if ($item->booking2 !== '') {
            $details .= '<b>' . $item->booking2 . ':</b> ' . $item->custom2 . '<br>';
        }

        $details .= BookingHelper::showState($item->state);

// Lookup the contact for the event
        $sql = 'SELECT p.preferred_name FROM `#__ra_profiles` AS p ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.user_id = p.id ';
        $sql .= 'WHERE c.id=' . (int) $item->contact_id;
        $contact = $this->toolsHelper->getValue($sql);

        $details .= '<b>Organiser</b> ' . $contact;
 //       $target_email = 'index.php?option=com_ra_tools&task=system.eventOrganiser&id=' . $item->id;
        //$details .= $this->toolsHelper->buildLink($target_email, '<span class="icon-envelope" aria-hidden="true"></span>', false);
//        $details .= $this->toolsHelper->buildLink($target_email, 'Send email<span class="icon-envelope" aria-hidden="true"></span>', false);
        $details .= '<br>';

        $details .= '<b>Created:</b> ' . HTMLHelper::_('date', $item->created, 'd M y') . '<br>';
        if (!is_null($item->confirmed)) {
            $details .= '<b>Confirmed:</b> ' . HTMLHelper::_('date', $item->confirmed, 'd M y') . '<br>';
        }
        if (!is_null($item->cancelled)) {
            $details .= '<b>Cancelled:</b> ' . HTMLHelper::_('date', $item->cancelled, 'd M y') . '<br>';
        }
        $details .= '</div>';
        $details .= '<br>To make changes, please contact the organiser<br>';

        // If going to include a link, website address must be given from the component parameters
        $params = ComponentHelper::getParams('com_ra_events');
        $website = $params->get('website_base', '');
        if ($website !== '') {
            $website = ToolsHelper::addSlash($website);  // add a trailing slash if necessary
            $target_email = $website .'index.php?option=com_ra_tools&task=system.eventOrganiser&id=' . $item->id; 
            $details .= $this->toolsHelper->buildLink($target_email, 'Send email<span class="icon-envelope" aria-hidden="true"></span>', false); 
            $details .= '<br>';

            $details .= 'To see the Terms and Conditions, visit the website using the link below<br>';
            $target =  $website .'index.php?option=com_ra_events&task=event.showTerms';
            $details .= $this->toolsHelper->buildLink($target, 'View Terms and Conditions', false); 
            $details .= '<br>';
            }

        return $details;
    }

    private function getChunk($token, &$start_pos) {
// Used when decoding an email token
// getg the first variable length integere, starting from the given pointer.
// It then increments the pointer
        //       echo "token $token, start $start_pos, ";
        $length = (int) substr($token, $start_pos, 1);
        $value = substr($token, $start_pos + 1, $length);
        //       echo "length $length, value $value, new start ";
        $start_pos = $start_pos + ((int) $length ) + 1;
        //       echo $start_pos . '<br>';
        return $value;
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

    public function lookupBooking($event_id, $email) {
        // When sending an email to all Event attendees, included details of their booking
        $sql = 'SELECT b.id FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = b.user_id ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' AND u.email="' . $email . '"';
        $id = $this->toolsHelper->getValue($sql);

        if (is_null($id)) {
            return '';
        } else {
//      Set the div for the booking details
            $params = ComponentHelper::getParams('com_ra_mailman');
            $booking_details = '<div style="background: ' . $params->get('colour_event', 'rgba(156, 200, 171, 0.4)') . ';';
            $booking_details .= ' border-radius: 5%; padding: 10px; "';
            $booking_details .= '>';
            $booking_details .= $this->getBookingDetails($id);
            $booking_details .= '</div>';
//            die('details=' . $booking_details);
            return $booking_details;
        }
    }

    public function lookupContact($id) {
// Finds the "Linked user" for the given contact
        $sql = 'SELECT user_id FROM #__contact_details WHERE id=' . (int) $id;
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
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . (int) $id;
//        echo "$sql<br>";
        $name = $this->toolsHelper->getValue($sql);
        if ((is_null($name)) || ($name == '')) {
            Factory::getApplication()->enqueueMessage('Preferred name not found for ' . $id, 'error');
            $sql = 'SELECT name FROM #__users WHERE id=' . (int) $id;
            return $this->toolsHelper->getValue($sql);
        } else {
            return $name;
        }
    }

    public function lookupUsername($id) {
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . $id;
//        echo "$sql<br>";
        return $this->toolsHelper->getValue($sql);
    }

    private function makeChunk($value) {
// Used to encode an email token
// returnd the given value (time 7) prefixed bt a lenth byte

        $length = strlen($value);
//        echo "value is $value, length is $length, ";
//        echo $length . $value . '<br>';
        return $length . $value;
    }
        public function sendAcknowledgement($booking_id, $mode){
    // Always sends acknowledgement to the booker
    // If mode =2, also notifies the organiser

        $eventHelper = new EventsHelper;

        $sql = 'SELECT b.state, b.user_id, b.event_id, b.created, b.confirmed, b.cancelled, ';
        $sql .= 'e.title, e.event_date, ';
        $sql .= 'e.booking_info, e.max_bookings,e.booking1, e.booking2, ';
        $sql .= 'c.name AS `organiser`, p.preferred_name, u.email ';   
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id=b.event_id ';
        $sql .= 'INNER JOIN #__contact_details AS c ON c.id=e.contact_id ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = c.user_id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id=c.user_id ';
        $sql .= 'WHERE b.id=' . $booking_id;
        $item = $this->toolsHelper->getItem($sql);
        if (is_null($item)) {
            Factory::getApplication()->enqueueMessage('Booking not found for id ' . $booking_id, 'error');
            return false;
        }
// get name and email of the booker
        $sql = 'SELECT p.preferred_name, u.email ';
        $sql .= 'FROM #__ra_profiles AS p ';
        $sql .= 'INNER JOIN #__users AS u ON u.id= p.id ';
        $sql .= 'WHERE p.id=' . $item->user_id;
        $new_booker = $this->toolsHelper->getItem($sql);
        if (is_null($new_booker)) {
            Factory::getApplication()->enqueueMessage('Booker not found for id ' . $item->user_id, 'error');
            return false;
        }   
//       var_dump($new_booker);
//       die;
        $title = 'Your booking for ' . $item->title;

        $body = $eventHelper->emailHeader($item->event_id, '2');
        $body .= 'Dear ' . $new_booker->preferred_name . ',<br>';
        $body .= $this->getBookingDetails($booking_id);

        // send the email
        $this->toolsHelper->sendEmail($new_booker->email, $item->email, $title, $body);

        if ($mode == 1) {
            return true; // Only send acknowledgement to booker
        }
//=============================================================================    
         // Send a message to the event organiser
       

        $body = $eventHelper->emailHeader($item->event_id, '3');
        $body .= 'Dear ' . $item->organiser . ',<br><br>';
         if ($item->state == 0) {
            $title = 'New booking for ' . $item->title;
            $body .= 'This is to notify you that there has been a new booking for your event ';
        } else {
            $title = 'Updated booking for ' . $item->title;
            $body .= 'This is to notify you that a booking has been updated for your event ';
        }   
        $body .= '<b>' . $item->title . '</b>.<br><br>';
/*
. HTMLHelper::_('date', $item->created, 'H:i');
        $body .= ' on ' . HTMLHelper::_('date', $item->created, 'd M y') . '<br>';
*/
        // 08/12/25 should <br> be necessary?
        $body .= '<br> ' . $new_booker->preferred_name . ' made a booking at ' . HTMLHelper::_('date', $item->created, 'H:i');
        $body .= ' on ' . HTMLHelper::_('date', $item->created, 'd M y') . '<br><br>';
        $body .= 'The list of bookings is now:<br>';
//
        $sql = 'SELECT p.preferred_name, s.title, b.state, b.created, ';
        $sql .= 'b.num_places, b.partner, b.special_request, b.custom1, b.custom2 ';
        $sql .= 'FROM #__ra_profiles AS p ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.user_id=p.id ';
        $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state ';
        $sql .= 'WHERE b.event_id=' . $item->event_id;
        $sql .= ' ORDER BY b.created';
        $rows = $this->toolsHelper->getRows($sql);

        $body .= '<table>';
        $body .='<tr><th>Date</th><th>Name</th><th>Status</th><th>Places</th><th>Details</th></tr>';
        $provisional = 0;
        $confirmed = 0;
        foreach ($rows as $row) {
            if ($row->state == 0) {
                $provisional += $row->num_places;
            } else {
                $confirmed += $row->num_places;
            }
            $body .='<tr>';
            $body .= '<td>' . HTMLHelper::_('date', $row->created, 'd M y H:i') . '</td>';
            $body .= '<td>' . $row->preferred_name . '</td>';
            $body .= '<td>' . $row->title . '</td>';
            $body .= '<td>' . $row->num_places . '</td>';
            $details = $row->partner . ', ' . $row->special_request;
            if ($item->booking1 !== '') {
                $details .= ',' . $row->custom1;
            }
            if ($item->booking2 !== '') {
                $details .= ',' . $row->custom2;
            }
            $body .= '<td>' . $details . '</td>';
            $body .='</tr>';        
        }
        $body .='</table>';
      
        $body .= 'Total possible places: ' . $item->max_bookings . '<br>';    
        $body .= 'Confirmed places: ' . $confirmed . '<br>';
         
         if($confirmed + $provisional > $item->max_bookings){
             $body .= '<div style="color: red;">' .  'Provisional places: ' . $provisional . ' <b>(Over subscribed!)</b></div>';
         } else {
            $body .= 'Provisional places: ' . $provisional;
         }
         echo '<br>';
 //       if ($provisional > 0) {
 //           $body .= 'Logon to confirm these provisional bookings<br>';
 //       }

//        echo 'emailing to ' . $item->email . '<br>';
//      Log the email
        $user_id = Factory::getApplication()->getIdentity()->id;
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
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

    public function showBookings($bookable, $event_id, $menu_id, $callback, $buttons = true) {
        /*
         * invoked from tmpl/event/default.php to generates a literal with details of
         * current bookings, plus action buttons as appropriate
         *
         * callback will the layout to list events, or an event_type_id
         */ 
// Check this event is bookable        
        if ($bookable == 0) {
            return '';
        }
// get details of the Event
        $sql = 'SELECT e.*, c.user_id, s.* ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'LEFT JOIN #__ra_api_sites AS s ON s.id = e.api_site_id ';
        $sql .= 'WHERE e.id=' . $event_id . ' ';

        $event = $this->toolsHelper->getItem($sql);
        if (is_null($event)) {
            throw new \Exception('Event not found', 404);
        }        
        
        // Check if event is in the future
        $event_date = new \DateTime($event->event_date);
        $today = new \DateTime('today');
        $is_future_event = ($event_date >= $today);
        
        // get any bookings, confirmed or provisional
        $sql = 'SELECT SUM(b.num_places) AS `tot` ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.event_id = e.id  ';
        $sql .= 'WHERE e.id=' . $event_id . ' ';
        $sql .= 'AND e.state=1 ';
        $tot_bookings = $this->toolsHelper->getValue($sql);
// Get any confirmed bookings
        $sql = 'SELECT SUM(b.num_places) AS `tot` ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.event_id = e.id  ';
        $sql .= 'WHERE e.id=' . $event_id . ' ';
        $sql .= 'AND e.state=1 ';
        $sql .= 'AND b.state=1 ';
        $confirmed_bookings = $this->toolsHelper->getValue($sql);
        
        // Calculate provisional bookings
        $tot_places = is_null($tot_bookings) ? 0 : $tot_bookings;
        $confirmed_places = is_null($confirmed_bookings) ? 0 : $confirmed_bookings;
        $provisional_bookings = $tot_places - $confirmed_places;


//        echo $sql . '<br>';
       echo 'Total number of spaces ' . $event->max_bookings . '<br>';
        if (is_null($confirmed_bookings)) {
            $available = ' ' . $event->max_bookings;
        } else {
            $available = ' ' . ($event->max_bookings - $confirmed_bookings);
        }

// Find any existing bookings
        $details =   $this->countBookingsSite($event_id) ;

        $details .= ', ' . $available;
        $details .= ($available > 1) ? ' spaces' : ' space';
        $details .= ' available';
          if ($buttons == false) {
// Admin application, just display the literal with no buttons
            return $details;
        }
// Check User is logged in
        if ($this->current_user_id == 0) {
 //           throw new \Exception('You must be logged in to view bookings', 403);
            return $details;
        }  


        // See if this Event has been imported from another site
        if (!is_null($event->api_site_id)) {
//            $url = $sql;
            $details .= '<br>Event is being organised by <b>' . $event->title . '</b>, you must log in to their site to manage bookings';
            $link = $event->url . '/index.php?option=com_ra_events&view=event&id=' . $event->original_id;
            $details .= $this->toolsHelper->buildButton($link, 'Visit ', True, 'red');
            return $details;
        }

        if ($this->current_user_id == 0) {
            $details .= '<br>Login to make a booking or manage an existing booking<br>';
            return $details;
        }
                
// User is logged in

        $target = 'index.php?option=com_ra_events&Itemid=' . $menu_id;
        if ($tot_bookings > 0) {
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
                $link = 'index.php?option=com_ra_events&Itemid=' . $menu_id;
                $link .= '&task=event.showEmails&id=' . $event_id;
                $link .= '&Itemid=' . $menu_id;
                $link .= '&callback=' . $callback;
                $details .= $this->toolsHelper->buildButton($link, $label, false, 'sunset');
            }

            if ($available > 0 && $is_future_event) {
                $select = $target . '&task=booking.selectUsers&event_id=' . $event_id;
                $details .= '<a>' . $this->toolsHelper->buildButton($select, 'Select Users') . '</a>';
            }
            if ($tot_bookings > 0) {
                $label = 'Send email';
                $link = 'index.php?option=com_ra_tools&Itemid=' . $menu_id;
                $link .= '&task=system.eventAttendees';
                $link .= '&id=' . $event_id;
                $details .= $this->toolsHelper->buildButton($link, $label, True, 'orange');

                $label = 'Show Reports';
                $link = 'index.php?option=com_ra_events&Itemid=' . $menu_id;
                $link .= '&task=event.bookingReports&id=' . $event_id;
                $details .= $this->toolsHelper->buildButton($link, $label, False, 'darkgreen');
            }
        }
        $details .= $event->api_site_id;
        $details .= '<br><br>';

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
                if ($booking->confirmed_by == 0) {
                    // Created as a confirmed booking
                    $details .= 'You have a confirmed booking';
                } else {
                    $details .= 'Your booking has been confirmed';
                    if (!is_null($booking->confirmed)) {
                        $details .= ' on ' . $booking->confirmed;
                    }
                    $details .= ' by ';
                    $details .= $this->lookupPreferredname($booking->confirmed_by);
                }
            } elseif ($booking->state == -2) {
                $details .= 'Your booking was cancelled on ';
                $details .= $booking->cancelled;
                $details .= ' by ';
                $details .= $this->lookupPreferredname($booking->cancelled_by);
            }

            $details .= '<br>If you have changed your mind, please contact the organiser<br>';
        } else {
            if ($available > 0 && $is_future_event) {
                if (($event->max_bookings - $confirmed_bookings - $provisional_bookings ) <2) {
                    $details .= '<br><b>WARNING: <b> If you make a booking,and the existing provisional bookings are accepted, ';
                    $details .= 'yours may not be possible</b><br>';
                    $button_label = 'Join the waiting list';
                }   else {
                    $button_label = 'Make a booking';
                }
                $target .= '&task=booking.makeBooking';
                $target .= '&event_id=' . $event_id;
                $target .= '&user_id=' . $this->current_user_id;
                $details .= $this->toolsHelper->buildButton($target, $button_label, False, 'red');
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
        }
        if ($state == 0) {
            return'<p style="color:orange"><b>Status: </b>Provisional</p>';
        } elseif ($state == 1) {
            return '<p style="color:green"><b>Status: </b>Confirmed</p>';
        } elseif ($state == -2) {
            return '<p style="color:red"><b>Status: </b>Cancelled</p>';
        } else {
            return '<p> <b>' . $state . '</b></p>';
        }
    }

    private function statusDescription($count, $status) {
        $details = $count . ' ' . $status;
        $details .= ' place';
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
