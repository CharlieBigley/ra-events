<?php

/**
 * @version    2.5.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 30/12/23 CB getActions from ComponentHelper, not ToolsHelper
 * 02/12/24 CB comment out setup of this->header
 * 19/02/25 CB set up $this->user from getCurrentUser
 * 16/06/25 CB read-only if imported from another site
 * 14/11/25 CB warning if checked out
 * 06/04/26 CB don't show toolbar for layout send
 */

namespace Ramblers\Component\Ra_events\Administrator\View\Event;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\User\CurrentUserInterface;
use \Ramblers\Component\Ra_tevents\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a single Event.
 *
 * @since  1.0.1
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    public $canDo;
    public $header;
    public $toolsHelper;
    protected $mode;
    protected $event_type;
    protected $show_group;
    protected $state;
    protected $item;
    protected $user;
    protected $form;

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
        //See if we are editing Agends / Reports / Minutes
        $this->mode = Factory::getApplication()->getUserState('com_ra_events.edit.event.mode');

        $this->state = $this->get('State');
        $this->item = $this->get('Item');
        $this->user = $this->getCurrentUser();
        $app = Factory::getApplication();
        $layout = $app->input->getWord('layout');
        $this->toolsHelper = new ToolsHelper;

        // Set up page introduction for diplay by the Template
        if ($this->item->id == 0) {
            $this->header = '<h2>New Event</h2>';
        } else {
            // Look up the type of Event

            $sql = 'SELECT description FROM #__ra_event_types WHERE id=' . $this->item->event_type_id;
            $this->event_type = $this->toolsHelper->getValue($sql);
            $this->header = '<h2>' . $this->event_type;
            if ($this->mode == 'A') {
                $this->header .= ' Agenda';
            } elseif ($this->mode == 'R') {
                $this->header .= ' Reports';
            } elseif ($this->mode == 'M') {
                $this->header .= ' Minutes';
//            } else {
//                $this->header .= 'committee meeting';
            }
            $this->header .= '</h2>';
            /*
              if ($this->item->event_type_id == 4) { // Holiday
              $this->header .= '<b>Date from </b>' . HTMLHelper::_('date', $this->item->event_date, 'd-m-y');
              $this->header .= ' to ' . HTMLHelper::_('date', $this->item->event_date_end, 'd-m-y') . $this->mode . '<br>';
              $this->header .= '<b>Location </b>' . $this->item->location . '<br>';
              } else {
              $this->header .= '<b>Date </b>' . HTMLHelper::_('date', $this->item->event_date, 'd-m-y') . '<br>';
              }

              if ($this->mode != '') {  //
              $this->header .= '<b>Meeting</b> at ' . $this->item->event_time;
              if ($this->item->location == "Zoom") {
              $this->header .= ' using Zoom<br>';
              } else {
              $this->header .= '<br><b>Location </b>' . $this->item->location . '<br>';
              }
              }
             */
        }
        $this->form = $this->get('Form');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }
        $this->show_group = ComponentHelper::getParams('com_ra_events')['show_group'];
        if ($layout !== 'send') {
            $this->addToolbar();
        }  
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @throws Exception
     */
    protected function addToolbar() {
        Factory::getApplication()->input->set('hidemainmenu', true);
        $isNew = ($this->item->id == 0);
        if (($this->item->checked_out == 0) || ($this->item->checked_out == $this->user->id)) {
            $checkedOut = false;
        } else {
            $checkedOut = true;
            //            $checkedOut = ( $this->item->checked_out !== $this->user->id);
            $user_name = $this->toolsHelper->lookupUser($this->item->checked_out);
            Factory::getApplication()->enqueueMessage('This item is currently checked out by ' . $this->item->checked_out . '/' . $user_name, 'warning');
        }
//        if (isset($this->item->checked_out)) {
//            echo 'user ' . $this->user->id . '<br>';
//            echo 'Checked ' . $this->item->checked_out . '<br>';
//            echo ($this->item->checked_out !== $this->user->id) . '<br>';
//            //$checkedOut = !($this->item->checked_out !== 0 || $this->item->checked_out == $this->user->id);
//            $checkedOut = ( $this->item->checked_out !== $this->user->id);
//            $user_name = $this->toolsHelper->lookupUser($this->item->checked_out);
//            Factory::getApplication()->enqueueMessage('This item is currently checked out by ' . $this->item->checked_out . '/' . $user_name, 'warning');
//        } else {
//            $checkedOut = false;
//        }

        $this->canDo = ContentHelper::getActions('com_ra_events');

        ToolbarHelper::title('Event');
        if (($this->item->api_site_id == 0) OR (is_null($this->item->api_site_id))) {
            // If not checked out, can save the item.
            if (!$checkedOut && ($this->canDo->get('core.edit') || ($this->canDo->get('core.create')))) {
                ToolbarHelper::apply('event.apply', 'JTOOLBAR_APPLY');
            }

            if ($this->item->id > 0) {
                if (!$checkedOut && ($this->canDo->get('core.edit') || ($this->canDo->get('core.create')))) {
                    ToolbarHelper::save('event.save', 'JTOOLBAR_SAVE');
                }
                if (!$checkedOut && ($this->canDo->get('core.create'))) {
                    ToolbarHelper::custom('event.save2new', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
                }

                // If an existing item, can save to a copy.
                if (!$isNew && $this->canDo->get('core.create')) {
                    ToolbarHelper::custom('event.save2copy', 'save-copy.png', 'save-copy_f2.png', 'JTOOLBAR_SAVE_AS_COPY', false);
                }
            }
        }
        if (empty($this->item->id)) {
            ToolbarHelper::cancel('event.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('event.cancel', 'JTOOLBAR_CLOSE');
        }
    }

}
