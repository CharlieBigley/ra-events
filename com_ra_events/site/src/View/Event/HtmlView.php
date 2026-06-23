<?php

/**
 * @version    2.5.0
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 19/02/25 CB set up $this->user from getCurrentUser
 * 05/03/25 CB support for bookings
 * 23/03/25 CB simplify message
 * 30/06/25 CB store $layout;
 * 26/07/25 CB save input parameters in user state
 * 07/04/26 CB showButton
 */

namespace Ramblers\Component\Ra_events\Site\View\Event;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Ra_events.
 *
 * @since  2.0.9
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $attachment_folder;
    protected $bookingHelper;
    protected $event_type_id;
    protected $event_type;
    protected $state;
    protected $item;
    protected $form;
    protected $layout;
    protected $menu_id;
    protected $params;
    protected $user;
    protected $toolsHelper;

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
        $this->state = $this->get('State');
        $this->item = $this->get('Item');
//        var_dump($this->item);
        $this->params = $app->getParams('com_ra_events');
        $menu_params = $app->getMenu()->getActive()->getParams();
//        var_dump($menu_params);
        // menu id will have been passed as a parameter
        $this->menu_id = $app->input->getInt("Itemid");
        $this->layout = $app->input->getWord('layout');

// Save input parameters so we know to where return need to be
        $app->setUserState('com_ra_events.event.menu_id', $this->menu_id);
        $app->setUserState('com_ra_events.event.layout', $this->layout);
        $app->setUserState('com_ra_events.event.id', $this->item->id);
// Reset callback so after editing a booking, control passes back to here
        $app->setUserState('com_ra_events.event.callback', '');
        if (!empty($this->item)) {
            $this->form = $this->get('Form');
        }

        $this->attachment_folder = 'images/com_ra_events';
        // Find the type of event
        $this->event_type_id = $this->item->event_type_id;

        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT description FROM #__ra_event_types WHERE id=' . $this->event_type_id;
        $this->event_type = $toolsHelper->getValue($sql);

        $this->bookingHelper = new BookingHelper;

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }


        if ($this->_layout == 'edit') {
            $authorised = $user->authorise('core.create', 'com_ra_events');

            if ($authorised !== true) {
                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'));
            }
        }

        $this->_prepareDocument();
        $this->toolsHelper = new ToolsHelper;
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
        // We need to get it from the menu item itself
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

    public function showButtons(){
    // returns a link to create/edit/send an email

        $back = 'index.php?option=com_ra_events&view=events' . '&Itemid=' . $this->menu_id;
        if ($this->layout == '') {
            $back .= '&event_type_id=' . $this->event_type_id;
        } else {
            $back .= '&layout=' . $this->layout;
        }
        $buttons = $this->toolsHelper->backButton($back, $caption);
        // get any bookings, confirmed or provisional
 //       echo 'view: event id=' . $this->item->id . ' - event type id=' . $this->event_type_id . '<br>';
        $tot_bookings = $this->bookingHelper->countActiveBookings($this->item->id);

        if (($this->item->emails_outstanding == 0) && ($tot_bookings > 1)) {
            $target = 'index.php?option=com_ra_events&Itemid=' . $this->menu_id . '&';
            $sql = 'SELECT id, processing_started, date_sent FROM `#__ra_mail_shots` ';
            $sql .= 'WHERE event_id=' . (INT) $this->item->id;
            $sql .= ' ORDER BY id DESC LIMIT 2';
//            echo $sql . '<br>';
            $mailshot = $this->toolsHelper->getItem($sql);
            if (is_null($mailshot)) {
                // No mailshot
                $caption = 'New message';
                $colour = 'darkgreen';
                $target .= 'view=mailshotform';
            } else {
                // Mailshot exists
                if (is_null($mailshot->processing_started)) {
                // mailshot exists but not yet sent
                    $caption = 'Edit message';
                    $colour = 'sunrise';
                    $target .= 'view=mailshotform&id=' . $mailshot->id;
                    $target .= '&event_id=' .$this->item->id;
                    echo $target . '<br>';
                    $buttons .= $this->toolsHelper->buildButton($target,$caption,false,$colour);

                    $caption = 'Send message';
                    $colour = 'red';
                    $target .= 'task=event.registerEmails&mailshot_id=' . $mailshot->id;
                } else {
                // Last mailshot was sent successfully
                    $caption = 'New message';
                    $colour = 'darkgreen';
                    $target .= 'view=mailshotform';
                }
            }        

            $target .= '&event_id=' .$this->item->id;
            $buttons .= $this->toolsHelper->buildButton($target,$caption,false,$colour);
        }
        if ($tot_bookings > 0) {    
            $caption = 'Show Reports';
            $target = 'index.php?option=com_ra_events&Itemid=' . $this->menu_id;
            $target .= '&task=event.bookingReports&id=' . $this->item->id;
            $buttons .= $this->toolsHelper->buildButton($target, $caption, False, 'darkgreen');   
        }     
        return $buttons;
}

}
