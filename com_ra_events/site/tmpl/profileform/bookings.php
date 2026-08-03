<?php

/**
 * @version    2.3.5
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 26/07/25 CB show count of bookings
 * 06/08/25 CB show salutation & number of bookings
 * 01/10/25 CB show future and past events organised by this user
 * 03/11/25 CB pass menu_id to helper / showBookings
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');

// Load admin language file
$lang = Factory::getLanguage();
$lang->load('com_ra_events', JPATH_SITE);

$toolsHelper = new Toolshelper;
$bookingHelper = new BookingHelper;
$sql = 'SELECT preferred_name from #__ra_profiles WHERE id=' . $this->user->id;
$preferred_name = $toolsHelper->getValue($sql);
echo '<h2>Hi ' . $preferred_name . '</h2>';

echo $toolsHelper->showEvents($this->user->id);

// Find events on which the user has the booked
$sql = 'SELECT e.id, e.group_code, e.event_time, e.event_date, e.title, e.bookable, ';
$sql .= 'e.max_bookings, t.description ';
$sql .= 'FROM #__ra_events AS e ';
$sql .= 'INNER JOIN #__ra_bookings AS b ON b.event_id = e.id ';
$sql .= 'INNER JOIN #__ra_event_types AS t ON t.id = e.event_type_id ';
$sql .= 'WHERE b.user_id=' . $this->user->id;
$sql .= ' ORDER BY e.event_date DESC';
//echo "$sql<br>";
$rows = $toolsHelper->getRows($sql);
if (count($rows) == 0) {
    echo 'You have not yet made any bookings<br>';
} else {
    echo '<h2>Events you have booked on</h2>';
    $toolsTable = new ToolsTable;
    $toolsTable->add_header('Group,Date,Event,Type,Max');
    $rows = $toolsHelper->getRows($sql);
    foreach ($rows as $row) {
        $toolsTable->add_item($row->group_code);
        $date = $row->event_time . ' ' . HTMLHelper::_('date', $row->event_date, 'D d/m/y');
        $toolsTable->add_item($date);
        $toolsTable->add_item($row->title);
        $toolsTable->add_item($row->description);
//        $toolsTable->add_item($row->max_bookings);
// $bookable, $event_id, $callback, $buttons = true
        $bookings = $bookingHelper->showBookings($row->bookable, $row->id, $this->menu_id, '', false);
        $toolsTable->add_item($bookings);
        $toolsTable->generate_line();
    }
    $toolsTable->generate_table();
    if (count($rows) > 1) {
        echo count($rows) . ' Bookings<br>';
    }
}

//    Find past events
echo $toolsHelper->showEvents($this->user->id, 'N');
?>

