<?php

/**
 * @version     2.1.9
 * @package     com_ra_events
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 05/08/25 CB created
 */

namespace Ramblers\Component\Ra_events\Site\View\Myemails;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Ramblers detail view
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $app;
    protected $menu_id;
    protected $menu_params;
    protected $params;
    protected $toolsHelper;
    protected $user;
    protected $user_id;

    /**
     * Display the view
     */
    public function display($tpl = null) {
        $this->user = $this->getCurrentUser();
        if ($this->user->id == 0) {
            throw new \Exception('You must be logged on to make a booking', 403);
        }
//        if ($this->user->authorise('core.edit', 'com_ra_events')) {
//            $this->canEdit = true;
//        } else {
//            throw new \Exception('Insufficient access to update a booking', 403);
//        }
        // Find from which menu we have been invoked
        $this->app = Factory::getApplication();
        $this->menu_id = $this->app->input->getInt('Itemid', '0');

        $this->user_id = $this->app->input->getInt('user_id', $this->user->id);
        if ($this->user_id == 0) {
            $this->user_id = $this->user->id;
        }
        // Load the component params
        $this->params = ComponentHelper::getParams('com_ra_tools');
        $app = Factory::getApplication();
//        $user = Factory::getApplication()->getIdentity();
        $menu = $app->getMenu()->getActive();
        if (is_null($menu)) {

        } else {
            $this->menu_params = $menu->getParams();
        }

        $this->toolsHelper = new ToolsHelper;
        $sql = 'SELECT u.email, p.preferred_name, c.name, c.con_position, cat.title ';
        $sql .= 'FROM #__contact_details AS c ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = c.user_id ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id =  c.user_id ';
        $sql .= 'INNER JOIN #__categories AS cat ON cat.id =  c.catid ';
        $sql .= 'WHERE c.user_id=' . $this->user_id;
        //       die($sql);
        $this->item = $this->toolsHelper->getItem($sql);
        Factory::getApplication()->setUserState('com_ra_tools.menu_id', $menu_id);

        /*
          // Throw exeption if errors
          if (count($errors = $this->get('Errors')))
          {
          throw new Exception(implode("\n", $errors));
          }

         */
//        $this->loadTemplateHeader();
        $this->prepareDocument();

        parent::display();
    }

    /**
     * Prepares the document
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function prepareDocument() {
//        $app = Factory::getApplication();
        $menus = $this->app->getMenu();
        $title = null;

        // Because the application sets a default page title,
        // we need to get it from the menu item itself
        $menu = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('Reports'));
        }

        $title = $this->params->get('page_title', '');

        if (empty($title)) {
            $title = $this->app->get('sitename');
        } elseif ($this->app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($this->app->get('sitename_pagetitles', 0) == 2) {
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
