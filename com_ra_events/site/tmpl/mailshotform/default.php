<?php
/**
 * @version    2.5.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 30/03/26 GPT copied from com_ra_mailman
 * 07/04/26 CB correct destination of Cancel etc
 */

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');

// Load admin language file
$lang = Factory::getLanguage();
$lang->load('com_ra_events', JPATH_SITE);
$toolsHelper = new ToolsHelper;
?>

<div class="mailshot-edit front-end-edit">
    <?php if (!$this->canEdit) : ?>
        <h3>
            <?php throw new \Exception(Text::_('COM_RA_EVENTS_ERROR_MESSAGE_NOT_AUTHORISED'), 403); ?>
        </h3>
    <?php else : ?>
        <?php if (!empty($this->item->id)): ?>
            <h2><?php echo 'Updating Mailshot'; ?>
            <?php else: ?>
                <h2><?php echo 'Creating Mailshot'; ?>
                <?php endif; ?>
                <?php echo ' for ' . $this->list_name; ?> </h2>
            <form id="form-mailshot"
                  action="<?php echo Route::_('index.php?option=com_ra_events&task=mailshotform.save'); ?>"
                  method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

                <input type="hidden" name="jform[id]" value="<?php echo isset($this->item->id) ? $this->item->id : ''; ?>" />

                <input type="hidden" name="jform[state]" value="<?php echo isset($this->item->state) ? $this->item->state : ''; ?>" />

                <?php 
                echo $this->form->getInput('created_by'); 
                echo $this->form->getInput('modified_by'); 
                echo $this->form->renderField('title');
                echo $this->form->renderField('body');
                echo $this->form->renderField('attachment');
                echo $this->form->renderField('event_id');
                echo $this->form->renderField('record_type');  
                ?>
                <?php if (!empty($this->item->attachment)) : ?>
                    <?php $attachmentFiles = array(); ?>
                    <?php foreach ((array) $this->item->attachment as $fileSingle) : ?>
                        <?php if (!is_array($fileSingle)) : ?>
                            <a href="<?php echo Route::_(Uri::root() . 'images/com_ra_mailman' . DIRECTORY_SEPARATOR . $fileSingle, false); ?>"><?php echo $fileSingle; ?></a> |
                            <?php $attachmentFiles[] = $fileSingle; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="jform[attachment_hidden]" id="jform_attachment_hidden" value="<?php echo implode(',', $attachmentFiles); ?>" />
                <?php endif; ?>
                <?php //echo HTMLHelper::_('uitab.endTab'); ?>
                <div class="control-group">
                    <div class="controls">
                        <?php if ($this->canSave): ?>
                            <button type="submit" class="validate btn btn-primary" name="save_quit">
                                <span class="fas fa-check" aria-hidden="true"></span>
                                <?php echo Text::_('Save & Close'); ?>
                            </button>
                            <button type="submit" class="validate btn btn-success" name="save_continue" onclick="this.form.task.value='mailshotform.savecontinue';">
                                <span class="fas fa-save" aria-hidden="true"></span>
                                <?php echo Text::_('Save & Continue'); ?>
                            </button>
                        <?php endif; ?>
                        <a class="btn btn-danger"
                           href="<?php echo Route::_('index.php?option=com_ra_events&task=mailshotform.cancel'); ?>"
                           title="<?php echo Text::_('JCANCEL'); ?>">
                            <span class="fas fa-times" aria-hidden="true"></span>
                            <?php echo Text::_('JCANCEL'); ?>
                        </a>
                    </div>
                </div>

                  <input type="hidden" name="option" value="com_ra_events"/>
                  <input type="hidden" name="task" value="mailshotform.save"/>
                  <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        <?php endif; ?>
</div>
