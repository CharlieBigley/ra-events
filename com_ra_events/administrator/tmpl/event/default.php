<?php

/**
 * @version    2.4.6
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 19/02/25 CB copied from site
 * 11/04/25 CB show number of bookings
 * 30/06/25 CB Show imported Events in a different colour
 * 06/08/25 CB pass additional parameter to bookingHelper->showBookings
 * 12/02/26 CB delete reference to non-existent field event_id
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
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;

$objHelper = new ToolsHelper;
$bookingHelper = new BookingHelper;
$objApp = JFactory::getApplication();
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
// $mode will be blank if invoked from Social Events, or from the first column of List committee Meetings,
// but will be (A)genda, (R)eports or (M)inutes if invoked from specific columns of the List committee Meetings
$mode = $objApp->input->getCmd('mode', '');
$back = 'administrator/index.php?option=com_ra_events&view=events';
if (($this->event_type_id > 1) AND ($this->event_type_id < 5)) {
    $back .= '&layout=default';
} else {
    $back .= '&layout=committee'; // Committee Meetings / WM
}
echo $this->toolsHelper->backButton($back);

// Lookup the contact for the event
$sql = 'SELECT c.name FROM `#__ra_events` AS e ';
$sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
$sql .= 'WHERE e.id=' . $this->item->id;
$contact = $this->toolsHelper->getValue($sql);
//    echo $sql;

echo '<h3>' . $this->event_type . '</h3>';
if ($this->event_type_id == 4) {  // Holiday
    echo '<h2>' . HTMLHelper::_('date', $this->item->event_date, 'd-M-y') . ' to ';
    echo HTMLHelper::_('date', $this->item->event_date_end, 'd-M-y');
} else {
    echo '<h2>' . HTMLHelper::_('date', $this->item->event_date, 'l d M y');
}
echo ' <b>' . $this->item->title . '</b></h2>';
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
            echo $this->toolsHelper->backButton($back);
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
            echo $this->toolsHelper->backButton($back);
            return;
        case 'M':       // Just showing Minutes
            echo '<b>At</b> ' . $this->item->event_time . '<br>';
            echo '<b>Location</b> ' . $location . '<br>';
            echo '<h4>Minutes</h4>';
            if (trim($this->item->minutes) == '') {
                echo '<i>(Not yet available)</i><br>';
            } else {
                echo '<p>' . $this->item->minutes . '</p>';
                echo $this->toolsHelper->backButton($back);
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
echo $bookingHelper->showBookings($this->item->bookable, $this->item->id, '', False);
if (!$this->item->url == "") {
    echo '<h4>' . $this->item->url_description . '</h4>';
    echo $this->toolsHelper->buildLink($this->item->url, $this->item->url, True);
}
if ($this->item->attachments != "") {
    echo '<h4>' . $this->item->attachment_description . '</h4>';
    $target = Juri::Base() . $this->attachment_folder . "/" . $this->item->attachments;
    echo $this->toolsHelper->buildLink($target, $this->item->attachments, True);
}
echo '<br>';
echo $this->toolsHelper->backButton($back);

