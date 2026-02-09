<?php
/**
 * @version    2.4.4
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 21/03/25 CB created from committee
 * 17/06/25 CB don't show group if blank, show remote events in colour
 * 27/06/25 CB show bookable
 * 30/06/25 CB pass layout as parameter to Event
 * 01/10/25 CB use HTMLHelper, not JHtml
 * 26/01/19 CB use grid.sort for all columns in all.php for reliable sorting (searchtools.sort requires Search Tools bar)
 * NOTE: A lot of time was spent tracking down the sorting issue; the actual solution was to use grid.sort instead of searchtools.sort, as the latter requires the Search Tools bar to be present and active.
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

if ($this->event_type_id == '1') {
    $title = 'All ' . $this->event_type . 's' . $this->event_type_id;
} else {

    $title = 'All future ' . $this->event_type . 's';
}
echo '<h2>' . $title . '</h2>';
$toolsHelper = new ToolsHelper;
//$user = Factory::getApplication()->getSession()->get('user');
//$userId = $this->user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

//$target = "index.php?option=com_ra_events&view=event&tmpl=component&Itemid=" . $this->menu_id . '&id=';
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
$target_display = 'index.php?option=com_ra_events&view=event&Itemid=' . $this->menu_id;
$target_display .= '&layout=' . $this->layout . '&id=';
?>

<form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
      name="adminForm" id="adminForm">

    <div class="table-responsive">
        <table class="table table-striped" id="eventList">
            <thead>
                <tr>

                    <?php
                    echo '<th>';
                    echo HTMLHelper::_('grid.sort', 'Date', 'a.event_date', $listDirn, $listOrder);
                    echo ' </th>' . PHP_EOL;

                    echo '<th class="location">';
                    echo HTMLHelper::_('grid.sort', 'Type', 'event_type.description', $listDirn, $listOrder);
                    echo ' </th>' . PHP_EOL;

                    echo '<th class="title">';
                    echo HTMLHelper::_('grid.sort', 'Title', 'a.title', $listDirn, $listOrder);
                    echo ' </th>' . PHP_EOL;

                    echo '<th class="location">';
                    echo HTMLHelper::_('grid.sort', 'Organiser', 'c.name', $listDirn, $listOrder);
                    echo ' </th>' . PHP_EOL;

                    echo '<th class="location">';
                    echo HTMLHelper::_('grid.sort', 'Location', 'a.location', $listDirn, $listOrder);
                    echo ' </th>' . PHP_EOL;

                    echo '<th>';
//                    $image = '<img src="' . uri::Base() . 'media/com_ra_tools/link.png' . '" alt="URL" width="20" height="20" />';
                    echo HTMLHelper::_('grid.sort', 'Link', 'a.url', $listDirn, $listOrder);
                    echo '</th>' . PHP_EOL;

                    echo '<th class="left">';
//                    $image = '<span class="icon-paperclip"></span>';
                    echo HTMLHelper::_('grid.sort', 'Attach', 'a.attachments', $listDirn, $listOrder);
                    echo '</th>' . PHP_EOL;

                    echo '<th class="bookable">';
                    echo HTMLHelper::_('grid.sort', 'Bookable', 'a.bookable', $listDirn, $listOrder);
                    echo ' </th>' . PHP_EOL;
                    if ($this->show_group == 1) {
                        echo '<th class="left">';
                        echo HTMLHelper::_('grid.sort', 'Group', 'a.group_code', $listDirn, $listOrder);
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
                    $link = $target_display . $item->id;

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

                    echo '<td class="item-type">' . $item->event_type . '</td>';

                    echo '<td class="item-title"><a href = "';
                    //echo JRoute::_('index.php?option=com_ra_events&view=event&id=' . $item->id . '&Itemid=' . $this->menu_id) . '">';
                    echo Route::_($link) . '">';
                    echo $item->title;
                    echo '</a></td>' . PHP_EOL;
                    echo '<td>' . $item->name . '</td>' . PHP_EOL;
                    echo '<td class="item-location">' . $item->location . '</td>';
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
                        echo "attachments $item->attachment_description<br>";
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
                    echo '<td class="item-bookable">';
                    if ($item->bookable == '1') {
                        echo 'Y';
                    }
                    echo '</td>';
                    if ($item->group_code !== '') {

//                      If Event is from a different site, show contact name in colour
                        if (is_null($item->api_site_id)) {
                            echo '<td>';
                        } else {
                            $sql = 'SELECT colour FROM #__ra_api_sites WHERE id=' . $item->api_site_id;
                            $colour = $toolsHelper->getValue($sql);
                            echo '<td style="background: ' . $colour . '; ">';
                        }

                        echo $item->group_code . '</td>' . PHP_EOL;
                    }
                    echo '</tr>' . PHP_EOL;
                }
                ?>

            </tbody>
        </table>
    </div>

    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <input type="hidden" name="filter_order" value="<?php echo htmlspecialchars($listOrder); ?>"/>
    <input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars($listDirn); ?>"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>



