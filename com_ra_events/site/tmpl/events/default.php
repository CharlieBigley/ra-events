<?php
/**
 * @version    2.1.9
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 21/11/23 CB Show "read more" if description too long
 * 11/12/23 CB delete button to add new
 * 23/01/24 CB truncate description if longer than
 * 02/02/24 CB correct duplicate title
 * 01/12/24 CB only show group_code if show_group is set in component configuration
 * 02/12/24 CB change description to title
 * 06/03/25 CB Show number of bookings
 * 16/06/25 CB If event is from a different site, show details of it in colour
 * 30/06/25 CB pass layout as parameter to Event, use Tools for email
 * 04/08/25 CB only show Group and Number of Bookings if values are present
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

if ($this->event_type_id == '1') {
    $title = 'All ' . $this->event_type . 's';
} else {

    $title = 'All future ' . $this->event_type . 's';
}
echo '<h2>' . $title . '</h2>';
$user = Factory::getApplication()->getSession()->get('user');
$userId = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
$canCreate = $user->authorise('core.create', 'com_ra_events') && file_exists(JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'eventform.xml');
$canEdit = $user->authorise('core.edit', 'com_ra_events') && file_exists(JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'eventform.xml');
$canCheckin = $user->authorise('core.manage', 'com_ra_events');
$canChange = $user->authorise('core.edit.state', 'com_ra_events');
$canDelete = $user->authorise('core.delete', 'com_ra_events');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$target = "index.php?option=com_ra_events&view=event&tmpl=component&Itemid=" . $this->menu_id . '&id=';
$target_email = 'index.php?option=com_ra_tools&task=system.eventOrganiser&id=';
$toolsHelper = new ToolsHelper();

// Find the next scheduled Event
// first find yesterday's date
date_default_timezone_set('Europe/London');  // just in case
$yesterday = date('Y-m-d', strtotime("-1 days"));

// then find the first meeting after that
$sql = "SELECT id from #__ra_events ";
$sql .= "WHERE (datediff(event_date, '" . $yesterday . "') > 0 ) ";
$sql .= "AND event_type_id='" . $this->event_type_id . "' AND state=1 ";
$sql .= "ORDER BY event_date ASC LIMIT 1";
$next_id = $toolsHelper->getValue($sql);
?>

<form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
      name="adminForm" id="adminForm">

    <div class="table-responsive">
        <table class="table table-striped" id="eventList">
            <thead>
                <tr>
                    <th class="date">Date</th>
                    <th class="details">Details</th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="2">
                        <div class="pagination">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </div>
                    </td>
                </tr>
            </tfoot>
            <tbody>
                <?php
                foreach ($this->items as $i => $item) {

                    $link = 'index.php?option=com_ra_events&view=event&id=' . $item->id . '&Itemid=' . $this->menu_id;
                    $link .= '&callback=' . $this->event_type_id;
                    echo '<tr class="row' . $i % 2 . '" valign="TOP">';

                    // N.B. Joomla stores date as GMT, so it must be changed back into local time
                    echo '<td style="vertical-align: top" class="item-event_date">';
                    // Show the date in bold for the next Event due
                    if ($item->id == $next_id) {
                        echo '<b>';
                    }
                    echo HTMLHelper::_('date', $item->event_date, 'd/m/y');
                    if ($this->event_type_id == 4) {  // Holiday
                        echo ' - ' . HTMLHelper::_('date', $item->event_date_end, 'd/m/y');
                    } else {
                        echo ' ' . $item->event_time;
                    }
                    // echo $date > 0 ? HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC4')) : '-';

                    if ($item->id == $next_id) {
                        echo '</b>';
                    }
                    echo '</td>';
                    if (is_null($item->api_site_id)) {
                        echo '<td class="item-title">';
                    } else {
                        $sql = 'SELECT colour FROM #__ra_api_sites WHERE id=' . $item->api_site_id;
                        $colour = $toolsHelper->getValue($sql);
                        echo '<td style="background: ' . $colour . '; ">';
                    }

                    echo '<a href = "';
                    echo Route::_($link) . '">';
                    echo rtrim($item->title) . PHP_EOL;                      // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
                    echo '</a><br>' . PHP_EOL;
                    if (!$item->location == '') {
                        echo '<b>Location </b>';
//. " at " . $item->location . '</h4>';
                        if ($item->location == "Zoom") {
                            echo ' using ';
                        }
                        echo $item->location . '<br>';
                    }
                    if ($item->contact_id > 0) {
                        echo '<b>Contact</b> ' . $this->lookupContact($item->contact_id);
                        echo $toolsHelper->buildLink($target_email . $item->id, '<span class="icon-envelope" aria-hidden="true"></span>', True);
                        echo '<br>';
                    }
                    $details = strip_tags($item->full_details);
                    if (strlen($item->full_details) > $this->max_chars) {
                        $details = strip_tags($item->full_details);
                        echo substr($item->full_details, 0, $this->max_chars);
                        echo $toolsHelper->buildLink($link, 'Read more', True, 'readmore');
                    } else {
                        echo $item->full_details;
                    }
                    echo '<br>';

                    if ($this->show_group == 1) {
                        $group = $toolsHelper->lookupGroup($item->group_code);
                        if (!is_null($group)) {
                            echo '<b>Group</b> ' . $group . '<br>';
                        }
                    }

                    if ($item->url != "") {
                        if ($item->url_description != '') {
                            echo '<b>' . $item->url_description . '</b>';
                        }
                        echo $toolsHelper->buildLink($item->url, $item->url, True);
                        echo '<br>';
                    }
                    if ($item->attachments != '') {
                        if ($item->attachment_description != "") {
                            echo '<b>' . $item->attachment_description . '</b>';
                        }
                        echo $toolsHelper->buildLink($this->attachment_folder . '/' . $item->attachments, $item->attachments, True);
                        echo '<br>';
                    }
                    if ($item->bookable == '1') {
                        $sql = 'SELECT SUM(num_places) FROM #__ra_bookings WHERE event_id=' . $item->id;
                        $sql .= ' AND ((state= 0) OR (state=1)) ';
                        $count = $toolsHelper->getValue($sql);
                        if ($count > 0) {
                            echo '<b>Number of bookings</b> ' . $count;
                        }
                    }
                    echo '</tr>' . PHP_EOL;
                }
                ?>

            </tbody>
        </table>
        <?php
        if (!empty($this->filterForm)) {
            echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this));
        }
        ?>
    </div>

    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <input type="hidden" name="filter_order" value=""/>
    <input type="hidden" name="filter_order_Dir" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

