<?php
/**
 * @version     2.3.4
 * @package     com_ra_events
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 25/09/23 CB created from com ra_tools
 * 13/03/25 CB showAwaitingPublication
 * 08/04/25 CB datesToGo
 * 17/06/25 CB sharedEvents
 * 30/06/25 CB show Emails
 * 01/07/25 CB importeddEvents
 * 07/07/25 CB breadcrumbs
 * 11/07/25 CB contactsReport
 * 15/07/25 CB delete report for emails
 * 15/09/25 CB delete report for imported events
 * 13/10/25 bookingSummary
 */
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$toolsHelper = new ToolsHelper;
ToolBarHelper::title('Reports for Events');

// Import CSS
$this->wa = $this->document->getWebAssetManager();
$this->wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$back = 'administrator/index.php?option=com_ra_tools&view=dashboard';
$breadcrumbs = $toolsHelper->buildLink('administrator/index.php', 'Home Dashboard');
$breadcrumbs .= '>' . $toolsHelper->buildLink($back, 'RA Dashboard');
echo $breadcrumbs;

$reports = [
    'Shared Events' => 'administrator/index.php?option=com_ra_events&task=reports.sharedEvents',
    'Events waiting for publication' => 'administrator/index.php?option=com_ra_events&task=reports.showAwaitingPublication',
    'Events by Days-to-go' => 'administrator/index.php?option=com_ra_events&task=reports.datesToGo',
    'Events by Month' => 'administrator/index.php?option=com_ra_events&task=reports.showEventsByMonth',
    'Events by Type' => 'administrator/index.php?option=com_ra_events&task=reports.showEventsByType',
    'Events by Group' => 'administrator/index.php?option=com_ra_events&task=reports.showEventsByGroup',
    'Provisional bookings' => 'administrator/index.php?option=com_ra_events&task=reports.provisionalBookings',
    'Booking Summary' => 'administrator/index.php?option=com_ra_events&task=reports.bookingSummary',
    'Bookings by User' => 'administrator/index.php?option=com_ra_events&task=reports.bookingsByUser',
    'Contacts report' => 'administrator/index.php?option=com_ra_events&task=reports.contactsReport',
];
?>

<form action="<?php echo Route::_('index.php?option=com_ra_events&view=reports'); ?>" method="post" name="reportsForm" id="reportsForm">
    <div id="j-main-container" class="span10">
        <div class="clearfix"> </div>
        <?php
        echo '<ul>';
        foreach ($reports as $caption => $task) {
            echo '<li>' . $toolsHelper->buildLink($task, $caption) . '</li>';
        }
        echo '</ul>';

        echo $toolsHelper->backButton($back);
        ?>
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</div>
</form>
<?php
echo "<!-- End of code from ' . __file . ' -->" . PHP_EOL;
