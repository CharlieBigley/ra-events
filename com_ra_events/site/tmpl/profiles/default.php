<?php
/**
 * @version    2.4.7
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 07/05/25 CB show partner
 * 07/07/25 CB use sub-query, multiBook
 * 21/07/25 CB use $this->user
 * 04/02/26 CB change multibook butoon to form_submit
 * 25/02/26 CB don't show Multibook if custom gfileds are present
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
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers.css', 'com_ta_tools/css/ramblers.css');

$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
echo '<h2>Selecting Bookings for <i>' . $this->event_title . '</i></h2>';
  
$bookingHelper = new BookingHelper;
$canCreate = $this->user->authorise('core.create', 'com_ra_events') && file_exists(JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'profileform.xml');
$canEdit = $this->user->authorise('core.edit', 'com_ra_events') && file_exists(JPATH_COMPONENT . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'profileform.xml');
$canCheckin = $this->user->authorise('core.manage', 'com_ra_events');
$canChange = $this->user->authorise('core.edit.state', 'com_ra_events');
$canDelete = $this->user->authorise('core.delete', 'com_ra_events');

//echo 'url ' . htmlspecialchars(Uri::getInstance()->toString()) . '<br>';
?>

<form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
      name="adminForm" id="adminForm">
          <?php
          if (!empty($this->filterForm)) {
              echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this));
          }
          ?>
    <div class="table-responsive">
        <table class="table table-striped" id="profileList">
            <thead>
                <tr>
                    <th  scope="col" class="w-1 text-center">'  <?php echo HTMLHelper::_('grid.checkall'); ?></th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'Group', 'p.home_group', $listDirn, $listOrder); ?>
                    </th>

                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'Name', 'p.preferred_name', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'Other', 'b.partner', $listDirn, $listOrder); ?>
                    </th>
                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'Status', 'b.title', $listDirn, $listOrder); ?>
                    </th>
                    <?php
                    echo '<th class="left">Action</th>';
                    ?>


                    <th class=''>
                        <?php echo HTMLHelper::_('grid.sort', 'id', 'a.id', $listDirn, $listOrder); ?>
                    </th>

                </tr>
            </thead>
            <tfoot>
                <tr>
                    <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                        <div class="pagination">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </div>
                    </td>
                </tr>
            </tfoot>
            <tbody>
                <?php
                foreach ($this->items as $i => $item) :
                    $action = '';

                    // See if this user is currently booked
                    $check_visible = false;
                    $target = 'index.php?option=com_ra_events&task=profiles.';
                    if (is_null($item->state)) {
                        // no subscription record found
                        $label = 'Book';
                        $action = 'createBooking';
                        $colour = 'sunrise';
                        $check_visible = true;
                    } elseif ($item->state == 0) {
                        $label = 'Confirm';
                        $action = 'confirmBooking';
                        $colour = 'sunrise';
                    } elseif ($item->state == 1) {
                        $label = 'Cancel';
                        $action = 'cancelBooking';
                        $colour = 'red';
                    } elseif ($item->state == -2) {
                        $label = 'Re-instate';
                        $action = 'confirmBooking';
                        $colour = 'red';
                    } elseif ($item->state == '') {

                    }

                    $target .= $action . '&user_id=' . $item->id;
                    if ($label == 'Book') {
                        $organiser_id = 0;
                    } else {
                        $target .= '&id=' . $item->booking_id;
                        // Get the organiser of this event
                        $organiser_id = $bookingHelper->lookupOrganiser($item->booking_id);
                    }
                    $target .= '&event_id=' . $this->event_id;
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="text-center">
                            <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                        </td>
                        <?php
                        echo '<td>' . $item->home_group . '</td>';
                        if ($item->preferred_name == '') {
                            $name = $item->name;
                        } else {
                            $name = $item->preferred_name;
                        }
                        echo '<td>' . $name;
                        if ($item->requireReset == 1) {
                            echo '<span class="icon-warning"></span>';
                        }
                        echo '</td>';
                        echo '<td>' . $item->partner . '</td>';
//                        echo '<td>' . $item->creator . '</td>';
//                                // Count how many bookings this user has
//                                $sql = 'SELECT COUNT(id) FROM #__ra_bookings WHERE user_id=' . $item->id;
//                                $count = $this->objHelper->getValue($sql);
//                                echo '<td>' . $count . '</td>';

                        echo '<td>' . $item->title . '</td>';

                        echo '<td>';
                        if ($item->requireReset == 0) {
                            if (($organiser_id == $item->id)) {
                                echo '<b>Organiser</b>';
                            } else {
                                echo $this->toolsHelper->buildButton($target, $label, false, $colour);
                            }
                        }
                        echo '</td>';
                        echo '<td>' . $item->id . '</td>';
                        echo '</tr>';
                        ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <input type="hidden" name="filter_order" value=""/>
    <input type="hidden" name="filter_order_Dir" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<div class="controls">
     <?php if ($this->multibook) : ?>               
    <button class="btn btn-primary" onclick="Joomla.submitform('profiles.multiBook', document.getElementById('adminForm'));">
        <span class="fas fa-check" aria-hidden="true"></span>
        <?php echo Text::_('Multi-book'); ?>
    </button>
    <?php endif; ?>
    <a class="btn btn-danger"
       href="<?php echo Route::_('index.php?option=com_ra_events&task=profiles.cancel'); ?>"
       title="<?php echo Text::_('JCANCEL'); ?>">
        <span class="fas fa-times" aria-hidden="true"></span>
        <?php echo Text::_('JCANCEL'); ?>
    </a>
</div>
