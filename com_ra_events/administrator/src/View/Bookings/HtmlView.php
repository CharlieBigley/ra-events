<?php

/**
 * @version    2.2,1
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 20/03/25 CB Return to Dashboard
 * 14/08/25 CB reinstate delete
 * 10/09/25 CB Help
 */

namespace Ramblers\Component\Ra_events\Administrator\View\Bookings;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\HTML\Helpers\Sidebar;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Bookings.
 *
 * @since  2.0
 */
class HtmlView extends BaseHtmlView {

    protected $items;
    protected $pagination;
    protected $state;

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

        $this->addToolbar();

        $this->sidebar = Sidebar::render();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   2.0
     */
    protected function addToolbar() {
        $state = $this->get('State');
        $canDo = ToolsHelper::getActions();

        ToolbarHelper::title('List of Bookings', "generic");

        $toolbar = Toolbar::getInstance('toolbar');

        // Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Bookings';

        if (file_exists($formPath)) {
            if ($canDo->get('core.create')) {
                $toolbar->addNew('booking.add');
            }
        }

        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('fas fa-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if (isset($this->items[0]->state)) {
                $childBar->publish('bookings.publish')->listCheck(true);
                $childBar->unpublish('bookings.unpublish')->listCheck(true);
            }



            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('bookings.checkin')->listCheck(true);
            }

            if (isset($this->items[0]->state)) {
                $childBar->trash('bookings.trash')->text('Cancel')->listCheck(true);
            }
        }
        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {

            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
                $toolbar->delete('bookings.delete')
                        ->text('Delete')
                        ->message('JGLOBAL_CONFIRM_DELETE')
                        ->listCheck(true);
            }
        }
        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);
        ToolbarHelper::cancel('bookings.cancel', 'Return to Dashboard');
        $help_url = 'https://docs.stokeandnewcastleramblers.org.uk/ramblers-components.html?view=article&id=514:com-3-6-1&catid=33';
        ToolbarHelper::help('', false, $help_url);
        //       if ($canDo->get('core.admin')) {
        //           $toolbar->preferences('com_ra_events');
        //       }
        // Set sidebar action
        Sidebar::setAction('index.php?option=com_ra_events&view=bookings');
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
            'a.`partner`' => Text::_('Second person'),
            'p.`preferred_name`' => Text::_('Name'),
            'e.`title`' => Text::_('Event'),
            'a.`num_places`' => Text::_('Num places'),
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
