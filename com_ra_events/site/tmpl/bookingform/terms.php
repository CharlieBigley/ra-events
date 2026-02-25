<?php

/**
 * @version     2.2.7
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 27/09/25 CB Created
 * 25/02/26 CB show Terms from article if it exists, otherwise show default text
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
$toolsHelper = new ToolsHelper;
$title = 'Bookings: Terms and Conditions';
$sql = 'SELECT `introtext` from #__content WHERE title="' . $title . '"';

$introtext = $this->toolsHelper->getValue($sql);
if (is_null($introtext) OR ($introtext == '')) {
        echo '<h2>Terms of Use</h2>';
        echo 'We store details of your real name and email address for the purposes of communicating to you by email and for managing bookings that you make for Events.' . '<br><br>';
        echo 'In addition, we hold a "preferred name" of your choice, and this is shown above. '
        . 'The organiser of the Event will only be able to see this "preferred name" when using reports from the system.' . '<br><br>';
        echo 'We will never share your personal data with any other organisation. ' . '<br>';
 } else {
        echo $introtext . '<br>';
}

