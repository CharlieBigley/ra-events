<?php

/**
 * @version    2.2.1
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 20/03/25 CB Return to Dashboard
 * 15/09/25 CB add ContentHelper
 */

namespace Ramblers\Component\Ra_events\Administrator\View\Profiles;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Form\Form;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\HTML\Helpers\Sidebar;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Profiles.
 *
 * @since  2.0
 */
class HtmlView extends BaseHtmlView {

    protected $canDo;
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
        $this->canDo = ContentHelper::getActions('com_ra_events');

        ToolbarHelper::title(Text::_('Users'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');

        // Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Profiles';

        if (file_exists($formPath)) {
            if ($this->canDo->get('core.create')) {
                $toolbar->addNew('profile.add');
            }
        }

        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);
        ToolbarHelper::cancel('bookings.cancel', 'Return to Dashboard');

        // Set sidebar action
        Sidebar::setAction('index.php?option=com_ra_events&view=profiles');
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'u.`id`' => Text::_('JGRID_HEADING_ID'),
            's.`title`' => Text::_('JSTATUS'),
            's.`title`' => Text::_('COM_RA_EVENTS_PROFILES_EMAIL'),
            'p.`preferred_name`' => Text::_('COM_RA_EVENTS_PROFILES_PREFERRED_NAME'),
            'p.`home_group`' => Text::_('COM_RA_EVENTS_PROFILES_HOME_GROUP'),
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
