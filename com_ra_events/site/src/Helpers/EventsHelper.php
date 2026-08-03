<?php

/**
 * Contains functions used in the back end and the front end
 * @version    2.5.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 18/06/25 CB created
 * 28/08/25 CB use apisites from tools, not events
 * 09/09/25 CB deleted function notifyOrganiser
 * 10/09/25 CB delete event_time_end, add num_bookings + max_bookings
 * 15/09/25 CB show Profiles (if MailMan not installed)
 * 18/09/25 CB ensure num_bookings is integer (for Committee meetings it will be blank)
 * 24/09/25 CB correct lookupContact
 * 08/02/26 CB Don't create new events if status = 0 (unpublished), but update if they exist
 * 25/02/26 CB changes to email header and body for new booking confirmation, show booking_info in red
 * 26/02/26 CB showFirst - show count of fields
 * 09/03/26 CB add function createLog (calls ToolsHelper->createLog)
 * 06/04/26 CB registerEmails
 */

namespace Ramblers\Component\Ra_events\Site\Helpers;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

class EventsHelper {

    protected $app;
    protected $db;
    protected $canDo;
    protected $current_user_id;
    protected $toolsHelper;
    public $messages;

    function __construct() {
        $this->app = Factory::getApplication();
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
        $this->messages = [];
        $this->current_user_id = Factory::getApplication()->getSession()->get('user')->id;
        $this->toolsHelper = new ToolsHelper;
        $this->canDo = ContentHelper::getActions('com_ra_events');
    }

