<?php

/**
 * @version    2.1.9
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 26/07/25 CB show count of bookings
 * 06/08/25 CB show salutation & number of bookings
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
$sql = 'SELECT e.id, e.event_time, e.event_date, COUNT(b.id) as cnt, SUM(b.num_places) as num , ';
$sql .= 'e.title, e.max_bookings, e.notify_organiser, t.description ';
$sql .= 'FROM #__ra_events AS e ';
$sql .= 'INNER JOIN #__contact_details AS c ON c.id = e.contact_id ';
$sql .= 'INNER JOIN #__ra_event_types AS t ON t.id = e.event_type_id ';
$sql .= 'LEFT JOIN #__ra_bookings AS b ON b.event_id = e.id ';
$sql .= 'WHERE c.user_id=' . $this->user->id;
$sql .= ' GROUP BY e.id,e.event_date, e.title, e.max_bookings, e.notify_organiser, t.description';
$sql .= ' ORDER BY e.event_date DESC';

$rows = $toolsHelper->getRows($sql);
If (count($rows) > 0) {
    echo '<h2>Events you are organising</h2>';

    $objTable = new ToolsTable;
    $objTable->add_header("Date,Event,Type,Max places,Notify,Count bookings,Total places,Emails");
    $rows = $toolsHelper->getRows($sql);
    $sql = 'SELECT COUNT(id) as num ';
    $sql .= 'FROM #__ra_emails AS e ';
    $sql .= 'WHERE sub_system="RA Events" ';
    $sql .= 'AND ref=';
    foreach ($rows as $row) {

        $email_count = $toolsHelper->getValue($sql . $row->id);
        $date = $row->event_time . ' ' . HTMLHelper::_('date', $row->event_date, 'D d/m/y');
        $objTable->add_item($date);
        // TODO should be a link
        $objTable->add_item($row->title);
        $objTable->add_item($row->description);
        $objTable->add_item($row->max_bookings);
        if ($row->notify_organiser == 1) {
            $objTable->add_item('Yes');
        } else {
            $objTable->add_item('No');
        }
        $objTable->add_item($row->cnt);
        $objTable->add_item($row->num);

        if ($email_count == 0) {
            $objTable->add_item('');
        } else {
            $link = 'index.php?option=com_ra_events&Itemid=' . $this->menu_id;
            $link .= '&task=event.showEmails&id=' . $row->id;
            $link .= '&callback=profileform';
            $objTable->add_item($toolsHelper->buildLink($link, $email_count));
        }
        $objTable->generate_line();
    }
    $objTable->generate_table();
    if (count($rows) > 1) {
        echo count($rows) . ' Events<br>';
    }
}
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
    $objTable = new ToolsTable;
    $objTable->add_header('Group,Date,Event,Type,Max');
    $rows = $toolsHelper->getRows($sql);
    foreach ($rows as $row) {
        $objTable->add_item($row->group_code);
        $date = $row->event_time . ' ' . HTMLHelper::_('date', $row->event_date, 'D d/m/y');
        $objTable->add_item($date);
        $objTable->add_item($row->title);
        $objTable->add_item($row->description);
//        $objTable->add_item($row->max_bookings);
        // $bookable, $event_id, $callback, $buttons = true
        $bookings = $bookingHelper->showBookings($row->bookable, $row->id, '', false);
        $objTable->add_item($bookings);
        $objTable->generate_line();
    }
    $objTable->generate_table();
    if (count($rows) > 1) {
        echo count($rows) . ' Bookings<br>';
    }
}
?>

