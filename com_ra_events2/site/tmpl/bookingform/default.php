<?php
/**
 * @version    2.1.7
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 03/05/25 CB show name larger
 * 08/05/25 CB give error 404 if not logged in
 * 25/07/25 CB regenerated to allow both Add and Update
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');

// Load admin language file
$lang = Factory::getLanguage();
$lang->load('com_ra_events', JPATH_SITE);

$user = Factory::getApplication()->getIdentity();
//$canEdit = Ra_eventsHelper::canUserEdit($this->item, $user);

if ($this->item->state == 1) {
    $state_string = 'Publish';
    $state_value = 1;
} else {
    $state_string = 'Provisional';
    $state_value = 0;
}

$bookingHelper = new BookingHelper;
$toolsHelper = new ToolsHelper;

echo '<h3>' . $this->title . '</h3>';
// Find name of the User
echo $this->intro;
?>

<div class="booking-edit front-end-edit">

    <?php if (!$this->canEdit) : ?>
        <h3>
            <?php throw new \Exception(Text::_('COM_RA_EVENTS_ERROR_MESSAGE_NOT_AUTHORISED'), 403); ?>
        </h3>
    <?php else : ?>


        <form id="form-booking"
              action="<?php echo Route::_('index.php?option=com_ra_events&task=bookingform.save'); ?>"
              method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

            <input type="hidden" name="jform[id]" value="<?php echo isset($this->item->id) ? $this->item->id : ''; ?>" />

            <?php echo $this->form->getInput('created_by'); ?>
            <?php echo $this->form->getInput('modified_by'); ?>
            <div class="control-group">
                <?php echo $this->form->renderField('num_places'); ?>
                <?php echo $this->form->renderField('partner'); ?>
                <?php if ($this->canState == true): ?>
                    <div class="control-label"><?php echo $this->form->getLabel('state'); ?></div>
                    <div class="controls"><?php echo $this->form->getInput('state'); ?></div>
                <?php else: ?>
                    <div class="control-label"><?php echo $this->form->getLabel('state') . $state_string; ?></div>
                    <input type="hidden" name="jform[state]" value="<?php echo $state_value; ?>" />
                <?php endif; ?>
            </div>

            <?php echo $this->form->renderField('user_id'); ?>
            <?php echo $this->form->renderField('confirmed'); ?>
            <?php echo $this->form->renderField('event_id'); ?>
            <div class="control-group">
                <div class="controls">

                    <?php if ($this->canSave): ?>
                        <button type="submit" class="validate btn btn-primary">
                            <span class="fas fa-check" aria-hidden="true"></span>
                            <?php echo Text::_('JSUBMIT'); ?>
                        </button>
                    <?php endif; ?>
                    <a class="btn btn-danger"
                       href="<?php echo Route::_('index.php?option=com_ra_events&task=bookingform.cancel'); ?>"
                       title="<?php echo Text::_('JCANCEL'); ?>">
                        <span class="fas fa-times" aria-hidden="true"></span>
                        <?php echo Text::_('JCANCEL'); ?>
                    </a>
                </div>
            </div>

            <input type="hidden" name="option" value="com_ra_events"/>
            <input type="hidden" name="task"
                   value="bookingform.save"/>
                   <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php endif; ?>
</div>