<?php
/**
 * @version    2.4.4
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 21/11/23 CB combine pagination and search in table footer
 * 11/12/23 CB delete button to add new
 * 20/11/24 CB use icons instead of image
 * 02/12/24 CB change description to title, delete organiser
 * 04/12/24 CB revert to using image, not icon
 * 21/03/25 CB remove $can*
 * 30/06/25 CB pass layout as parameter to Event
 * 04/02/25 CB show 'Y' if agenda/reports/minutes exist, else show -
 * 05/02/26 CB Allow Committee Meetings to be sorted by Date
 * 05/02/16 CB correction for remote attachments
 */ 
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Session\Session;
use \Joomla\CMS\User\UserFactoryInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

echo '<h2>All Committee Meetings</h2>';
$user = Factory::getApplication()->getSession()->get('user');
$userId = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$target = "index.php?option=com_ra_events&view=event&tmpl=component&Itemid=" . $this->menu_id . '&id=';
$objHelper = new ToolsHelper();

// Find the next scheduled Event
// first find yesterday's date
date_default_timezone_set('Europe/London');  // just in case
$yesterday = date('Y-m-d', strtotime("-1 days"));

// then find the first meeting after that
$sql = "SELECT id from #__ra_events ";
$sql .= "WHERE (datediff(event_date, '" . $yesterday . "') > 0 ) ";
$sql .= "AND event_type_id=' . $this->event_type_id . ' AND state=1 ";
$sql .= "ORDER BY event_date ASC LIMIT 1";
$next_id = $objHelper->getValue($sql);
$target = 'index.php?option=com_ra_events&view=event&Itemid=' . $this->menu_id;
$target .= '&layout=' . $this->layout . '&id=';
?>

