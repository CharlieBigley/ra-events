<?php

/**
 * @version    2.3.5
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 29/03/25 CB showBookings and extractBookings
 * 09/04/25 CB publish/Unpublish
 * 08/05/25 CB header for extract
 * 30/06/25 CB use BookingHelper for extracts
 * 04/08/25 CB functions delete & publish copied from Emails
 * 05/10/25 CB show home group
 * 23/10/25 CB implement delete
 */

namespace Ramblers\Component\Ra_events\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Events list controller class.
 *
 * @since  1.0.1
 */
class EventsController extends AdminController {

    public function __construct() {
        parent::__construct();
//        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->objApp = Factory::getApplication();

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    private function changeState($id, $new) {
        $sql = 'SELECT event_date, title, state FROM #__ra_events WHERE id=';
        $event = $this->toolsHelper->getItem($sql . $id);
        $message = 'Event "' . $event->event_date . '/' . $event->title . '" ';
//        if ($event->state == $new) {
//            $message .= 'is already in state ' . $new;
//        } else {
//            $sql = 'UPDATE #__ra_events SET state=' . $new . ' WHERE id=' . $id;
//            $message .= ( $new == 1 ? ' Published' : ' Unpublished');
//            Factory::getApplication()->enqueueMessage($sql, 'info');
//            //$this->toolsHelper->executeCommand($sql);
//        }
//        Factory::getApplication()->enqueueMessage($message, 'info');
        if ($event->state == 0) {
            $message .= ' Published';
            $new_state = 1;
        } else {
            $message .= ' Unpublished';
            $new_state = 0;
        }
        $sql = 'UPDATE #__ra_events SET state=' . $new_state . ' WHERE id=' . $id;
        Factory::getApplication()->enqueueMessage($message, 'info');
        $this->toolsHelper->executeCommand($sql);
    }

    public function delete() {
        $message = 'Invalid function: event.delete';
        Factory::getApplication()->enqueueMessage($message, 'info');
        echo $message . '<br>';

        $this->setRedirect('index.php?option=com_ra_events&view=events');
    }

    /**
     * Method to clone existing Events
     *
     * @return  void
     *
     * @throws  Exception
     */
    public function duplicate() {
        // Check for request forgeries
        $this->checkToken();

        // Get id(s)
        $pks = $this->input->post->get('cid', array(), 'array');

        try {
            if (empty($pks)) {
                throw new \Exception(Text::_('COM_RA_EVENTS_NO_ELEMENT_SELECTED'));
            }

            ArrayHelper::toInteger($pks);
            $model = $this->getModel();
            $model->duplicate($pks);
            $this->setMessage(Text::_('COM_RA_EVENTS_ITEMS_SUCCESS_DUPLICATED'));
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect('index.php?option=com_ra_events&view=events');
    }

    public function extractBookings() {
        ToolBarHelper::title('Extract Bookings');
        $event_id = $this->app->input->getInt('id', '0');
        $table = $this->app->input->getInt('table', 'N');
        echo '<h4>You can copy this report and paste it into a CSV file, then add extra columns etc</h4>';
        echo '<h4>You could save this file, edit as required and import into a different Event</h4>';
        $bookingHelper = new BookingHelper;
        $bookingHelper->extractBookings($event_id, $table);
        $back = 'administrator/index.php?option=com_ra_events&view=events';
        echo $this->toolsHelper->backButton($back);
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    Optional. Model name
     * @param   string  $prefix  Optional. Class prefix
     * @param   array   $config  Optional. Configuration array for model
     *
     * @return  object	The Model
     *
     * @since   1.0.1
     */
    public function getModel($name = 'Event', $prefix = 'Administrator', $config = array()) {
        return parent::getModel($name, $prefix, array('ignore_request' => true));
    }

    public function publish() {
        $primary_keys = $this->input->post->get('cid', array(), 'array');

        switch ($this->task) {
            case 'publish':
                $from = 0;
                $to = 1;
                break;
            case 'unpublish':
                $from = 1;
                $to = 0;
                break;
            default;
                Factory::getApplication()->enqueueMessage($this->task . ' not recognised', 'warning');
                return;
        }
//        Factory::getApplication()->enqueueMessage($this->task . ", from $from to $to ", 'warning');
        foreach ($primary_keys as $id) {
            $message = $this->changeState($id, $from, $to);
            Factory::getApplication()->enqueueMessage($message, 'info');
        }
        $this->setRedirect('index.php?option=com_ra_events&iew=events');
    }

    public function showBookings() {

        $event_id = $this->app->input->getInt('id', '0');
        $sql = 'SELECT event_date, title, state FROM #__ra_events WHERE id=';
        $event = $this->toolsHelper->getItem($sql . $event_id);
        ToolBarHelper::title('Bookings for ' . ' Event ' . $event->event_date . '/' . $event->title);

        $table = new ToolsTable;
        $total_bookings = 0;
        $confirmed_bookings = 0;
        $total_places = 0;
        $table->add_header('Name,Group,Status,Booked,Places,Other');

        $sql = 'SELECT b.id, b.event_id, b.state, b.created, b.num_places, b.partner, ';
        $sql .= 'p.preferred_name, p.home_group, s.title ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id  ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id  ';
        $sql .= 'INNER JOIN #__ra_event_states AS s ON s.id = b.state  ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' ORDER BY s.seq, p.preferred_name';

        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            if ($row->state == 1) {
                $total_places = $total_places + $row->num_places;
                $confirmed_bookings++;
            }
            $total_bookings++;

            $table->add_item($row->preferred_name);
            $table->add_item($row->home_group);
            $table->add_item(BookingHelper::showState($row->state));
            $table->add_item(HTMLHelper::_('date', $row->created, 'd M y H:i'));
            $table->add_item($row->num_places);
            $table->add_item($row->partner);
            $table->generate_line();
        }
        $table->generate_table();

        echo 'Total number of bookings = ' . $total_bookings;
        echo ', Confirmed bookings = ' . $confirmed_bookings;
        echo ', Total places = ' . $total_places . '<br>';
        $back = 'administrator/index.php?option=com_ra_events&view=events';
        echo $this->toolsHelper->backButton($back);
    }

}
