<?php

/**
 * @version    4.1.10
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 10/10/24 CB created
 */
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_events\Site\Helpers\UserHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$objHelper = new ToolsHelper;

$objUserHelper = new UserHelper;
$objUserHelper->list_id = $this->list_id;
$objUserHelper->processing = $this->processing;
$objUserHelper->filename = $this->working_file;
//        $objUserHelper->purgeTestData();   // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<

$response = $objUserHelper->processFile();

// Redirect as appropriate
if ($response) {
    if ($this->processing == '0') {
        echo 'If you continue, updates will be applied to the database.<br>';
        $target = 'administrator/index.php?option=com_ra_events&view=dataload';
        echo $objHelper->buildButton($target, 'Cancel', False, 'granite');
        $target = 'administrator/index.php?option=com_ra_events&task=dataload.continue';
        echo $objHelper->buildButton($target, 'Continue', False, 'red');
    } else {
        // Flush the data from the session..
        Factory::getApplication()->setUserState('com_ra_events.edit.upload.data', null);

        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $objHelper->backButton($target);
    }
} else {
    $target = 'administrator/index.php?option=com_ra_events&view=dataload';
    echo $objHelper->backButton($target);
}