    private function createRecipent($mailshot_id, $user_id) {
        if ($mailshot_id == 0) {
            Factory::getApplication()->enqueueMessage('Recipient ' . $user_id . ', mailshot id =0', 'comment');
            return false;
        }
        if ($this->user_id == 0) {
            Factory::getApplication()->enqueueMessage("User id = 0", 'error');
            return false;
        }
        $db = Factory::getDbo();
        $jinput = Factory::getApplication()->input;
        $ip_address = $jinput->server->get('REMOTE_ADDR');
        $sql = 'SELECT email FROM #__users WHERE id=' . $user_id;
        $email = $this->toolsHelper->getValue($sql);
        $columns = array('mailshot_id', 'user_id', 'email', 'ip_address', 'created_by');

        $values = array($db->quote($mailshot_id),
            $db->quote($user_id),
            $db->quote($email),
            $db->quote($ip_address),
            $db->quote($this->user_id));

        $query = $db->getQuery(true);
// Prepare the insert query.
        $query
                ->insert($db->quoteName('#__ra_mail_recipients'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

// Set the query using our newly populated query object and execute it.
        $db->setQuery($query);
        $db->execute();
        $id = $db->insertid();
        if ($id == 0) {
            return 0;
        }
        return 1;
    }
    function countAttendees($event_id) {
        // Returns number of active attendees for given event
        $sql = 'SELECT COUNT(*) ';
        $sql .= 'FROM  `#__ra_bookings` AS b ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = b.user_id ';
        $sql .= 'WHERE (b.state=0 OR b.state=1) AND u.block=0 AND u.requireReset=0 AND e.id=' . $event_id;
        return $this->toolsHelper->getValue($sql);
    }

    public function deleteShared() {
        $sql = 'UPDATE `#__ra_events` Set api_site_id = NULL where api_site_id = 0';
        $this->toolsHelper->executeCommand($sql);
        $sql = 'SELECT id, title FROM #__ra_events WHERE api_site_id IS NOT NULL';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $this->toolsHelper->executeCommand('DELETE FROM #__ra_events WHERE id=' . $row->id);
            Factory::getApplication()->enqueueMessage('Event ' . $row->title . ' deleted', 'info');
        }
    }

    public function dumpShared($api_site_id, $events) {
// Invoked from ApisitesController.refresh
        echo '<h2>Dump of Shared events</h2>';
        $sql = 'SELECT * FROM #__ra_api_sites WHERE id=' . $api_site_id;

        $site = $this->toolsHelper->getItem($sql);
        $website = $site->url;
        $target = '&id=';
        ToolBarHelper::title('Shared events from ' . $website);
        $count = count($events);
        var_dump($events);
        echo '<br>';
        $back = 'administrator/index.php?option=com_ra_tools&view=apisites';
        $back .= '&id=' . $api_site_id;
        echo $this->toolsHelper->backButton($back);
    }

    public function emailHeader($event_id, $record_type) {
        /*
         * Builds the header fragment for the outgoing HTML email.
         * ToolsHelper::sendEmail() adds the outer HTML wrapper.
         */
        $logo = '/images/com_ra_events/logo.png';
        $params = ComponentHelper::getParams('com_ra_events');

// Set the header container using flexbox for responsive layout.
        $header = '<div style="';
        $header .= 'display: flex; ';
        $header .= 'justify-content: space-between; ';
        $header .= 'align-items: center; ';
        $header .= 'gap: 20px; ';
        $header .= 'background: ' . $params->get('colour_header', 'rgba(20, 141, 168, 0.5)') . '; ';
        $header .= 'border-radius: 5%; ';
        $header .= 'padding: 20px; ';
        $header .= 'box-sizing: border-box; ';
        $header .= 'width: 100%; ';
        $header .= 'max-width: 100%; ';
        $header .= 'overflow: hidden; ';
        $header .= '">';

//      Set the header text block.
        $header .= '<div style="flex: 1 1 auto; text-align: left; min-width: 0; overflow-wrap: break-word;">';
        $header_text = $params->get('email_header', 'Send from RA Events');
        $header_text .= '<br>';
// Add the text
        if ($record_type == '1') {
            $header_text .= 'Enquiry to organiser of: ';
        } elseif ($record_type == '2') {    
            $header_text .= 'Details of Booking: ';
        } elseif ($record_type == '3') {
            $header_text .= 'New booking:';
        } elseif ($record_type == '4') {
            $header_text .= 'Message to everyone booked onto: ';            
        } else {
            $header_text .= 'Message of type: ' . $record_type;
        }
        $sql = 'SELECT title FROM #__ra_events WHERE id=' . $event_id;
        $header_text .= $this->toolsHelper->getValue($sql);        
        $header .= $header_text;
        $header .= '</div>';

//      Add the logo block if the file is present.
        if (file_exists(JPATH_ROOT . $logo)) {
            $image_data = file_get_contents(JPATH_ROOT . $logo);
            $encoded = base64_encode($image_data);
            $header .= '<a href="' . $params->get('website') . '" style="flex-shrink: 0; display: flex; margin-left: auto;">';
            $header .= '<img src="data:image/jpeg;base64,' . $encoded . '" ';
            $header .= 'style="height: ' . $params->get('height') . 'px; width: ' . $params->get('width') . 'px; display: block; max-width: 100%; height: auto;" ';
            $header .= 'alt="Logo">';
            $header .= '</a>';
        } else {
            Factory::getApplication()->enqueueMessage('Logo file "' . $logo . '" not found', 'warning');
        }

        $header .= '</div>';
        return $header;
    }

    static function emailType($record_type) {
        if ($record_type == 1) {
            return 'Enquiry';
        } elseif ($record_type == 1) {
            return 'Booking';
        } else {
            return 'Mailshot';
        }
    }

    public function getSharedEvents($site_id) {
        if (JDEBUG) {
            $message = 'Site id ' . $site_id . ', ';
            $message .= 'Seeking events from site id ' . $site_id;
            $this->messages[] = $message;
        }
        $sql = 'SELECT * FROM #__ra_api_sites WHERE id=' . $site_id;

        $site = $this->toolsHelper->getItem($sql);
        $token = trim($site->token);
        $curl = curl_init();
       
        $url = $site->url . '/api/index.php/v1/ra_events/events';
        if (JDEBUG) {
            $message = 'Site id ' . $site_id . ', ';
            $message .= 'Seeking events from ' . $url;
            $message .= 'Token is ' . $token;
            $this->messages[] = $message;
        }
//      set up maximum time of 5 minutes
        $max = 5 * 60;
        set_time_limit($max);

// HTTP request headers
        $headers = [
            'Accept: application/vnd.api+json',
            'Content-Type: application/json',
            sprintf('X-Joomla-Token: %s', $token),
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false, // do not include header in output
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => 'utf-8',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => $max,
            CURLOPT_TIMEOUT => $max,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_REFERER => "com_ra_tools", // say who wants the feed
            CURLOPT_HTTPHEADER => $headers,
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // do not follow redirects
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // do not output result
                ]
        );

        $rawResponse = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $responseHeaders = '';
        $responseBody = '';
        if ($rawResponse !== false) {
            $responseHeaders = substr($rawResponse, 0, $headerSize);
            $responseBody = substr($rawResponse, $headerSize);
        }
//        if (curl_errno($curl)) {
//            echo curl_error($curl);
//        }
        curl_close($curl);

        if ($httpCode !== 200) {
            $message = 'Error: ' . $httpCode;
            if ($httpCode == 401) {
                $message .= ': Authorization Required (Token missing or invalid)';
            } else {
                $message .= ': ' . $error;
            }
            $this->messages[] = $message;
            $this->messages[] = 'Endpoint: ' . $endpoint;
            if ($responseHeaders !== '') {
                $this->messages[] = 'Response headers: ' . trim($responseHeaders);
            }
            if ($responseBody !== '') {
                $this->messages[] = 'Response body: ' . substr($responseBody, 0, 500);
            }
//            return false;
        }
        $details = json_decode($responseBody, true);
        if ($details === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->messages[] = 'JSON decode error: ' . json_last_error_msg();
        }
        if (JDEBUG) {
            echo '<b>Start of details</b><br>';
            var_dump($details);
            echo '<br><b>End of details</b><br>';
            echo $responseBody;
            echo '<br>========<br>';
 //           return;
            return $details;
        }
    }

