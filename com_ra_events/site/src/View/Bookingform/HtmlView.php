<?php

/**
 * @version    2.4.3
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 03/05/25 CB set up $this->canDo;
 * 26/07/25 Allow both Add and Update
 * 11/09/25 CB Allow anyone to make a booking if they are logged in
 * 22/09/25 CB save $this-_event_id
 * 25/09/25 CB allow booking via email invitation
 */

namespace Ramblers\Component\Ra_events\Site\View\Bookingform;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Ra_events.
 *
 * @since  2.0
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $intro;
    protected $callback;
    protected $canState;
    protected $state;
    protected $item;
    protected $form;
    protected $canEdit;
    protected $params;
    protected $toolsHelper;
    protected $canSave;
    protected $menu_id;
    protected $user_id;
    public $event_id;

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
        $this->toolsHelper = new ToolsHelper;
//        $this->user_id = $this->getCurrentUser()->id;
//        if ($this->user_id == 0) {
//                throw new \Exception('You must be logged on to make a booking', 403);
//        }
//      event_id cannot be passed to the view directly, so it is stored in the State
        $this->event_id = $app->getUserState('com_ra_events.bookingform.event_id', 0);
        if ($this->event_id == 0) {
            throw new \Exception("Can't find event number", 403);
        }
        $this->user_id = $app->getUserState('com_ra_events.bookingform.user_id', 0);
        if ($this->user_id == 0) {
            throw new \Exception("User not given", 403);
        }
        $this->callback = $app->getUserState('com_ra_events.bookingform.callback', '');
//        echo "View: callback=$this->callback<br>";
        $this->state = $this->get('State');
        $this->item = $this->get('Item');
        $this->params = $app->getParams('com_ra_events');
        $this->canSave = $this->get('CanSave');
        $this->form = $this->get('Form');
        $this->menu_id = $app->input->getInt('Itemid');
//        $this->canDo = ContentHelper::getActions('com_ra_events');
// Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }
//        if (JDEBUG) {
//            echo 'Booking id=', $this->item->id . '<br>';
//            echo 'event id=', $this->event_id . '<br>';
//        }
        $bookingHelper = new BookingHelper;
        $toolsHelper = new ToolsHelper;
//      Get details of the Event
        $sql = 'SELECT e.id, e.event_date, e.event_date_end, e.event_time, ';
        $sql .= 'e.title, e.location, c.name, ';
        if (($this->item->id == 0) AND ($this->callback !== 'profiles')) {
            // Find name of the User
            $this->title = 'Hi ';
            $name_lookup = $this->user_id;
        } else {
            $this->title = 'User ';
        }

        if ($this->item->id == 0) {
            $name_lookup = $this->user_id;
            $sql .= 'booking_info ';
            $sql .= 'FROM #__ra_events AS e ';
            $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
            $sql .= 'WHERE e.id=' . $this->event_id;
            $this->intro = '<h3>You are making a booking for the Event: ';
            if ($this->callback == 'profiles') {
                $this->canState = true;
            } else {
                $this->canState = false;
            }
        } else {
            $sql .= 'b.user_id, p.preferred_name ';
            $sql .= 'FROM #__ra_events AS e ';
            $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
            $sql .= 'LEFT JOIN #__ra_bookings AS b ON b.event_id = e.id ';
            $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id ';
            $sql .= 'WHERE b.id=' . $this->item->id;

//            $this->intro .= '<h4>Booking details</h4>';
            $this->intro = '<h3>You are updating a booking for the Event: ';
            $this->canState = true;
        }
//        echo $sql;
//        die;
        $event = $toolsHelper->getItem($sql);

        $this->event_id = $event->id;
        if ($event->type_id == 4) {  // Holiday
            $this->intro .= '<h3>' . HTMLHelper::_('date', $event->event_date, 'd-M-y') . ' to ';
            $this->intro .= HTMLHelper::_('date', $event->event_date_end, 'd-M-y');
        } else {
            $this->intro .= HTMLHelper::_('date', $event->event_date, 'l d M y');
        }
        $this->intro .= ' <i>' . $event->title . '</i></h3>';

        if ($event->location == "Zoom") {
            $location = ' using Zoom';
        } else {
            $location = $event->location;
        }

        if ($this->event_type_id == 4) {  // Holiday
            $this->intro .= '<b>Location</b> ' . $event->location . '<br>';
        } else {
            $this->intro .= '<h4>Meeting at ' . $event->event_time . '</h4>';
            $this->intro .= '<b>Location </b>';
            $this->intro .= $location . '<br>';
        }
        if ($event->contact_id == 0) {
            $contact = 'n/k';
        } else {
            $event->name = $event->name;
        }
        $this->intro .= '<b>Contact</b> ' . $event->name;
        $target_email = 'index.php?option=com_ra_tools&task=system.eventOrganiser&id=';
        $this->intro .= $toolsHelper->buildLink($target_email . $this->event_id, '<span class="icon-envelope" aria-hidden="true"></span>', false);
        $this->intro .= '<br> ';

//        $this->intro .= '<span class="icon-envelope" aria-hidden="true"></span>';

        if ($this->item->id == 0) {
            $this->intro .= '<h4>Payment Details are as follows:</h4>';
            $this->intro .= '<div style="color:red">';
            $this->intro .= $event->booking_info . '<br>';
            $this->intro .= '</div>';
            $this->intro .= '<h4>Your details</h4>';
        } else {
            $name_lookup = $event->user_id;
            //          $this->intro .= 'user_id=' . $event->user_id;
        }
        $this->title .= $bookingHelper->lookupPreferredname($name_lookup);
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
