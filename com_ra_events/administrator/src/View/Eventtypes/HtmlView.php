<?php

/**
 * @version    2.2.1
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 14/04/25 CB create new records
 */

namespace Ramblers\Component\Ra_events\Administrator\View\Eventtypes;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\HTML\Helpers\Sidebar;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Eventtypes.
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
        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT COUNT(id) FROM #__ra_event_types';
//        echo $sql;
        $count = $toolsHelper->getValue($sql);
        if ($count == 0) {
            $sql = 'INSERT INTO #__ra_event_types (id,description) VALUES(1,"Committee Meetings")';
            $toolsHelper->executeCommand($sql);
            $sql = 'INSERT INTO #__ra_event_types (id,description) VALUES(2,"Social Event")';
            $toolsHelper->executeCommand($sql);
            $sql = 'INSERT INTO #__ra_event_types (id,description) VALUES(3,"Training")';
            $toolsHelper->executeCommand($sql);
            $sql = 'INSERT INTO #__ra_event_types (id,description) VALUES(4,"Holiday/Weekend")';
            $toolsHelper->executeCommand($sql);
        }
        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
//		$this->filterForm = $this->get('FilterForm');
//		$this->activeFilters = $this->get('ActiveFilters');
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
        $canDo = ToolsHelper::getActions('com_ra_events');

        ToolbarHelper::title(Text::_('Event types'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');

        // Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Eventtypes';

        if (file_exists($formPath)) {
            if ($canDo->get('core.create')) {
                $toolbar->addNew('eventtype.add');
            }
        }
        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);
        ToolbarHelper::cancel('events.cancel', 'Return to Dashboard');
        // Set sidebar action
        Sidebar::setAction('index.php?option=com_ra_events&view=eventtypes');
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
            'a.`description`' => Text::_('COM_RA_EVENTS_EVENTTYPES_DESCRIPTION'),
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