    private function getSubscribers($event_id, $restart = 'N') {
//        $this->message .= 'getSubscribers mailshot_id=' . $mailshot_id . ', ';
// returns an array of users currently subscribed to the given list
        if ($event_id == '') {
            echo 'Event id is blank<br>';
            Factory::getApplication()->enqueueMessage('Event id is blank', 'error');
            return;
        }
        $sql = "SELECT DISTINCT b.id AS booking_id, e.id AS eventid, ";
//      $sql .= "u.name AS 'User', ";          
        $sql .= "u.id as user_id, u.email AS 'email' ";
        $sql .= 'FROM #__ra_mail_shots AS m ';
        $sql .= 'INNER JOIN `#__ra_events` AS e ON e.id = m.event_id ';
        $sql .= 'INNER JOIN #__ra_bookings AS b ON b.event_id = e.id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = b.user_id ';
        if ($restart == 'N') {
            $sql .= 'WHERE ';
        } else {
            $sql .= 'LEFT JOIN #__ra_mail_recipients AS mr ON mr.mailshot_id =m.id ';
            $sql .= 'AND u.id = mr.user_id ';
            $sql .= 'WHERE mr.id IS NULL ';
            $sql .= 'AND ';
        }
        $sql .= 'e.id=' . $event_id;

        $sql .= ' AND (b.state=0 OR b.state=1)';
        $sql .= ' AND u.block=0 AND u.requireReset=0';
        $sql .= ' ORDER BY u.email';
//        echo $sql;
//        $this->toolsHelper->showSql($sql);
//        die;
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        $db->execute();
        $rows = $db->loadObjectList();
        return $rows;
    }

    public function createLog ($record_type,$ref, $message){
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        $query->insert($db->quoteName('#__ra_logfile'))
            ->set('sub_system = ' . $db->quote('RA Events'))
            ->set('record_type = ' . $db->quote($record_type))
            ->set('ref = ' . $db->quote($ref))
            ->set('message = ' . $db->quote($message));
        $db->setQuery($query)->execute();
//        $this->toolsHelper->createLog('RA Events', $record_type, $ref, $message);
    }

