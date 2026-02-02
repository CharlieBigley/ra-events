<?php

/**
 * @version    2.3.5
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 09/10/23 CB delete Action button
 * 30/12/23 CB getActions from ComponentHelper, not ToolsHelper
 * 02/02/24 CB new button for delete
 * 02/12/24 CB only show group_code if show_group is set in component configuration
 * 02/12/24 CB change description to title]
 * 19/02/25 CB set up $this->user from getCurrentUser
 * 20/03/25 CB Return to Dashboard
 * 08/04/25 CB comment out Actions / Edit (did not work)
 * 25/08/25 CB Help
 * 23/10/25 CB reinstate delete
 */

namespace Ramblers\Component\Ra_events\Administrator\View\Events;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\HTML\Helpers\Sidebar;
use \Joomla\CMS\User\CurrentUserInterface;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Events.
 *
 * @since  1.0.1
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $canDo;
    protected $items;
    protected $pagination;
    protected $state;
    protected $show_group;
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
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

// Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }
        $this->user = $this->getCurrentUser();
        $this->addToolbar();
        // Find the folder that holds booking slips etc
        $this->attachment_folder = 'images/com_ra_events';
//        // Find the maximum number of characters to show from the description
//        $this->max_chars = $this->params->get('events_max_chars', 500);
        $this->show_group = ComponentHelper::getParams('com_ra_events')['show_group'];
//        $this->sidebar = Sidebar::render();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.0.1
     */
    protected function addToolbar() {
// Suppress menu side panel
        Factory::getApplication()->input->set('hidemainmenu', true);
// Set sidebar action
        Sidebar::setAction('index.php?option=com_ra_events&view=events');
        $state = $this->get('State');
        $this->canDo = ContentHelper::getActions('com_ra_events');
        ToolbarHelper::title('List of Events');

        $toolbar = Toolbar::getInstance('toolbar');

// Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Events';

        if (file_exists($formPath)) {
            if ($this->canDo->get('core.create')) {
                $toolbar->addNew('event.add');
            }
        }

        if ($this->canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('fas fa-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();
            if (isset($this->items[0]->state)) {
                $childBar->publish('events.publish')->listCheck(true);
                $childBar->unpublish('events.unpublish')->listCheck(true);

//                $childBar->edit('events.edit')->listCheck(true);
            }
        }

        if ($this->canDo->get('core.delete')) {
            $toolbar->delete('event.delete')
                    ->text('JTOOLBAR_DELETE')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
        }
        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);
        ToolbarHelper::cancel('events.cancel', 'Return to Dashboard');
        $help_url = 'https://docs.stokeandnewcastleramblers.org.uk/mail-manager.html?view=article&id=394:com-3-1-events&catid=33';
        ToolbarHelper::help('', false, $help_url);
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'a.`id`' => Text::_('JGRID_HEADING_ID'),
            'a.`state`' => Text::_('JSTATUS'),
            'a.`event_date`' => Text::_('COM_RA_EVENTS_EVENTS_EVENT_DATE'),
            'a.`title`' => Text::_('COM_RA_EVENTS_EVENTS_DESCRIPTION'),
            'a.`group_code`' => Text::_('COM_RA_EVENTS_EVENTS_GROUP_CODE'),
            'a.`location`' => Text::_('COM_RA_EVENTS_EVENTS_LOCATION'),
            'a.`event_time`' => Text::_('COM_RA_EVENTS_EVENTS_EVENT_TIME'),
        );
    }

    /**
     * Check if state is set
     *
     * @param   mixed  $state  State
     *
     * @return bool
     */
    public function getState($state) {
        return isset($this->state->{$state}) ? $this->state->{$state} : false;
    }

}
