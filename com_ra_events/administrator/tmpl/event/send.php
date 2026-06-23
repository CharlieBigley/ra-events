<?php

/**
 * @version    2.5.0
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 04/04/26 CB created
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
//use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;

$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$eventHelper = new EventsHelper;
echo '<h3>' . $this->event_type . '</h3>';
if ($this->event_type_id == 4) {  // Holiday
    echo '<h2>' . HTMLHelper::_('date', $this->item->event_date, 'd-M-y') . ' to ';
    echo HTMLHelper::_('date', $this->item->event_date_end, 'd-M-y');
} else {
    echo '<h2>' . HTMLHelper::_('date', $this->item->event_date, 'l d M y');
}
echo ' <b>' . $this->item->title . '</b></h2>';

$sql = 'SELECT * FROM `#__ra_mail_shots` ';
$sql .= 'WHERE event_id=' . $this->item->id;
$sql .= ' ORDER BY id DESC LIMIT 1';
$mailshot = $this->toolsHelper->getItem($sql);
if ($mailshot->id > 0) {
    echo '<p>Last mailshot: <b>' . $mailshot->title . '</b><br>';
    echo 'Created=' . HTMLHelper::_('date', $mailshot->created, 'G:H:i d M Y');
    if (!is_null($mailshot->modified)) {
        echo ' (Modified ' . HTMLHelper::_('date', $mailshot->modified, 'G:H:i d M Y') . ')';
    }
    echo '<br>';
    if (!is_null($mailshot->date_sent)) {
        echo ' Sent ' . HTMLHelper::_('date', $mailshot->date_sent, 'G:H:i d M Y') ;
    }
    if (!is_null($mailshot->processing_started)) {
        echo ' (Started ' . HTMLHelper::_('date', $mailshot->processing_started, 'G:H:i d M Y') . ')';
    }
//  if ($mailshot->attachment) {
//      echo 'Attachment <a href="' . Uri::root() . 'media/com_ra_events/mailshots/' . $mailshot->attachment . '" target="_blank">[Attachment]</a>';
//  }
    echo '</p>';
    echo "Number of attendees: " . $eventHelper->countAttendees($this->item->id) . '<br>';
    echo 'Emails are currently scheduled to be dispatched by a batch job<br>';
    echo 'If you want to force the immediate send of the mailshot, click the button below.<br><br>';
    echo '<b>Wait until processing has finished - only click the button once</b><br><br>';
    $target = 'administrator/index.php?option=com_ra_events&task=event.forceSend&id=' . $mailshot->id;
    echo $this->toolsHelper->buildButton($target, 'Send Mailshot',false, 'red')    ;
} else {
    echo '<p>No mailshot created yet</p>';
}


$back = 'administrator/index.php?option=com_ra_events&view=events';
if (($this->event_type_id > 1) AND ($this->event_type_id < 5)) {
    $back .= '&layout=default';
} else {
    $back .= '&layout=committee'; // Committee Meetings / WM
}
echo $this->toolsHelper->backButton($back);