    public function lookupContact($contact) {
// First see if a corresponding profile already exists with this name
        $contact_id = 0;
        if (trim($contact) !== '') {
            $sql = 'SELECT p.id FROM #__ra_profiles AS p ';
            $sql .= 'WHERE p.preferred_name= ' . $this->db->quote($contact) . '';
//            echo $sql . '<br>';
            $user_id = $this->toolsHelper->getValue($sql);
            if (is_null($user_id)) {
                $message = 'Profile not found for ' . $contact;
                $contact_id = null;
            } else {
                $sql = 'SELECT c.id FROM #__contact_details AS c ';
                $sql .= 'WHERE c.user_id= ' . $this->db->quote($user_id);
//                echo $sql . '<br>';
                $contact_id = $this->toolsHelper->getValue($sql);
            }
        }
        if (!is_null($contact_id)) {
            return $contact_id;
        }

// No matching contact, get details from config
        $params = ComponentHelper::getParams('com_ra_events');
        $contact_id = $params['default_contact'];
        if ($contact_id == '') {
            $contact_id = 1;
            Factory::getApplication()->enqueueMessage('Default contact not specified; please review configuration settings', 'error');
        }

// Generate a warning message
        if (is_null($user_id)) {
            $message = 'User not found for ' . $contact;
        } else {
            $message = 'No contact found for ' . $contact;
        }
        Factory::getApplication()->enqueueMessage($message, 'info');

//generate a warning email
//        $body = 'Group<b> ' . $event->group_code . '</b><br>';
//        $body .= 'Date<b> ' . HTMLHelper::_('date', $event->event_date, 'd/m/y') . '</b><br>';
//        $body .= 'Title<b> ' . $event->title . '</b><br>';
        $body .= 'Contact name<b> ' . $contact . '</b><br>';
        $body .= $params['default_message'] . '<br>';

        $to = $this->lookupContactEmail($contact_id);
        if ($to == '') {
            echo 'Email to be sent to ' . $to . '<br>';
            echo $body;
        } else {
            echo 'Email to be sent to ' . $to . '<br>';
            $subject = 'Shared Event without known Contact';
//            echo 'Email to be sent to ' . $subject . '<br>';
            $this->toolsHelper->sendEmail($to, $to, $subject, $body);
        }

        return $contact_id;
    }

    public function lookupContactEmail($contact_id) {
// Finds the email address from "Linked user" for the given contact
        $sql = 'SELECT u.email from #__contact_details AS c ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = c.user_id ';
        $sql .= 'WHERE c.id=' . $contact_id;
//       $email = $contact_id;
        return $this->toolsHelper->getValue($sql);
    }

    public function lookupContactid() {
// Sees if the current user has an associated Contact record
// Returns the appropriate Contact id, or  False
        $sql = 'SELECT c.id from #__contact_details AS c ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = c.user_id ';
        $sql .= 'WHERE u.id=' . $this->current_user_id;
        $contact_id = $this->toolsHelper->getValue($sql);
        return $contact_id;
    }

    public function lookupEvent($event_id) {
// Returns the Date/Name of the specified Event
        $sql = 'SELECT e.event_date, e.title from #__ra_events AS e ';
        $sql .= 'WHERE e.id=' . $event_id;
        $event = $this->toolsHelper->getItem($sql);
        $title = HTMLHelper::_('date', $event->event_date, 'd/m/y') . ' ' . $event->title;
        return $title;
    }

