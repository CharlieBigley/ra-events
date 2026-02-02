<?php
/**
 * @version    2.2.3
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 08/04/25 CB bookingsForUser
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

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->useStyle('com_ra_events.admin')
        ->useScript('com_ra_events.admin');

$helper = new BookingHelper;
$user = Factory::getApplication()->getIdentity();
$userId = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
$canOrder = $user->authorise('core.edit.state', 'com_ra_events');
?>

<form action="<?php echo Route::_('index.php?option=com_ra_events&view=profiles'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="profileList">
                    <thead>
                        <tr>
                            <th scope="col" class="w-3 d-none d-lg-table-cell" >

                                <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Group', 'a.home_group', $listDirn, $listOrder); ?>
                            </th>

                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Preferred name', 'p.preferred_name', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Real name', 'a.ame', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Email', 'a.email', $listDirn, $listOrder); ?>
                            </th>
                            <?php echo '<th>Bookings</th>'; ?>


                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                                <?php echo $this->pagination->getListFooter(); ?>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody <?php if (!empty($saveOrder)) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" <?php endif; ?>>
                        <?php
                        foreach ($this->items as $i => $item) :
                            $ordering = ($listOrder == 'a.ordering');
                            $canCreate = $user->authorise('core.create', 'com_ra_events');
                            $canEdit = $user->authorise('core.edit', 'com_ra_events');
                            $canCheckin = $user->authorise('core.manage', 'com_ra_events');
                            $canChange = $user->authorise('core.edit.state', 'com_ra_events');
                            ?>
                            <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>
                                <td class="d-none d-lg-table-cell">
                                    <a href="<?php echo Route::_('index.php?option=com_ra_events&task=profile.edit&id=' . (int) $item->id); ?>">
                                        <?php echo $item->id; ?>

                                </td>
                                <?php
                                echo '<td>' . $item->home_group . '</td>';
                                echo '<td>' . $item->preferred_name . '</td>';
                                echo '<td>' . $item->name . '</td>';
                                echo '<td>' . $item->email . '</td>';
                                echo '<td>' . $helper->bookingsForUser($item->id) . '</td>';
                                ?>
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