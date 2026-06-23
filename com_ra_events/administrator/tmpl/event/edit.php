<?php
/**
 * @version    2.5.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 02/12/24 change description to title, comment out display of this->header
 * 29/03/25 CB max_bookings
 * 09/06/25 CB remove hidden field state, show selection list
 * 16/06/25 CB make form read-only if imported from another site
 * 25/06/25 CB show api_site_id and original_id
 * 20/06/25 CB correct attachments,Show imported Events in a different colour
 * 25/07/25 CB ensure details can be edited
 * 06/08/25 CB pass additional parameter to bookingHelper->showBookings
 * 22/09/25 CB add custom fields
 * 05/11/25 CB don't show link for bookings
 * 03/04/26 CB new tab to show Mailshots
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;

$objHelper = new ToolsHelper;
$bookingHelper = new BookingHelper;
//$eventsHelper = new EventsHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
// Page introduction will have been set up in the View
echo $this->header;
$self = 'index.php?option=com_ra_events&layout=edit&id=' . (int) $this->item->id;
//echo "self=$self<br>";
//
$api_site_id = $this->form->getvalue('api_site_id');
if ($api_site_id > 0) {
    $toolsHelper = new ToolsHelper;
    $sql = 'SELECT url, colour FROM #__ra_api_sites WHERE id=' . $api_site_id;
    $site = $toolsHelper->getItem($sql);
    echo '<div style="background: ' . $site->colour . '; ">';
    echo "Shared from site $site->url<br>";
    $sql = 'SELECT name FROM #__contact_details WHERE id=' . $this->form->getvalue('contact_id');
    $contact = $toolsHelper->getValue($sql);
    echo "Contact is $contact<br>";
}
?>

<form
    action="<?php echo Route::_($self); ?>"
    method="post" enctype="multipart/form-data" name="adminForm" id="event-form" class="form-validate form-horizontal">

    <?php
    echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'event'));
    echo HTMLHelper::_('uitab.addTab', 'myTab', 'event1', 'Common fields');

    echo '<div class="row-fluid">';
    echo '<div class="span10 form-horizontal">';
    echo '<fieldset class="adminform">';
    if ($this->item->id == 0) {
        echo $this->form->renderField('event_type_id');
    }
    echo $this->form->renderField('event_date');
    if ($this->item->event_type_id == 4) { // Holidays
        echo $this->form->renderField('event_date_end');
    }
    echo $this->form->renderField('event_time');

    echo $this->form->renderField('title');
    echo $this->form->renderField('location');
    echo $this->form->renderField('contact_id');
    if ($this->show_group == 1) {
        echo $this->form->renderField('group_code');
    }
    //            echo $this->form->formField('event_date')->input;
    if ($this->item->id == 0) {
        echo $this->form->renderField('state');
    } else {
        echo $this->form->renderField('url');
        echo $this->form->renderField('url_description');
        echo $this->form->renderField('attachments');
        if (!empty($this->item->attachments)) {
            $attachmentsFiles = array();
            foreach ((array) $this->item->attachments as $fileSingle) {
                if (!is_array($fileSingle)) {
                    $target = Route::_(Uri::root() . 'faults' . DIRECTORY_SEPARATOR . $fileSingle, false);
                    echo $objHelper->buildLink($target, $fileSingle, true);
                    $imagesFiles[] = $fileSingle;
                }
            }
            echo '<input type="hidden" name="jform[attachment_hidden]" id="jform_attachment_hidden" value="' . implode(',', $imagesFiles) . '" />';
        }
        echo $this->form->renderField('attachment_description');
    }
    echo '</fieldset>';
    echo '</div>';
    echo '</div>';
    echo HTMLHelper::_('uitab.endTab');

    if ($this->item->event_type_id == 1) { // Committee meetings
        echo HTMLHelper::_('uitab.addTab', 'myTab', 'event2', 'Agenda');
        echo '<div class="row-fluid">';
        echo '<div class="span10 form-horizontal">';
        echo '<fieldset class="adminform">';
        if ((is_null($this->item->api_site_id)) or ($this->item->api_site_id == 0)) {
            echo $this->form->renderField('details');
        } else {
            echo $this->form->getvalue('details');
        }
        echo '</fieldset>';
        echo '</div>';
        echo '</div>';
        echo HTMLHelper::_('uitab.endTab');

        echo HTMLHelper::_('uitab.addTab', 'myTab', 'event3', 'Reports');
        echo '<div class="row-fluid">';
        echo '<div class="span10 form-horizontal">';
        echo '<fieldset class="adminform">';
        echo $this->form->renderField('reports');
        echo '</fieldset>';
        echo '</div>';
        echo '</div>';
        echo HTMLHelper::_('uitab.endTab');

        echo HTMLHelper::_('uitab.addTab', 'myTab', 'event4', 'Minutes');
        echo '<div class="row-fluid">';
        echo '<div class="span10 form-horizontal">';
        echo '<fieldset class="adminform">';
        echo $this->form->renderField('minutes');
        echo '</fieldset>';
        echo '</div>';
        echo '</div>';
        echo HTMLHelper::_('uitab.endTab');
    } else {
        if ($this->item->id > 0) {
            echo HTMLHelper::_('uitab.addTab', 'myTab', 'event2', 'Details');
            echo '<div class="row-fluid">';
            echo '<div class="span10 form-horizontal">';
            echo '<fieldset class="adminform">';
            if ((is_null($this->item->api_site_id)) or ($this->item->api_site_id == 0)) {
                echo $this->form->renderField('details');
            } else {
                echo $this->form->getvalue('details');
            }
            echo '</fieldset>';
            echo '</div>';
            echo '</div>';
            echo HTMLHelper::_('uitab.endTab');
            echo HTMLHelper::_('uitab.addTab', 'myTab', 'event2', 'Booking');
            echo '<div class="row-fluid">';
            echo '<div class="span10 form-horizontal">';
            echo '<fieldset class="adminform">';
            echo $this->form->renderField('bookable');
            echo $this->form->renderField('max_bookings');

            if (is_null($this->item->api_site_id)) {
                echo $this->form->renderField('notify_organiser');
                echo $this->form->renderField('booking1');
                echo $this->form->renderField('booking1_hint');
                echo $this->form->renderField('booking2');
                echo $this->form->renderField('booking2_hint');
                echo $this->form->renderField('booking_info');
            } else {
                echo '<h4>Booking information</h4>';
                echo $this->form->getvalue('booking_info');
            }
            //echo $bookingHelper->showBookings($this->item->bookable, $this->item->id, '', False);
            echo $bookingHelper->showBookingsAdmin($this->item->bookable, $this->item->id);
            echo '</fieldset>';
            echo '</div>';
            echo '</div>';
            echo HTMLHelper::_('uitab.endTab');
         if ($this->item->bookable  == '1') {
            echo HTMLHelper::_('uitab.addTab', 'myTab', 'event5', 'Mailshots');
            $sql = 'SELECT date_sent, title FROM #__ra_mail_shots WHERE event_id=' . $this->item->id;
            $mailshots = $objHelper->getRows($sql);
            if (count($mailshots) == 0) {
                echo '<p>No mailshots have been sent for this event</p>';
            } else {
                echo '<ul>';
                foreach ($mailshots as $mailshot) {
                    echo '<li>' . HTMLHelper::_('date', $mailshot->date_sent, Text::_('DATE_FORMAT_LC2')) . ' - ' . $mailshot->title . '</li>';
                }
                echo '</ul>';
            }
            echo HTMLHelper::_('uitab.endTab');
         }
        }
    }
    if ($this->item->id > 0) {
        echo HTMLHelper::_('uitab.addTab', 'myTab', 'event6', 'Publishing');
        echo '<div class="row-fluid">';
        echo '<div class="span10 form-horizontal">';
        echo '<fieldset class="adminform">';
        echo $this->form->renderField('state');
        echo $this->form->renderField('publication_date');
        echo $this->form->renderField('shareable');
        echo $this->form->renderField('share_date');
        echo $this->form->renderField('api_site_id');
        echo $this->form->renderField('original_id');
        echo $this->form->renderField('created');
        echo $this->form->renderField('created_by');
        echo $this->form->renderField('modified');
        echo $this->form->renderField('modified_by');
        echo $this->form->renderField('id');
        echo $this->form->renderField('event_type_id');
        echo '</fieldset>';
        echo '</div>';
        echo '</div>';
        echo HTMLHelper::_('uitab.endTab');
    }
    ?>


    <input type="hidden" name="jform[id]" value="<?php echo $this->item->id; ?>" />

    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

    <input type="hidden" name="task" value=""/>
    <?php
    echo HTMLHelper::_('form.token');
    echo '</form>';
    if ($api_site_id > 0) {
        echo '&nbsp;<br>';
        echo '</div>';
    }
    ?>


