<?php

/**
 * @version    2.1.1
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 22/01/23 CB default for max_chars
 * 04/02/24 CB lookupContact
 * 01/12/24 CB only show group_code if show_group is set in component configuration
 * 02/12/24 CB change description to title
 * 19/02/25 CB set up $this->user from getCurrentUser
 * protected $layout;
 *
 */

namespace Ramblers\Component\Ra_events\Site\View\Events;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Ra_events.
 *
 * @since  1.0.1
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $attachment_folder;
    protected $event_type_id;
    protected $event_type;
    protected $items;
    protected $layout;
    protected $max_chars;
    protected $menu_id;
    protected $pagination;
    protected $show_group;
    protected $state;
    protected $params;
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
        $app = Factory::getApplication();
        $this->user = $this->getCurrentUser();
        // Find the type of event
        $this->event_type_id = $app->input->getInt('event_type_id', '0');
        // Get the menu item id - we need this so view Event knows where to return to
        $this->menu_id = $app->input->getInt('Itemid');
        $this->layout = $app->input->getWord('layout');

        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->params = $app->getParams('com_ra_events');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        // Find the folder that holds booking slips etc
        $this->attachment_folder = 'images/com_ra_events';
        // Find the maximum number of characters to show from the details
        $this->max_chars = $this->params->get('events_max_chars', 500);

        $objHelper = new ToolsHelper;
        if ($this->event_type_id == 0) {
            $this->event_type = 'Event';
        } else {
            $sql = 'SELECT description FROM #__ra_event_types WHERE id=' . $this->event_type_id;
            $this->event_type = $objHelper->getValue($sql);
        }

        $menu_params = $app->getMenu()->getActive()->getParams();
//        var_dump($menu_params);
//
        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }
        $this->show_group = ComponentHelper::getParams('com_ra_events')['show_group'];
        $this->_prepareDocument();
        parent::display($tpl);
    }

    /**
     * Prepares the document
     *
     * @return void
     *
     * @throws Exception
     */
    protected function _prepareDocument() {
        $app = Factory::getApplication();
        $menus = $app->getMenu();
        $title = null;

        // Because the application sets a default page title,
        // we need to get it from the menu item itself
        $menu = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_RA_EVENTS_DEFAULT_PAGE_TITLE'));
        }

        $title = $this->params->get('page_title', '');

        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->document->setTitle($title);

        if ($this->params->get('menu-meta_description')) {
            $this->document->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->document->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->document->setMetadata('robots', $this->params->get('robots'));
        }
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

    public function lookupContact($contact_id) {
        $sql .= 'SELECT name FROM #__contact_details WHERE id=' . $contact_id;
        $objHelper = new ToolsHelper;
        return $objHelper->getValue($sql);
    }

}
