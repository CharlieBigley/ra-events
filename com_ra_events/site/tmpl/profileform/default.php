<?php
/**
 * @version    2.1.3
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 25/03/25 CB page_intro
 * 23/04/25 CB show preferred name before email
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');

// Load admin language file
$lang = Factory::getLanguage();
$lang->load('com_ra_events', JPATH_SITE);
//var_dump($this->item);
$page_intro = $this->params->get('page_intro');
$page_footer = $this->params->get('page_footer');
?>

<div class="profile-edit front-end-edit">

    <?php if ($this->params->get('show_page_heading')) : ?>
        <div class="page-header">
            <h1> <?php echo $this->escape($this->params->get('page_heading')); ?> </h1>
        </div>
    <?php endif; ?>
    <?php
    echo '<h1>';
    if (empty($this->item->id)) {
        if ($this->mode == '0') {
            echo 'Register for Bookings';
        } else {
            echo 'Create a new User for Bookings';
        }
    } else {
        echo 'Updating Profile ' . $this->item->id;
    }
    echo '</h1>';
    if (!$page_intro == '') {
        echo $page_intro;
    }
    ?>


    <form id="form-profile"
          action="<?php echo Route::_('index.php?option=com_ra_events&task=profileform.save'); ?>"
          method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

        <?php echo $this->form->getInput('created_by'); ?>
        <?php echo $this->form->getInput('modified_by'); ?>
        <?php echo $this->form->renderField('preferred_name'); ?>
        <?php echo $this->form->renderField('real_name'); ?>

        <?php echo $this->form->renderField('email'); ?>

        <?php echo $this->form->renderField('home_group'); ?>
        <?php echo $this->form->renderField('id'); ?>
        <div class="control-group">
            <div class="controls">

                <?php if ($this->canSave): ?>
                    <button type="submit" class="validate btn btn-primary">
                        <span class="fas fa-check" aria-hidden="true"></span>
                        <?php echo Text::_('JSUBMIT'); ?>
                    </button>
                <?php endif; ?>
                <a class="btn btn-danger"
                   href="<?php echo Route::_('index.php?option=com_ra_events&task=profileform.cancel'); ?>"
                   title="<?php echo Text::_('JCANCEL'); ?>">
                    <span class="fas fa-times" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </a>
            </div>
        </div>

        <input type="hidden" name="option" value="com_ra_events"/>
        <input type="hidden" name="task"
               value="profileform.save"/>
               <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
<?php
if (!$page_footer == '') {
    echo $page_footer;
}
?>
