<?php
/**
 * @version    2.5.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 09/10/3 CB record selection box
 * 30/11/24 CB don't show link for Agenda / Reports / Minutes
 * 01/12/24 CB only show group_code if show_group is set in component configuration
 * 02/12/24 CB attachments, change description to title
 * 05/12/24 CB show name of attachments
 * 16/01/25 CB show month name for date
 * 19/02/25 CB show read-only view if no edit access
 * 29/03/25 CB use BookingHelper to display details of bookings
 * 30/03/25 CB show Special
 * 16/06/25 CB If event is from a different site, show details of it in colour
 * 30/06/25 CB showBookingsAdmin
 * 06/04/26 CB link to force send if there are outstanding emails   
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$toolsHelper = new ToolsHelper;
$bookingHelper = new BookingHelper;
$user = Factory::getApplication()->getIdentity();
$userId = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
$canChange = True;
$saveOrder = $listOrder == 'a.event_date';
$target_send = 'administrator/index.php?option=com_ra_events&view=event&layout=send&id=';
?>

<form action="<?php echo Route::_('index.php?option=com_ra_events&view=events'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="eventList">
                    <thead>
                        <tr>
                            <th  scope="col" class="w-1 text-center">'  <?php echo HTMLHelper::_('grid.checkall'); ?></th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Date/Time', 'a.event_date', $listDirn, $listOrder); ?>
                            </th>

                            <?php
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Type', 'event_type.description', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Title', 'a.title', $listDirn, $listOrder) . '</th>';
                            echo '<th>Special</th>';
                            echo '<th>Bookings</th>';

                            echo '<th class="left">';
                            echo HTMLHelper::_('searchtools.sort', 'Attach', 'a.attachments', $listDirn, $listOrder);
                            echo '</th>' . PHP_EOL;
                            /*
                              this works on site view but uses grid.sort
                              echo '<th class="left">';
                              $image = '<span class="icon-paperclip"></span>';
                              echo HTMLHelper::_('searchtools.sort', $image, 'a.attachments', $listDirn, $listOrder);
                              echo '</th>' . PHP_EOL;
                             */
                            ?>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Contact', 'c.name', $listDirn, $listOrder); ?>
                            </th>

                            <?php
                            if ($this->show_group == 1) {
                                echo "<th class='left'>";
                                echo HTMLHelper::_('searchtools.sort', 'Group', 'a.group_code', $listDirn, $listOrder);
                                echo '</th>';
                            }
                            ?>

                            <th  scope="col" class="w-1 text-center">
                                <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                            </th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell" >
                                <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>					</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                                <?php echo $this->pagination->getListFooter(); ?>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php
                        foreach ($this->items as $i => $item) :
                            $canCreate = $user->authorise('core.create', 'com_ra_events');
                            $canEdit = $user->authorise('core.edit', 'com_ra_events');
                            $canCheckin = $user->authorise('core.manage', 'com_ra_events');
                            $canChange = $user->authorise('core.edit.state', 'com_ra_events');
                            ?>
                            <tr class="row<?php echo $i % 2; ?>" data-transition>
                                <td> <?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->name); ?></td>
                                <td>
                                    <?php
                                    $date = $item->event_date;
                                    //echo $date > 0 ? HTMLHelper::_('date', $date, Text::_('DATE_FORMAT_LC4')) : '-';
                                    echo $date > 0 ? HTMLHelper::_('date', $date, 'd M y') : '';
                                    echo ' ' . $item->event_time;
                                    ?>
                                </td>

                                <?php
                                echo '<td>' . $item->event_type . '</td>';
                                echo '<td>';
                                ?>
                                <?php
                                if ($canEdit) {
                                    echo '<a href="' . Route::_('index.php?option=com_ra_events&task=event.edit&id=' . (int) $item->id) . '">';
                                    echo $this->escape($item->title);
                                    echo '</a>';
                                    if ($item->event_type_id == 1) {
                                        echo ' Agenda ' . $item->details;
                                        echo ' Reports ' . $item->reports;
                                        echo ' Minutes ' . $item->minutes;
                                    }
                                } else {
                                    echo '<a href="' . Route::_('index.php?option=com_ra_events&view=event&id=' . (int) $item->id) . '">';
                                    echo $this->escape($item->title);
                                    echo '</a>';
                                }
                                echo '</td>';
                                $special = '';
                                if ($item->shareable == 1) {
                                    $special .= 'S ';
                                }
                                if ($item->bookable == 1) {
                                    $special .= 'B ';
                                }
                                if ($item->publication_to_go > 0) {
                                    $special .= 'P+' . $item->publication_to_go;
                                }
                                
                                if ($item->emails_outstanding > 0){ 
//                                    $caption = ToolsHelper::envelopeIcon();  // Only after tools 3.5.7
                                    $caption = '<span class="icon-envelope" aria-hidden="true"></span>';
                                    $special .= $toolsHelper->buildLink($target_send . $item->id,$caption);  
                                }
                                echo '<td>' . $special . '</td>';

                                $bookings = $bookingHelper->showBookingsAdmin($item->bookable, $item->id);
                                echo '<td>' . $bookings . '</td>';
                                echo '<td class="item-attachment">';
                                if (strlen($item->attachments) == 0) {
                                    echo "-";
                                } else {
                                    $label = $item->attachment_description;
                                    if ($label == '') {
                                        $label = 'Y';
                                    }
                                    //echo $objHelper->buildLink('../images/com_ra_events/' . $this->attachment_folder . '/' . $item->attachments, $label, true);
                                    echo $toolsHelper->buildLink('../images/com_ra_events/' . $item->attachments, $item->attachments, true);
                                }
                                echo '</td>';
//                              If Event is from a different site, show contact name in colour
                                if (is_null($item->api_site_id)) {
                                    echo '<td>';
                                } else {
                                    $sql = 'SELECT colour FROM #__ra_api_sites WHERE id=' . $item->api_site_id;
                                    $colour = $toolsHelper->getValue($sql);
                                    echo '<td style="background: ' . $colour . '; ">';
                                }
                                echo $item->name . '</td>';
                                if ($this->show_group == 1) {
                                    echo '<td>' . $item->group_code . '</td>';
                                }
                                ?>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'events.', $canChange, 'cb'); ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <?php echo $item->id; ?>

                                </td>


                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <input type="hidden" name="list[fullorder]" value="<?php echo $listOrder; ?> <?php echo $listDirn; ?>"/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>