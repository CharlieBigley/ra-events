<?php

/**
 * @version    2.1.3
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 20/03/25 CB Raise error depending on logged in and type of registration
 * 23/04/25 CB check permission before creating a new user
 */

namespace Ramblers\Component\Ra_events\Site\View\Profileform;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\User\CurrentUserInterface;

/**
 * View class for a list of Ra_events.
 *
 * @since  2.0
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $canDo;
    protected $state;
    protected $item;
    protected $form;
    protected $params;
    protected $canSave;
    protected $user;
    protected $menu_id;
    protected $mode;

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
        $this->params = $app->getParams('com_ra_events');
        $this->mode = $this->params->get('mode', '0');
        $this->menu_id = $app->input->getInt('Itemid', '0');
        $layout = $app->input->getWord('layout', 'profile');
        $this->canDo = ContentHelper::getActions('com_ra_events');

        if ($layout == 'profile') {
            // Mode is defined when creating the menu entry: 0=Self register, 1=Admin register
            $this->mode = $this->params->get('mode', '0');
            if ($this->mode == '0') {
                if ($this->user->id > 0) {
//                    throw new \Exception('You are already Registered', 403);
                    echo '<h4>You are already Registered</h4>';
                    return false;
                }
            } else {  // Creating a new user
                if ($this->user->id == 0) {
//                    throw new \Exception('Please login to gain access to this function', 403);
                    echo '<h4>Please login to gain access to this function</h4>';
                    return false;
                }
                if (!$this->canDo->get('core.create')) {
                    $app->enqueueMessage('Sorry, you don\'t have permission to create new Users', 'error');
//               $this->setRedirect(Route::_('index.php?option=com_ra_events&view=events', false));
                    return;
                }
            }
        } else {
            // Showing bookings
            if ($this->user->id == 0) {
                throw new \Exception('Please login to gain access to this function', 403);
            }
        }

        $this->state = $this->get('State');
        $this->item = $this->get('Item');

        $this->canSave = $this->get('CanSave');
        $this->form = $this->get('Form');
        //       die('View got form' . $layout);
        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
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
