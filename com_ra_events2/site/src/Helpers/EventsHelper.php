<?php

/**
 * Contains functions used in the back end and the front end
 * @version    2.1.10
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 18/06/25 CB created
 * 10/07/25 CB delete reference to apisites
 * 22/07/25 CB add message to the header
 * 05/08/25 CB emailType
 * 28/08/25 CB use apisites from tools, not events
 */

namespace Ramblers\Component\Ra_events\Site\Helpers;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Uri\Uri;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

class EventsHelper {

    protected $app;
    protected $db;
    protected $canDo;
    protected $current_user_id;
    public $message;

    function __construct() {
        $this->app = Factory::getApplication();
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
        $this->message = '';
        $this->current_user_id = Factory::getApplication()->getSession()->get('user')->id;
        $this->toolsHelper = new ToolsHelper;
        $this->canDo = ContentHelper::getActions('com_ra_events');
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
        $params = ComponentHelper::getParams('com_ra_events');
//      Set the div for the header as a whole
        $header = '<div style="background-color: ' . $params->get('colour_header', 'rgba(20, 141, 168, 0.5)') . ';';
        $header .= ' height: ' . ($params->get('header_height')) . 'px; ';
        $header .= ' border-radius: 5%; padding: 10px; "';
        $header .= '>';

//      Set the div for the header text
        $header .= '<div style="float: left; ">';
        $header_text = $params->get('email_header', 'Send from RA Events');
        $header .= $header_text . '<br>';

// Add the text
        if ($record_type == '1') {
            $header .= 'Enquiry to organiser of:';
        } elseif ($record_type == '3') {
            $header .= 'New booking:';
        } else {
            $header .= 'Message to everyone booked onto:';
        }
        $header .= '<br><b>';
        $sql = 'SELECT title FROM #__ra_events WHERE id=' . $event_id;
        $header .= $this->toolsHelper->getValue($sql);
        $header .= '</b></div>';

//      Add the logo
        $logo = '/images/com_ra_events/logo.png';
        $logo_align = 'right';
        $image_data = file_get_contents(JPATH_ROOT . $logo);
        $encoded = base64_encode($image_data);
        $header .= '<a  href="' . $params->get('website') . '" >';
        $header .= "<img src='data:image/jpeg;base64,{$encoded}' style='float: ";
        $header .= $logo_align . ";'";
        $header .= ' height="' . $params->get('image_height') . 'px" width="' . $params->get('image_width') . 'px">';
        $header .= "</a>";

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

    public function lookupContact($event) {
// First see if a corresponding Contact already exists with this email
        $contact_id = 0;
        if (trim($event->contact_email) !== '') {
            $sql = 'SELECT u.email FROM #__users AS u ';
            $sql .= 'INNER JOIN #__contact_details AS c ON c.user_id = u.id ';
            $sql .= 'WHERE u.email= ' . $this->db->quote($event->contact_email);
            echo $sql;
            $contact_id = $this->toolsHelper->getValue($sql);
        }
        if ($contact_id > 0) {
            return $contact_id;
        }
//generate a warning email
        $params = ComponentHelper::getParams('com_ra_events');
        $body = 'Group<b> ' . $event->group_code . '</b><br>';
        $body .= 'Date<b> ' . HTMLHelper::_('date', $event->event_date, 'd/m/y') . '</b><br>';
        $body .= 'Title<b> ' . $event->title . '</b><br>';
//        $body .= 'Contact name<b>' . $event->contact_name . '</b><br>';
//        $body .= 'Contact email<b>' . $event->contact_email . '</b><br>';
        $body .= $params['default_message'] . '<br>';

// No matching contact, get details from config
        $contact_id = $params['default_contact'];
        if ($contact_id == '') {
            $contact_id = 1;
            echo 'Default contact not specified; please review configuration settings<br>';
        }
        $to = $this->lookupContactEmail($contact_id);
        if ($to == '') {
            echo 'Email to be sent to ' . $to . '<br>';
            echo $body;
        } else {
            echo 'Email to be sent to ' . $to . '<br>';
            $subject = 'Shared Event without known Contact';
            echo 'Email to be sent to ' . $subject . '<br>';
//            $this->toolsHelper->sendEmail($to, $to, $subject, $body);
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

    public function menusDashboard() {
        $canDo = ContentHelper::getActions('com_ra_events');
        echo '<h3>Events</h3>' . PHP_EOL;
        echo '<ul>' . PHP_EOL;
        echo '<li><a href="index.php?option=com_ra_events&amp;view=events" target="_self">List of Events</a></li>' . PHP_EOL;
        if ($canDo->get('core.create')) {
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
        $this > sendEmail($event->email, $event->email, $title, $body);
    }

    public function sendEmail($to, $reply_to, $subject, $body, $attachments = '') {
// Adds the component header to the given message
//        $header = $this->emailHeader();
        $this->toolsHelper->sendEmail($to, $reply_to, $title, $header . $body);
    }

    public function showFirst($api_site_id, $events) {
        echo '<h2>First Shared event</h2>';
        $sql = 'SELECT * FROM #__ra_api_sites WHERE id=' . $api_site_id;

        $site = $this->toolsHelper->getItem($sql);
        $website = $site->url;
        $target = '&id=';
        ToolBarHelper::title('First Shared event for ' . $website);
        $count = count($events);

        $objTable = new ToolsTable();
        $objTable->add_header('Field,Value');
        foreach ($events as $event) {
            $id = $events[$i]['id'];

            $attributes = (object) $event['attributes'];
            var_dump($attributes);
            foreach ($attributes as $key => $val) {
                echo '<tr>';
                echo "<td>$key</td><td>$val</td>";
                echo '</tr>';
            }
            echo '</table>';
            break;
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
        $objTable = new ToolsTable();
        $objTable->add_header('id,Date,Title,Group,Details,Bookable,Share_date,Contact name');
        $target = $website . '/index.php?option=com_content&view=article&id=';
        $i = 0;
        foreach ($events as $event) {
            $id = $events[$i]['id'];
            $objTable->add_item($id);
            $attributes = (object) $event['attributes'];
//            echo $i . '<br>';
//            var_dump($attributes);
//            echo '<br>';
            $objTable->add_item($attributes->event_date);
            $objTable->add_item($attributes->title);
            $objTable->add_item($attributes->group_code);
//         $text = $attributes[''];
//          $link = $objHelper->buildLink($target . $id, $title, true);
//          $objTable->add_item($link);
//            $text = strip_tags($attributes->details);
//            $objTable->add_item(substr($text, 0, 100) . '...');
            $objTable->add_item(substr($attributes->details, 0, 100) . '...');
            $objTable->add_item($attributes->bookable);
            $objTable->add_item(HTMLHelper::_('date', $attributes->share_date, 'd/M/y'));
            $objTable->add_item($attributes->contact_name);
            $i++;
            $objTable->generate_line();
        }

        $objTable->generate_table();
        echo $count . ' Events found<br>';
        $back = 'administrator/index.php?option=com_ra_tools&view=apisites';
        $back .= '&id=' . $api_site_id;
        echo $this->toolsHelper->backButton($back);
        $target = 'administrator/index.php?option=com_ra_tools&task=apisites.refreshEvents&mode=3&id=' . $api_site_id;
        echo $this->toolsHelper->buildButton($target, 'Show first');
    }

    public function storeShared($api_site_id, $events) {
// Invoked from ApisitesController.refresh
        echo '<h2>Updating database</h2>';
        $i = 0;
        $insert_count = 0;
        $update_count = 0;
        $count = count($events);
        $sql_lookup = 'SELECT id FROM #__ra_events WHERE original_id=';
//        $i = 0;
//        foreach ($events as $event) {
//            echo $i . ' ' . $events[$i]['id'] . '<br>';
//            $i++;
//        }
//        die;
        $i = 0;
        foreach ($events as $event) {
            $original_id = $events[$i]['id'];
//           echo $i . ', id= ' . $events[$i]['id'] . '<br>';
            $attributes = (object) $event['attributes'];
            $contact_id = $this->lookupContact($attributes);
            if ($attributes->state == '') {
                echo "Record $original_id has blank state<br>";
                $attributes->state = 0;
            }
            if (trim($attributes->event_date_end) == '') {
                $event_date_end = 'NULL';
            } else {
                $event_date_end = $this->db->quote($attributes->event_date_end);
            }
//            echo 'end date ';
//            var_dump($event_date_end);
//            echo '<br>';
//            var_dump($this->db->quote($event_date_end));
//            die($event_date_end);
            $query = $this->db->getQuery(true);
            $query->set("original_id = " . $this->db->quote($original_id))
                    ->set("api_site_id = " . $this->db->quote($api_site_id))
                    ->set("contact_id = " . $this->db->quote($contact_id))
// now copy the rest of the fields unchanged
                    ->set("event_date = " . $this->db->quote($attributes->event_date))
                    ->set("event_date_end = " . $event_date_end)
                    ->set("event_time = " . $this->db->quote($attributes->event_time))
                    ->set("event_time_end = " . $this->db->quote($attributes->event_time_end))
                    ->set("event_type_id = " . $this->db->quote($attributes->event_type_id))
                    ->set("title = " . $this->db->quote($attributes->title))
                    ->set("details = " . $this->db->quote($attributes->details))
                    ->set("reports = " . $this->db->quote($attributes->reports))
//                    ->set("agenda = " . $this->db->quote($attributes->agenda))
                    ->set("group_code = " . $this->db->quote($attributes->group_code))
                    ->set("location = " . $this->db->quote($attributes->location))
                    ->set("url = " . $this->db->quote($attributes->url))
                    ->set("url_description = " . $this->db->quote($attributes->url_description))
                    ->set("attachments = " . $this->db->quote($attributes->attachments))  // <<<<<
                    ->set("attachment_description = " . $this->db->quote($attributes->attachment_description))
                    ->set("publication_date = " . $this->db->quote($attributes->publication_date))
                    ->set("shareable = " . $this->db->quote($attributes->shareable))
                    ->set("share_date = " . $this->db->quote($attributes->share_date))
                    ->set("bookable = " . $this->db->quote($attributes->bookable))
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
                $query->insert('#__ra_events');
                $result = $this->db->setQuery($query)->execute();
//                echo $query . '<br>';
                $insert_count++;
            } else {
// Matching record has been found
                $update = true;
//               if (JDEBUG) {
//                   echo 'Found ' . $row->id . '<br>';
//               }
                /*
                  if ($row->details <> $attributes->details) {
                  echo 'Updating name for ' . $row->code . ' from ' . $row->name . ' to ' . $attributes->name . '<br>';
                  $update = 1;
                  }
                  if ($row->details <> $attributes->description) {
                  echo 'Updating description for ' . $row->code . ' from ' . $row->details . ' to ' . $attributes->description . '<br>';
                  $update = 1;
                  }
                  if ($row->co_url <> $attributes->url) {
                  echo 'Updating co_url for ' . $row->code . ' from ' . $row->co_url . ' to ' . $attributes->url . '<br>';
                  $update = 1;
                  }
                  if ($row->website <> $attributes->external_url) {
                  echo 'Updating website for ' . $row->code . ' from ' . $row->website . ' to ' . $attributes->external_url . '<br>';
                  $update = 1;
                  }
                  if ($row->latitude <> $attributes->latitude) {
                  echo 'Updating latitude for ' . $row->code . ' from ' . $row->latitude . ' to ' . $attributes->latitude . '<br>';
                  $update = 1;
                  }
                  if ($row->longitude <> $attributes->longitude) {
                  //                        echo $attributes->group_code . ': ' . 'Updating longitude for row ' . $row->id . ' from ' . $row->longitude . ' to ' . $attributes->longitude . '<br>';
                  echo 'Updating longitude for ' . $row->code . ' from ' . $row->longitude . ' to ' . $attributes->longitude . '<br>';
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
        echo 'Number of Events ' . $count . '<br>';
        echo 'Number of Events created ' . $insert_count . '<br>';
        echo 'Number of Events updated ' . $update_count . '<br>';
        $back = 'administrator/index.php?option=com_ra_tools&view=apisites';
        $back .= '&id=' . $api_site_id;
        echo $this->toolsHelper->backButton($back);
    }

    public function today() {
// Returns current date, formatted correctly
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'));
        return substr($date->toSql(true), 0, 10);
    }

}
