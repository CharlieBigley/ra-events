<?php

/**
 * @version    2.5.0
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2024 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 30/03/26 GPT copied from com_ra_mailman
 */

namespace Ramblers\Component\Ra_events\Site\View\Mailshotform;

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView implements CurrentUserInterface {
    protected $state;
    protected $item;
    protected $form;
    protected $params;
    protected $canEdit;
    protected $canSave;
    protected $list_name;
    protected $user;

    public function display($tpl = null) {
        $app = Factory::getApplication();
        $this->user = $this->getCurrentUser();
        $this->state = $this->get('State');
        $this->item = $this->get('Item');
        $this->params = $app->getParams('com_ra_events');
        $canDo = ContentHelper::getActions('com_ra_events');

        $this->form = $this->get('Form');

// Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }
        $this->objHelper = new ToolsHelper;
        $this->eventsHelper = new EventsHelper;
// Get the  id of the Event, passed as part of the URL
        $this->event_id = $app->input->getInt('event_id', '0');
        if ($this->event_id == 0) {
            Factory::getApplication()->enqueueMessage('this->event_id is Zero', 'message');
            return;
        } else {
            $this->canEdit = $canDo->get('core.edit');
            $this->canSave = $canDo->get('core.create');
            $event_id = $app->input->getInt('event_id', '0');
            $this->list_name = $this->eventsHelper->lookupEvent($event_id);
        }

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

}