    public function menusDashboard() {
        $canDo = ContentHelper::getActions('com_ra_events');
        echo '<h3>Events</h3>' . PHP_EOL;
        echo '<ul>' . PHP_EOL;
        echo '<li><a href="index.php?option=com_ra_events&amp;view=events" target="_self">List of Events</a></li>' . PHP_EOL;
        if ($canDo->get('core.create')) {
            if (!ComponentHelper::isEnabled('com_ra_mailman', true)) {
                echo '<li><a href="index.php?option=com_ra_events&amp;view=profiles" target="_self">List of Profiles</a></li>' . PHP_EOL;
            }
            echo '<li><a href="index.php?option=com_ra_events&amp;view=bookings" target="_self">List of Bookings</a></li>' . PHP_EOL;
            echo '<li><a href="index.php?option=com_ra_events&amp;view=reports" target="_self">Event Reports</a></li>' . PHP_EOL;
            echo '<li><a href="index.php?option=com_ra_events&amp;view=dataload" target="_self">Import list of bookings</a></li>' . PHP_EOL;
        }
        if ($this->toolsHelper->isSuperuser()) {
            echo '<li><a href="index.php?option=com_ra_events&amp;view=eventtypes" target="_self">Event Types</a></li>' . PHP_EOL;
        }
        if ($canDo->get('core.admin')) {
            $versions = $this->toolsHelper->getVersions('com_ra_events');
            echo '<li><a href="index.php?option=com_config&view=component&component=com_ra_events" target="_self">';
            echo "Configure com_ra_events (version " . $versions->component . ")</a></li>" . PHP_EOL;
            echo '<li>(DB version is ' . $versions->db_version . ')</li>';
        }
        echo '</ul>' . PHP_EOL;
    } 
 
public function sendEmails($mailshot_id, $force='N') {
//        Normally invoked in batch mode, but can also be invoked from the back end.
        $bookingHelper = new BookingHelper; 
// Find the email address of the event organiser.
        $sql = 'SELECT ms.body, ms.title, ms.processing_started, ms.date_sent, e.id, u.email ';
        $sql .= 'FROM #__ra_mail_shots AS ms ';
        $sql .= 'INNER JOIN `#__ra_events` AS e ON e.id = ms.event_id ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'LEFT JOIN #__users AS u ON u.id = c.user_id ';
        $sql .= 'WHERE ms.id=' . $mailshot_id;
        $item = $this->toolsHelper->getItem($sql);
        $reply_to = $item->email;
        $event_id = $item->id;
        if (is_null($item->email)) {
            $message = 'No email address found for the organiser of event id ' . $event_id . '; mailshot cannot be sent';   
            Factory::getApplication()->enqueueMessage($message, 'error');
            return false;
        }
        if ($force == 'N') {
// Count the number of attendees.
            $attendee_count = $this->countAttendees($event_id);
            echo 'Attendees for event id ' . $event_id . ': ' . $attendee_count . '<br>';
            $max_online_send = 0; // could get from config $params->get('max_online_send', 100);

            if ($attendee_count > $max_online_send) {
                $this->updateOutstanding($event_id, $attendee_count);
                $message = 'Mailshot "' . $item->title . '" has been logged for dispatch and will be processed shortly.';
                Factory::getApplication()->enqueueMessage($message, 'info');
                return true;
            }
        }
    // Build the reusable header fragment for this event mailshot.
        $header = $this->emailHeader($event_id, '4');


     // See if the send is only part way through.
        echo $item->processing_started . ' ' . $item->date_sent . '<br>';
        if (is_null($item->date_sent)) {
            echo $item->date_sent . ' date_sent is null<br>';
        }
        if ($item->date_sent > '') {
            $this->messages[] = 'Mailshot "' . $item->title . '" was sent ' . $item->date_sent;
            $this->updateOutstanding($event_id, 0);
            return 0;
        }
//      Set up maximum time of 10 mins (should be parameter in config).
        $max = 10 * 60;
        set_time_limit($max);

        if (is_null($item->processing_started)) {
            $this->messages[] = 'Sending of Mailshot "' . $item->title . '" started at ' . date('d-M-Y H:i:s A');
// Save the status that processing has started.
            if (!$this->updateDate($mailshot_id, 'processing_started')) {
                $this->message .= ', Unable to update ProcessingDate';
                return 0;
            }
            $restart = false;
            
// Store the composed fragment on the mailshot record.
            $sql = 'UPDATE #__ra_mail_shots ';
            $sql .= 'SET final_message = ' . $this->db->quote($header . $item->body);
            $sql .= ' WHERE id=' . $mailshot_id;
            echo 'sql=' . $sql . '<br>';
            $this->toolsHelper->executeCommand($sql);   

            $attendees = $this->getSubscribers($event_id);
            $count_attendees = count($attendees);
            if ($count_attendees == 0) {
                $this->messages[] = 'No attendees to send to    ';
            }
        } else {
//          Send had started but not completed.
            $message = 'Sending of Mailshot "' . $item->title . '" restarting ' . $item->processing_started;
            $restart = true;
// Only get users who have not yet received their message.
            $attendees = $this->getSubscribers($event_id, 'Y');
            $count_attendees = count($attendees);
            $message .= ', ' . $count_attendees . ' users outstanding';
            $this->messages[] = $message;
        }
//        die('event_id: ' . $event_id . ' count_attendees:' . $count_attendees   . ' mailshot_id:' . $mailshot_id);
        $error_count = 0;
        $count = 0;
        $outstanding = $count_attendees;
        $current_email = '';

        echo 'Body: ' . $item->body . '<br>';
//        foreach ($attendees as $attendee) {
//            echo 'Attendee: ' . $attendee->email . ', user_id: ' . $attendee->user_id . '<br>';
////            echo $bookingHelper->lookupBooking($event_id, $attendee->email);
//            }    
//        die;
        foreach ($attendees as $attendee) {
            $count++;
            $outstanding--;
//            if ($restart) {
//                $this->messages[] = $count . ' ' . $attendee->email;
//            }
            // Check not already sent an email to this attendee.
            if ($attendee->email == $current_email) {
                $this->messages[] = 'Duplicate message for ' . $attendee->email;
            } else {
                $message = $header . $item->body;

                // Add a block with details of the attendee's booking.
                $message .= $bookingHelper->lookupBooking($event_id, $attendee->email);

                $current_email = $attendee->email;
//                $token = $this->encode($attendee->subscription_id, 0);
//                $link = $this->toolsHelper->buildLink($website_base . 'index.php?option=com_ra_mailman&task=mail_lst.processEmail&token=' . $token, 'Un-subscribe');
//                $message .= $this->footer . $link . '</div>';
                if (!$this->toolsHelper->sendEmail($attendee->email, $reply_to, $item->title, $message, $this->attachments)) {
                    $error_count++;
                }

                if ($outstanding % 10 == 0) {
                    $this->updateOutstanding($event_id, $outstanding);
                }
//                $this->createRecipent($event_id, $attendee->user_id);
//                if ($count >= $max_emails) {
//                    break;
//                }
            }
        }
        if ($error_count > 0) {
            $this->message .= ' ' . $error_count . ' Errors';
        }
        $this->updateOutstanding($event_id, $outstanding);
        $this->messages[] = ' Mailshot ' . $this->email_title . ' sent to ' . $count . ' users ';
        if ($outstanding == 0) {
            if (!$this->updateDate($mailshot_id, 'date_sent')) {
                $this->messages[] = ', Unable to update DateSent';
                return false;
            }
        } else {
            $this->messages[] = $outstanding . ' messages still outstanding';
        }
        return true;

    }

