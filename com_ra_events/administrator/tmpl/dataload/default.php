<?php

/**
 * @package     com_ra_events
 * @version     4.1.10
 * @copyright   Copyright (C) 2025. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie Bigley <webmaster@bigley.me.uk>
 *              Actual processing is carried out in site/helpers/UserHelper.php
 * 25/03/25 CB created from MailMan
 */
use \Joomla\CMS\HTML\HTMLHelper;

// No direct access
defined('_JEXEC') or die;
?>
Processing of the data file is in two stages:
<ul>
    <li>Firstly it is validated to ensure it is in the correct format,
        and that all fields are present. A report is given, showing if Users are already present.
        You will be given to option to continue or cancel.</li>
    <li>If you continue, the database will be updated, creating any
        new Users that are required and booking them to the specified event.</li>
</ul>
<form action="<?php echo JRoute::_('index.php?option=com_ra_events&layout=edit'); ?>" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm" class="form-validate">
    <div class="row-fluid">
        <div id="j-main-container" class="span10">
            <fieldset class="adminform">

                <?php
                echo '<div class="control-group"><div class="control-label">';
                echo $this->form->getLabel('csv_file');
                echo '</div>' . PHP_EOL;
                echo '<div class="controls">';
                echo $this->form->getInput('csv_file');
                echo $this->form->renderField('file') . PHP_EOL;
                echo '</div></div>' . PHP_EOL;

                echo '<div class="control-group"><div class="control-label">';
                echo $this->form->getLabel('data_type');
                echo '</div>' . PHP_EOL;
                echo '<div class="controls">';
                echo $this->form->getInput('data_type');
                echo '</div></div>' . PHP_EOL;

                echo $this->form->renderField('event') . PHP_EOL;
                echo $this->form->renderField('processing') . PHP_EOL;
                ?>

                <?php if (!empty($this->item->attachment)) : ?>
                    <?php $attachmentFiles = array(); ?>
                    <?php foreach ((array) $this->item->attachment as $fileSingle) : ?>
                        <?php if (!is_array($fileSingle)) : ?>
                            <a href="<?php echo Route::_(Uri::root() . 'images/com_ra_events' . DIRECTORY_SEPARATOR . $fileSingle, false); ?>"><?php echo $fileSingle; ?></a> |
                            <?php $attachmentFiles[] = $fileSingle; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="jform[attachment_hidden]" id="jform_attachment_hidden" value="<?php echo implode(',', $attachmentFiles); ?>" />
                <?php endif; ?>


            </fieldset>
        </div>
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
    <div id="validation-form-failed" data-backend-detail="dataload" data-message="<?php echo $this->escape(JText::_('JGLOBAL_VALIDATION_FORM_FAILED')); ?>">
    </div>
</form>