<form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
      name="adminForm" id="adminForm">

    <div class="table-responsive">
        <table class="table table-striped" id="eventList">
            <thead>
                <tr>

                    <?php
                    if ($this->event_type_id == 4) {  // Holiday
                        echo '<th class=>';
                        echo HTMLHelper::_('grid.sort', 'Date from', 'a.event_date', $listDirn, $listOrder);
                        echo '</th>';
                        echo '<th class=>';
                        echo HTMLHelper::_('grid.sort', 'Date to', 'a.event_date_end', $listDirn, $listOrder);
                        echo '</th>';
                    } else if ($this->event_type_id == 1) {  // Committee Meeting
                        echo '<th class=>';
                        echo HTMLHelper::_('grid.sort', 'Date from', 'a.event_date', $listDirn, $listOrder);
                        echo '</th>';
                        echo '<th>Time</th>' . PHP_EOL;
                    } else {
                        echo '<th>';
                        echo JHtml::_('grid.sort', 'Date', 'a.event_date', $listDirn, $listOrder);
                        echo ' </th>' . PHP_EOL;

                        echo '<th class="time">';
                        echo JHtml::_('grid.sort', 'Time', 'a.event_time', $listDirn, $listOrder);
                        echo ' </th>' . PHP_EOL;
                    }

                    echo '<th class="location">';
                    echo JHtml::_('grid.sort', 'Location', 'a.location', $listDirn, $listOrder);
                    echo ' </th>' . PHP_EOL;

                    echo '<th class="title">';
                    echo JHtml::_('grid.sort', 'Title', 'a.title', $listDirn, $listOrder);
                    echo ' </th>' . PHP_EOL;
                    if ($this->event_type_id == 1) {
                        echo '<th class="agenda">';
                        echo HTMLHelper::_('grid.sort', 'Agenda', 'a.details', $listDirn, $listOrder);
                        echo ' </th>' . PHP_EOL;

                        echo '<th class="reports">';
                        echo HTMLHelper::_('grid.sort', 'Reports', 'a.reports', $listDirn, $listOrder);
                        echo ' </th>' . PHP_EOL;

                        echo '<th class="minutes">';
                        echo HTMLHelper::_('grid.sort', 'Minutes', 'a.minutes', $listDirn, $listOrder);
                        echo ' </th>' . PHP_EOL;
                    }
                    echo '<th>';
                    $image = '<img src="' . uri::Base() . 'components/com_ra_tools/assets/link.png' . '" alt="U" width="20" height="20" />';
                    echo HTMLHelper::_('grid.sort', $image, 'a.url', $listDirn, $listOrder);
                    echo '</th>' . PHP_EOL;

                    echo '<th class="left">';
                    $image = '<span class="icon-paperclip"></span>';
                    echo HTMLHelper::_('grid.sort', $image, 'a.attachments', $listDirn, $listOrder);
                    echo '</th>' . PHP_EOL;

                    if ($this->show_group == 1) {
                        echo '<th class="left">';
                        echo JHtml::_('grid.sort', 'Group', 'a.group_code', $listDirn, $listOrder);
                        echo '</th>';
                    }
                    ?>



                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="3">
                        <div class="pagination">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </div>
                    </td>
                    <td colspan="3">
                        <?php
                        if (!empty($this->filterForm)) {
                            echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this));
                        }
                        ?>
                    </td>
                </tr>
            </tfoot>
            <tbody>
                <?php
                foreach ($this->items as $i => $item) {
                    $link = $target . $item->id;

                    echo '<tr class="row' . $i % 2 . '">';

                    // N.B. Joomla stores date as GMT, so it must be changed back into local time
                    echo '<td class="item-event_date">';
                    // Show the date in bold for the next Meeting due
                    if ($item->id == $next_id) {
                        echo '<b>';
                    }
                    // echo $date > 0 ? HTMLHelper::_('date', $date, 'D d F Y') : '-';
                    echo HTMLHelper::_('date', $item->event_date, 'D d-m-Y');
                    if ($item->id == $next_id) {
                        echo '</b>';
                    }
                    echo '</td>';
                    if ($this->event_type_id == 4) {  // Holiday
                        echo '<td class="item-event_date">';
                        echo HTMLHelper::_('date', $item->event_date_end, 'D d-m-y'); // Date
                        echo '</td>';
                    } else {
                        // Committee Meeting
                        echo '<td class="item-event_time">' . $item->event_time . '</td>';
                    }

                    echo '<td class="item-location">' . $item->location . '</td>';

                    echo '<td class="item-title"><a href = "';
                    //echo JRoute::_('index.php?option=com_ra_events&view=event&id=' . $item->id . '&Itemid=' . $this->menu_id) . '">';
                    echo Route::_($link) . '">';
                    echo $item->title;
                    echo '</a></td>' . PHP_EOL;

                    if ($this->event_type_id == 1) {

                        echo '<td class="item-details">';
                        if ((is_null($item->details)) OR (strlen($item->details) == 0) ) {
                            echo '-';
                        } else {
                            echo 'Y';
                            echo $objHelper->imageButton("I", $target . $item->id . '&mode=A');
                        }
                        echo '</td>';

                        echo '<td class="item-reports">';
                        if ((is_null($item->reports)) OR (strlen($item->reports) == 0) ) {
                            echo '-';
                        } else {
                            echo 'Y';
                            echo $objHelper->imageButton("I", $target . $item->id . '&mode=R');
                        }
                        echo '</td>';

                        echo '<td class="item-minutes">';
                        if ((is_null($item->minutes)) OR (strlen($item->minutes) == 0) ) {
                            echo '-';
                        } else {
                            echo 'Y';
                            echo $objHelper->imageButton("I", $target . $item->id . '&mode=M');
                        }
                        echo '</td>';
                    } else {
                        // via foreign key contact_id
                        $display = $item->contact;
                        echo '<td class="contact">' . $item->contact . '</td>';
                    }
                    echo '<td class="item-url">';
                    if (strlen($item->url) == 0) {
                        echo "-";
                    } else {
                        $label = $item->url_description;
                        if ($label == '') {
                            $label = 'Y';
                        }
                        echo $objHelper->buildLink($item->url, $label, true);
                    }
                    echo '</td>' . PHP_EOL;

                    echo '<td class="item-attachment">';
                    if (strlen($item->attachments) == 0) {
                        echo "-";
                    } else {
                        $label = $item->attachment_description;
                        if ($label == '') {
                            $label = 'Y';
                        }
                        if (is_null($this->item->api_site_id)) {
                            $target = Juri::Base();
                        } else {
                            $target = $site->url . '/';
                        }    
                        echo $objHelper->buildLink($target . $this->attachment_folder . '/' . $item->attachments, $label, true);
                    }
                    echo '</td>';
                    if ($this->show_group == 1) {
                        echo '<td>' . $item->group_code . '</td>' . PHP_EOL;
                    }
                    echo '</tr>' . PHP_EOL;
                }
                ?>

            </tbody>
        </table>
    </div>

    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <input type="hidden" name="filter_order" value=""/>
    <input type="hidden" name="filter_order_Dir" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php
if (0) {
    $wa->addInlineScript("
			jQuery(document).ready(function () {
				jQuery('.delete-button').click(deleteItem);
			});

			function deleteItem() {

				if (!confirm(\"" . Text::_('COM_RA_EVENTS_DELETE_MESSAGE') . "\")) {
					return false;
				}
			}
		", [], [], ["jquery"]);
}
?>