    public function showFirst($api_site_id, $events) {
        echo '<h2>First Shared event</h2>';
        $sql = 'SELECT * FROM #__ra_api_sites WHERE id=' . $api_site_id;

        $site = $this->toolsHelper->getItem($sql);
        $website = $site->url;
        $target = '&id=';
        ToolBarHelper::title('First Shared event for ' . $website);
        // Accept either the raw JSON payload (with a data key) or the data array directly.
        $payload = $events;
        if (isset($events['data']) && is_array($events['data'])) {
            $payload = $events['data'];
        }

        $count = is_array($payload) ? count($payload) : 0;
        echo $count . ' events returned<br>';

        if ($count === 0) {
            echo 'No events to display<br>';
        } else {
            $event = $payload[0];
            $eventId = isset($event['id']) ? $event['id'] : '';
            $eventType = isset($event['type']) ? $event['type'] : '';
            echo 'event id=' . $eventId . ', type=' . $eventType . '<br>';

            $attributes = isset($event['attributes']) && is_array($event['attributes']) ? $event['attributes'] : array();

            echo '<table class="table">';
            echo '<tr><th>Field</th><th>Value</th></tr>';
            $fieldCount = 0;
            foreach ($attributes as $key => $val) {
                $fieldCount++;
                if (is_array($val) || is_object($val)) {
                    $val = json_encode($val);
                }
                echo '<tr>';
                echo '<td>' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo $fieldCount . ' fields<br>';

            if (isset($attributes['contact_name'])) {
                echo 'contact_id=' . $this->lookupContact($attributes['contact_name']) . '<br>';
            }
        }
        $target = 'administrator/index.php?option=com_ra_tools&view=apisites';
        echo $this->toolsHelper->backButton($target);
        $target = 'administrator/index.php?option=com_ra_tools&task=apisites.refreshEvents&mode=1&id=' . $api_site_id;
        echo $this->toolsHelper->buildButton($target, 'Show all');
        $target = 'administrator/index.php?option=com_ra_tools&task=apisites.refreshEvents&mode=2&id=' . $api_site_id;
        echo $this->toolsHelper->buildButton($target, 'Refresh', false, 'red');
    }

    public function showShared($api_site_id, $events) {
// Invoked from ApisitesController.refresh
        echo '<h2>List of Shared events</h2>';
        $sql = 'SELECT * FROM #__ra_api_sites WHERE id=' . $api_site_id;

        $site = $this->toolsHelper->getItem($sql);
        $website = $site->url;
        $target = '&id=';
        ToolBarHelper::title('Shared events for ' . $website);
        $count = count($events);
        $toolsTable = new ToolsTable();
        $toolsTable->add_header('id,State,Date,Title,Group,Details,Bookable,Share_date,Contact');
        $target = $website . '/index.php?option=com_content&view=article&id=';
        $i = 0;
        foreach ($events as $event) {
            $id = $events[$i]['id'];
            $toolsTable->add_item($id);
            $attributes = (object) $event['attributes'];
            if (JDEBUG) {
                if ($i == 1) {
                    var_dump($attributes);
                    echo '<br>';
                }   
            }         
            $toolsTable->add_item($attributes->state);
            $toolsTable->add_item($attributes->event_date);
            $toolsTable->add_item($attributes->title);
            $toolsTable->add_item($attributes->group_code);
//         $text = $attributes[''];
//          $link = $toolsHelper->buildLink($target . $id, $title, true);        
            $text = strip_tags($attributes->details);
            $toolsTable->add_item(substr($text, 0, 100) . '...');
//            $toolsTable->add_item(substr($attributes->details, 0, 100) . '...');
            $toolsTable->add_item($attributes->bookable);
            $toolsTable->add_item(HTMLHelper::_('date', $attributes->share_date, 'd/M/y'));
            $toolsTable->add_item($attributes->contact_name . '/' . $this->lookupContact($attributes->contact_name));
            $i++;
            $toolsTable->generate_line();
        }

        $toolsTable->generate_table();
        echo $count . ' Events found<br>';
        $back = 'administrator/index.php?option=com_ra_tools&view=apisites';
        $back .= '&id=' . $api_site_id;
        echo $this->toolsHelper->backButton($back);
        $target = 'administrator/index.php?option=com_ra_tools&task=apisites.refreshEvents&mode=3&id=' . $api_site_id;
        echo $this->toolsHelper->buildButton($target, 'Show first');
    }

    public function storeShared($api_site_id, $events) {
// Invoked from ApisitesController.refresh and API / EventsCopyComand

        $i = 0;
        $insert_count = 0;
        $update_count = 0;
        $count = count($events);
        $sql_lookup = 'SELECT * FROM #__ra_events WHERE original_id=';
        $i = 0;
        foreach ($events as $event) {
            $original_id = $events[$i]['id'];
//           echo $i . ', id= ' . $events[$i]['id'] . '<br>';
            $attributes = (object) $event['attributes'];
            $contact_id = $this->lookupContact($attributes->contact_name);
            if ($attributes->state == '') {
                echo "Record $original_id has blank state<br>";
                $attributes->state = 0;
            }
            if (trim($attributes->event_date_end) == '') {
                $event_date_end = 'NULL';
            } else {
                $event_date_end = $this->db->quote($attributes->event_date_end);
            }
            if (trim($attributes->publication_date) == '') {
                $publication_date = 'NULL';
            } else {
                $publication_date = $this->db->quote($attributes->publication_date);
            }
//            echo 'end date ';
//            var_dump($event_date_end);
//            echo '<br>';
//            var_dump($this->db->quote($event_date_end));
//            die($event_date_end);
            $query = $this->db->getQuery(true);
            $query->set("original_id = " . $this->db->quote($attributes->id))
                    ->set("api_site_id = " . $this->db->quote($api_site_id))
                    ->set("contact_id = " . $this->db->quote($contact_id))
// now copy the rest of the fields unchanged
                    ->set("event_date = " . $this->db->quote($attributes->event_date))
                    ->set("event_date_end = " . $event_date_end)
                    ->set("event_time = " . $this->db->quote($attributes->event_time))
                    ->set("event_type_id = " . $this->db->quote($attributes->event_type_id))
                    ->set("title = " . $this->db->quote($attributes->title))
                    ->set("details = " . $this->db->quote($attributes->details))
                    ->set("reports = " . $this->db->quote($attributes->reports))
                    ->set("minutes = " . $this->db->quote($attributes->minutes))
                    ->set("group_code = " . $this->db->quote($attributes->group_code))
                    ->set("location = " . $this->db->quote($attributes->location))
                    ->set("url = " . $this->db->quote($attributes->url))
                    ->set("url_description = " . $this->db->quote($attributes->url_description))
                    ->set("attachments = " . $this->db->quote($attributes->attachments))
                    ->set("attachment_description = " . $this->db->quote($attributes->attachment_description))
                    ->set("publication_date = " . $publication_date)
                    ->set("shareable = " . $this->db->quote($attributes->shareable))
                    ->set("share_date = " . $this->db->quote($attributes->share_date))
                    ->set("bookable = " . $this->db->quote($attributes->bookable))
                    ->set("num_bookings = " . $this->db->quote((int) $attributes->num_bookings))
                    ->set("max_bookings = " . $this->db->quote($attributes->max_bookings))
                    ->set("notify_organiser = " . $this->db->quote($attributes->notify_organiser))
                    ->set("booking_info = " . $this->db->quote($attributes->booking_info))
//                    ->set("created = " . $this->db->quote($attributes->created))                                        -
//                    ->set("created_by = " . $this->db->quote($attributes->created_by))
//                    ->set("modified = " . $this->db->quote($attributes->modified))
//                    ->set("modified_by = " . $this->db->quote($attributes->modified_by))
                    ->set("state = " . $this->db->quote($attributes->state))
            ;
//            echo $sql_lookup . $this->db->quote($original_id) . '<br>';
            $row = $this->toolsHelper->getItem($sql_lookup . $this->db->quote($original_id));
            if (is_null($row)) {
                // Only insert if state is not 0 (unpublished records are not created, only updated if they exist)
                if ($attributes->state != 0) {
                    $query->insert('#__ra_events');
                    $result = $this->db->setQuery($query)->execute();
//                    echo $query . '<br>';
                    $insert_count++;
                }
            } else {
// Matching record has been found
                if ($row->details <> $attributes->details) {
                    echo 'Updating details for ' . $row->id . ' from ' . $row->details . ' to <b>' . $attributes->details . '</b><br>';
                    $update = 1;
                }

                $update = true;
//               if (JDEBUG) {
//                   echo 'Found ' . $row->id . '<br>';
//               }
                /*
                  if ($row->location <> $attributes->description) {
                  echo 'Updating description for ' . $row->id . ' from ' . $row->location . ' to <b>' . $attributes->location . '</b><br>';
                  $update = 1;
                  }

                 */
                if ($update) {
                    $update_count++;
                    $query->update('#__ra_events')
                            ->where('id=' . $row->id);
//                    echo $query . '<br>';
                    $result = $this->db->setQuery($query)->execute();
                }
            }
            $i++;
        }
        $this->messages[] = 'Number of Events ' . $count;
        if ($insert_count > 0) {
            $this->messages[] = 'Number of Events created ' . $insert_count;
        }
        if ($update_count > 0) {
            $this->messages[] = 'Number of Events updated ' . $update_count;
        }
    }

    public function today() {
// Returns current date, formatted correctly
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'));
        return substr($date->toSql(true), 0, 10);
    }

    private function updateDate($mailshot_id, $field) {
        $sql = 'UPDATE #__ra_mail_shots SET ' . $field . '=NOW(), ';
        $sql .= 'state=1 ';
        $sql .= 'WHERE id=' . (int) $mailshot_id;
        return $this->toolsHelper->executeCommand($sql);
    }  

    private function updateOutstanding($event_id, $value) {     
        $sql = 'UPDATE #__ra_events SET emails_outstanding=' . (int) $value ;
        $sql .= 'WHERE id=' . (int) $event_id;
        return $this->toolsHelper->executeCommand($sql);
    }  

}
