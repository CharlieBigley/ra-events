<?php

/**
 * @version    2.4.6
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 02/02/24 CB line break after Social / Location
 * 04/12/24 CB correct display of Location
 * 05/04/25 CB correct link to email, showBookings
 * 29/03/25 CB use BookingHelper to display literal
 * 16/06/25 CB If event is from a different site, show details of it in colour
 * 30/06/25 CB use layout passed as parameter for $back, Tools for email
 * 15/09/25 CB breadcrumbs, show remote attachments
 * 03/11/25 CB pass menu_id to helper / showBookings
 * 05/02/16 CB correction for remote attachments
 * 11/02/26 CB show message about site from which shared, if remote
 * 12/02/26 CB delete reference to non-existent field event_id, show group name
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

//$toolsHelper = new ToolsHelper;
$bookingHelper = new BookingHelper;
$app = Factory::getApplication();
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

// $mode will be blank if invoked from Social Events, or from the first column of List committee Meetings,
// but will be (A)genda, (R)eports or (M)inutes if invoked from specific columns of the List committee Meetings
$mode = $app->input->getCmd('mode', '');

if ($this->item->emails_outstanding > 0) {  
     $app->enqueueMessage('Mailshot waiting to be sent', 'info');
}
echo $this->showButtons();

$target_email = 'index.php?option=com_ra_tools&task=system.eventOrganiser&id=';
// Lookup the contact for the event
$sql = 'SELECT c.name FROM `#__ra_events` AS e ';
$sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
//    $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = e.contact_id ';
$sql .= 'WHERE e.id=' . $this->item->id;
$contact = $this->toolsHelper->getValue($sql);
//    echo $sql;

echo '<h3>' . $this->event_type . '</h3>';

if (is_null($this->item->api_site_id)) {
    echo '<div>';
} else {
    $sql = 'SELECT * FROM #__ra_api_sites WHERE id=' . $this->item->api_site_id;
    $site = $this->toolsHelper->getItem($sql);
    echo '<div style="background: ' . $site->colour . '; ">';
//    if (!is_null($this->item->api_site_id)) {
     echo '<i>Event shared by ' . $site->title . '</i><br>';
//}
}  

if ($this->event_type_id == 4) {  // Holiday
    echo '<h2>' . HTMLHelper::_('date', $this->item->event_date, 'd-M-y') . ' to ';
    echo HTMLHelper::_('date', $this->item->event_date_end, 'd-M-y');
} else {
    echo '<h2>' . HTMLHelper::_('date', $this->item->event_date, 'l d M y');
}
echo ' <b>' . $this->item->title . '</b></h2>';

/* printing commented out 13/02/23
  $target_print = "index.php?option=com_ra_events&view=event&tmpl=component&Itemid=" . $this->menu_id . '&id=';
  $target_print .= '&layout=' . $this->layout
  //      Show link that allows page to be printed
  echo $objHelper->showPrint($target_print);

 */
if ($this->item->location == "Zoom") {
    $location = ' using Zoom';
} else {
    $location = $this->item->location;
}
if ($this->event_type_id == 1) {

    switch ($mode) {
        case 'A':       // Just showing Agenda
            echo '<b>At</b> ' . $this->item->event_time . '<br>';
            echo '<b>Location</b> ' . $location . '<br>';
            echo '<h4>Agenda</h4>';
            if ($this->item->details == '') {
                echo '<i>(Not yet available)</i><br>';
            } else {
                echo '<p>' . $this->item->details . '</p>';
            }
//            echo $this->toolsHelper->backButton($back);
            return;
        case 'R':       // Just showing Reports
            echo '<b>At</b> ' . $this->item->event_time . '<br>';
            echo '<b>Location</b> ' . $location . '<br>';
            echo '<h4>Reports</h4>';
            if ($this->item->reports == '') {
                echo '<i>(Not yet available)</i><br>';
            } else {
                echo '<p>' . $this->item->reports . '</p>';
            }
            //echo $this->toolsHelper->backButton($back);
            return;
        case 'M':       // Just showing Minutes
            echo '<b>At</b> ' . $this->item->event_time . '<br>';
            echo '<b>Location</b> ' . $location . '<br>';
            echo '<h4>Minutes</h4>';
            if (trim($this->item->minutes) == '') {
                echo '<i>(Not yet available)</i><br>';
            } else {
                echo '<p>' . $this->item->minutes . '</p>';
                //echo $this->toolsHelper->backButton($back);
            }
            return;
        default:        // Blank
    }
}

if ($this->event_type_id == 4) {  // Holiday
    echo '<b>Location</b> ' . $this->item->location . '<br>';
} else {
    echo '<h4>Meeting at ' . $this->item->event_time . '</h4>';
    echo '<b>Location </b>';
    echo $location . '<br>';
}

if ($this->event_type_id == 1) {
    switch ($mode) {
        case 'A':       // Just showing Agenda
            echo '<h4>Agenda</h4>';
            echo '<p>' . $this->item->details . '</p>';
            break;
        case 'R':       // Just showing Reports
            echo '<h4>Reports</h4>';
            echo '<p>' . $this->item->reports . '</p>';
            break;
        case 'M':       // Just showing Minutes
            echo '<h4>Minutes</h4>';
            echo '<p>' . $this->item->minutes . '</p>';
            break;
        default:        // Blank
    }
} else {
    if ($this->item->contact_id > 0) {
        echo '<b>Contact</b> ' . $contact;
     // echo $this->toolsHelper->buildLink($target_email . $this->item->id, ToolsHelper::envelopeIcon,false);  (after tools 3.5.7)
        echo $this->toolsHelper->buildLink($target_email . $this->item->id, '<span class="icon-envelope" aria-hidden="true"></span>', false);
        echo '<br>';
    }
    echo '<h4>Details</h4>';
    echo '<p>' . $this->item->details . '</p>';
}
//

if ($this->event_type_id == '1') {
    echo '<h4>Agenda</h4>';
    if ($this->item->details == "") {
        echo "...(not present)";
    } else {
        echo $this->item->details;
    }
    echo '<h4>Reports</h4>';
    if ($this->item->reports == "") {
        echo "...(not present)";
    } else {
        echo $this->item->reports;
    }
    echo '<h4>Minutes</h4>';
    if ($this->item->details == "") {
        echo "...(not present)";
    } else {
        echo $this->item->minutes;
    }
}
if (is_null($this->item->api_site_id)) { 
    echo '<b>Group:</b> ' . $this->toolsHelper->lookupGroup($this->item->group_code) . '<br>'; 
} 
if (!$this->item->url == "") { 
    echo '<b>' . $this->item->url_description . '</b>' ; 
    echo $this->toolsHelper->buildLink($this->item->url, $this->item->url, True); 
} 
if ($this->item->attachments != "") { 
    echo '<b>' . $this->item->attachment_description . '</b> '; 
    if (is_null($this->item->api_site_id)) { 
        $target = Juri::Base(); 
    } else {
        $target = $site->url . '/';
    }
    $target .= $this->attachment_folder . "/" . $this->item->attachments;
    echo $this->toolsHelper->buildLink($target, $this->item->attachments, True);
}
echo '<br>';

// Display a literal with details of the current number of bookings, plus buttons
if ($this->layout == '') {
    $callback = $this->event_type_id;
} else {
    $callback = $this->layout;
}
echo $bookingHelper->showBookings($this->item->bookable, $this->item->id, $this->menu_id, $callback);
echo '</div>';                   // End of Colour div
//echo $this->toolsHelper->backButton($back);

