<?php

/**
 * @version    4.2.0
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * This view validates and uploads the specified csv file before passing control the view Process.
 * If that aborts processing, control returns here, keeping the file selected.
 *
 * No Table is used, as the actual file upload is done in the save function of the Model
 * 25/03/25 CB created from MailMan
 */

namespace Ramblers\Component\Ra_events\Administrator\View\Dataload;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\User\CurrentUserInterface;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a single Mail_lst.
 *
 * @since  1.0.6
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $state;
    protected $item;
    protected $form;
    protected $user;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @throws Exception
     */
    public function display($tpl = null) {
        $this->state = $this->get('State');
        $this->form = $this->get('Form');
        $this->user = $this->getCurrentUser();

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @throws Exception
     */
    protected function addToolbar() {
        // Suppress menu side panel
        Factory::getApplication()->input->set('hidemainmenu', true);

        $isNew = ($this->item->id == 0);

        $canDo = ContentHelper::getActions('com_ra_events');

        ToolbarHelper::title(Text::_('Load booking data for an Event'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');

        // If not checked out, can save the item.
        if ($canDo->get('core.edit') || ($canDo->get('core.create'))) {

            $toolbar->standardButton('process')
                    ->icon('fa fa-info-circle')
                    ->text('Process file')
                    ->task('dataload.save')
                    ->onclick('return true')
                    ->listCheck(false);
        }

        ToolbarHelper::cancel('dataload.cancel', 'Return to Dashboard');
    }

}
