<?php
/**
 * @version    2.4.0
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 16/10/25 CB show custom fields
 * 11/11/25 CB special requests
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
//echo 'id  ' . $this->item->id . '<br>';
?>

<form
    action="<?php echo Route::_('index.php?option=com_ra_events&layout=edit&id=' . (int) $this->item->id); ?>"
    method="post" enctype="multipart/form-data" name="adminForm" id="booking-form" class="form-validate form-horizontal">

    <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'event')); ?>
    <?php
    echo HTMLHelper::_('uitab.addTab', 'myTab', 'event1', 'Details');
    echo '<div class="row-fluid">';
    echo '<div class="span10 form-horizontal">';
    echo '<fieldset class="adminform">';
    echo '<legend>Update Booking</legend>';

    echo $this->form->renderField('event_id');
    echo $this->form->renderField('user_id');
    echo $this->form->renderField('num_places');
    echo $this->form->renderField('partner');
    echo $this->form->renderField('special_request');
    echo $this->form->renderField('custom1');
    echo $this->form->renderField('custom2');
    echo '</fieldset>';
    echo '</div>';
    echo '</div>';
    echo HTMLHelper::_('uitab.endTab');
    echo HTMLHelper::_('uitab.addTab', 'myTab', 'event5', 'Publishing');
    echo '<div class="row-fluid">';
    echo '<div class="span10 form-horizontal">';
    echo '<fieldset class="adminform">';
    echo $this->form->renderField('state');
    echo $this->form->renderField('publication_date');
    echo $this->form->renderField('created');
    echo $this->form->renderField('creator');
    echo $this->form->renderField('confirmed');
    echo $this->form->renderField('confirmor');

    echo $this->form->renderField('cancelled');
    echo $this->form->renderField('cancellor');
    echo $this->form->renderField('id');
    echo '</fieldset>';
    echo '</div>';
    echo '</div>';
    echo HTMLHelper::_('uitab.endTab');
    ?>

</fieldset>
</div>
</div>


<input type="hidden" name="task" value=""/>
<?php echo HTMLHelper::_('form.token'); ?>

</form>
