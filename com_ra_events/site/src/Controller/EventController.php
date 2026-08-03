<?php

/**
 * @version    2.4.15
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 21/03/25 CB display proper message on success
 * 30/06/25 CB extractBookings
 * 12/10/25 CB bookingReports
 * 22/10/25 CB sort by custom fields
 * 03/11/25 CB for custom sort, also sort by name
 * 21/11/25 CB show special requests in CSV
 * 25/02/26 CB showTerms (invoked from link in confirmation email)
 * 02/03/26 CB use preferred_name in reports, not name
 * 17/06/26 CB include booked date in the CSV output
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

\defined('_JEXEC') or die;

use \Joomla\CMS\Application\SiteApplication;
use \Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Controller\BaseController;
//use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Event class.
 *
 * @since  1.6.0
 */
class EventController extends BaseController {

    protected $db;
    protected $app;
    protected $toolsHelper;

    public function __construct() {
        parent::__construct();
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
// Import CSS
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function bookingReports() {
        $event_id = $this->app->input->getInt('id', '0');
        $sort = $this->app->input->getCmd('sort', 'name');
        $mode = $this->app->input->getWord('mode', 'preview');
        $menu_id = $this->app->input->getInt('Itemid', '0');

        $sql = 'SELECT e.event_type_id, e.event_date, e.event_date_end, e.title, ';
        $sql .= 't.description AS `event_type`, e.booking1, e.booking2 ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__ra_event_types AS t ON t.id = e.event_type_id ';
        $sql .= 'WHERE e.id=' . $event_id;
        $event = $this->toolsHelper->getItem($sql);

//echo 'mode is ' . $mode . '<br>';
        $self = 'index.php?option=com_ra_events';
        $self .= '&Itemid=' . $menu_id;
        $self .= '&task=event.bookingReports&id=' . $event_id;

        $sql = 'SELECT b.*, ';
        $sql .= 'g.name AS `group_name`, p.preferred_name, u.name, u.email ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id  ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = b.user_id  ';
        $sql .= 'LEFT JOIN #__ra_groups AS g ON g.code = p.home_group  ';
        $sql .= 'INNER JOIN #__ra_events AS e ON e.id = b.event_id  ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' AND b.state in (0,1)';
        if ($sort == 'name') {
            $sql .= ' ORDER BY p.preferred_name, g.name';
        } elseif ($sort == 'booking1') {
            $sql .= ' ORDER BY custom1, g.name, p.preferred_name';
        } elseif ($sort == 'booking2') {
            $sql .= ' ORDER BY custom2, g.name, p.preferred_name';
        } else {
            $sql .= ' ORDER BY g.name,p.preferred_name';
        }
//        echo $sql . '<br>';
        $rows = $this->toolsHelper->getRows($sql);

        // Build column headings based on sort order and presence of custom fields
        $column_headings = '';
        if ($sort == 'name') {
            $column_headings = 'Name,Group';
        } else {
            $column_headings = 'Group,Name';
        }
        $column_headings .= ',Booked,, Email, Extra';
        if ($event->booking1 !== '') {
            $column_headings .= ', ' . $event->booking1;
        }
        if ($event->booking2 !== '') {
            $column_headings .= ', ' . $event->booking2;
        }

        // If CSV download requested, output headers and exit
        if ($mode === 'csv') {
            // Generate CSV data
            $csvData = $column_headings . ",Special requests\n";
            foreach ($rows as $row) {
                if ($sort == 'name') {
                    $csvData .= $row->preferred_name . ', ';
                    $csvData .= $row->group_name . ', ';
                } else {
                    $csvData .= $row->group_name . ', ';
                    $csvData .= $row->preferred_name . ', ';
                }
                $csvData .= $row->created . ', ';
                $csvData .= $row->email . ', ';
                $csvData .= $row->partner;
                if ($event->booking1 !== '') {
                    $csvData .= ', ' . $row->custom1;
                }
                if ($event->booking2 !== '') {
                    $csvData .= ', ' . $row->custom2;
                }
                $csvData .= ', ';
                if (str_contains($row->special_request, ',')) {
                    $csvData .= '"' . $row->special_request . '"';
                } else {
                    $csvData .= $row->special_request;
                }
                $csvData .= "\n";
            }

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="bookings_' . date('Y-m-d') . '.csv"');
            echo $csvData;
            exit;
        }
        echo '<h2>Booking Reports</h2>';
// echo '2 mode is ' . $mode . '<br>';
        // Display HTML view (preview or alpha mode)
        echo $this->toolsHelper->showPrint($target);
        $label = 'Download as CSV';
        $target = $self . '&sort=' . $sort . '&mode=csv';
        echo $this->toolsHelper->buildButton($target, $label, false, 'darkgreen');

        if ($mode !== 'alpha') {
            if ($sort == 'name') {
                $label = 'Sort by Group';
                $target = $self . '&mode=' . $mode . '&sort=group';
                echo $this->toolsHelper->buildButton($target, $label, false, 'darkgreen');
            } else {
                $label = 'Sort by Name';
                $target = $self . '&mode=' . $mode . '&sort=name';
                echo $this->toolsHelper->buildButton($target, $label, false, 'darkgreen');
            }
            if (($event->booking1 !== '') AND ($sort !== 'booking1')) {
                $label = 'Sort by ' . $event->booking1;
                $target = $self . '&mode=' . $mode . '&sort=booking1';
                echo $this->toolsHelper->buildButton($target, $label, false, 'darkgreen');
            }
            if (($event->booking2 !== '') AND ($sort !== 'booking2')) {
                $label = 'Sort by ' . $event->booking2;
                $target = $self . '&mode=' . $mode . '&sort=booking2';
                echo $this->toolsHelper->buildButton($target, $label, false, 'darkgreen');
            }
            $label = 'List all by name';
            $target = $self . '&mode=alpha';
            echo $this->toolsHelper->buildButton($target, $label, false, 'darkgreen');
        }

        echo '<h3>' . $event->event_type . '</h3>';
        if ($event->event_type_id == 4) {  // Holiday
            echo '<h2>' . HTMLHelper::_('date', $event->event_date, 'd-M-y') . ' to ';
            echo HTMLHelper::_('date', $event->event_date_end, 'd-M-y');
        } else {
            echo '<h2>' . HTMLHelper::_('date', $event->event_date, 'l d M y');
        }
        echo ' <b>' . $event->title . '</b></h2>';

        if ($mode == 'preview') {
            $toolsTable = new ToolsTable();
            $toolsTable->add_header($column_headings . ",Special requests");
            foreach ($rows as $row) {
                if ($sort == 'name') {
                    $toolsTable->add_item($row->preferred_name);
                    $toolsTable->add_item($row->group_name);
                } else {
                    $toolsTable->add_item($row->group_name);
                    $toolsTable->add_item($row->preferred_name);
                }
                $toolsTable->add_item($row->created);
                $toolsTable->add_item($row->email);
                $toolsTable->add_item($row->partner);
                if ($event->booking1 !== '') {
                    $toolsTable->add_item($row->custom1);
                }
                if ($event->booking2 !== '') {
                    $toolsTable->add_item($row->custom2);
                }
                $toolsTable->add_item($row->special_request);
                $toolsTable->generate_line();
            }
            $toolsTable->generate_table();
        } elseif ($mode == 'alpha') {
            $names = [];
            foreach ($rows as $row) {
                $names[] = $row->preferred_name;
                if ($row->num_places == 2) {
                    $names[] = $row->partner;
                }
            }
            sort($names);
            foreach ($names as $name) {
                echo $name . '<br>';
            }
        }

        $back = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
        $back .= '&Itemid=' . $menu_id;
        echo $this->toolsHelper->backButton($back);
    }

    /**
     * Method to check out an item for editing and redirect to the edit form.
     *
     * @return  void
     *
     * @since   1.0.1
     *
     * @throws  Exception
     */
    public function edit() {
        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $this->app->getUserState('com_ra_events.edit.event.id');
        $editId = $this->input->getInt('id', 0);

        // Set the user id for the user to edit in the session.
        $this->app->setUserState('com_ra_events.edit.event.id', $editId);

        // Get the model.
        $model = $this->getModel('Event', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId && $previousId !== $editId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=eventform&layout=edit', false));
    }

    public function extractBookings() {
        echo '<h2>Extract Bookings</h2>';
        echo '<h4>You can copy this report and paste it into a CSV file, then add extra columns as required</h4>';
        $event_id = $this->app->input->getInt('id', '0');
        $label = 'Extract details';
        $link = 'index.php?option=com_ra_events&Itemid=' . $this->menu_id;
        $link .= '&task=event.extractBookings&id=' . $event_id;
        $details .= $this->toolsHelper->buildButton($link, $label, True, 'darkgreen');

        $bookingHelper = new BookingHelper;
        $bookingHelper->extractBookings($event_id);
//        $back = 'index.php?option = com_ra_events&view = events';
//        echo $this->toolsHelper->backButton($back);
    }

    /**
     * Method to save data
     *
     * @return    void
     *
     * @throws  Exception
     * @since   1.0.1
     */
    public function publish() {
        // Checking if the user can remove object
        $user = $this->app->getIdentity();

        if ($user->authorise('core.edit', 'com_ra_events') || $user->authorise('core.edit.state', 'com_ra_events')) {
            $model = $this->getModel('Event', 'Site');

            // Get the user data.
            $id = $this->input->getInt('id');
            $state = $this->input->getInt('state');

            // Attempt to save the data.
            $return = $model->publish($id, $state);

            // Check for errors.
            if ($return === false) {
                $this->setMessage(Text::sprintf('Save failed: %s', $model->getError()), 'warning');
            }

            // Clear the booking id from the session.
            $this->app->setUserState('com_ra_events.edit.event.id', null);

            // Flush the data from the session.
            $this->app->setUserState('com_ra_events.edit.event.data', null);

            // Redirect to the list screen.
//			$this->setMessage(Text::_('COM_RA_EVENTS_ITEM_SAVED_SUCCESSFULLY'));
            $this->app->enqueueMessage('Event updated successfully', 'info');
            $menu = Factory::getApplication()->getMenu();
            $item = $menu->getActive();

            if (!$item) {
                // If there isn't any menu item active, redirect to list view
                $this->setRedirect(Route::_('index.php?option=com_ra_events&view=events', false));
            } else {
                $this->setRedirect(Route::_('index.php?Itemid=' . $item->id, false));
            }
        } else {
            throw new \Exception(500);
        }
    }

    /**
     * Check in record
     *
     * @return  boolean  True on success
     *
     * @since   1.0.1
     */
    public function checkin() {
        // Check for request forgeries.
        $this->checkToken('GET');

        $id = $this->input->getInt('id', 0);
        $model = $this->getModel();
        $item = $model->getItem($id);

        // Checking if the user can remove object
        $user = $this->app->getIdentity();

        if ($user->authorise('core.manage', 'com_ra_events') || $item->checked_out == $user->id) {

            $return = $model->checkin($id);

            if ($return === false) {
                // Checkin failed.
                $message = Text::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError());
                $this->setRedirect(Route::_('index.php?option=com_ra_events&view=event' . '&id=' . $id, false), $message, 'error');
                return false;
            } else {
                // Checkin succeeded.
                $message = Text::_('COM_RA_EVENTS_CHECKEDIN_SUCCESSFULLY');
                $this->setRedirect(Route::_('index.php?option=com_ra_events&view=event' . '&id=' . $id, false), $message);
                return true;
            }
        } else {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    public function registerEmails() {
        // count the number of attendees
        $event_id = $this->app->input->getInt('id', '0');
        $mailshot_id = $this->app->input->getInt('mailshot_id', '0');
        $eventsHelper = new EventsHelper;
        $attendee_count = $eventsHelper->countAttendees($event_id);
        echo 'Attendees for event id ' . $event_id . ': ' . $attendee_count . '<br>';
//        $eventsHelper->updateOutstanding($mailshot_id, $attendee_count);
        // Redirect to the display screen.
        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=event&id=' . $event_id, false));
    }

    /**
     * Remove data
     *
     * @return void
     *
     * @throws Exception
     */
    public function remove() {
        // Checking if the user can remove object
        $user = $this->app->getIdentity();

        if ($user->authorise('core.delete', 'com_ra_events')) {
            $model = $this->getModel('Event', 'Site');

            // Get the user data.
            $id = $this->input->getInt('id', 0);

            // Attempt to save the data.
            $return = $model->delete($id);

            // Check for errors.
            if ($return === false) {
                $this->setMessage(Text::sprintf('Delete failed', $model->getError()), 'warning');
            } else {
                // Check in the booking.
                if ($return) {
                    $model->checkin($return);
                }

                $this->app->setUserState('com_ra_events.edit.event.id', null);
                $this->app->setUserState('com_ra_events.edit.event.data', null);

                $this->app->enqueueMessage(Text::_('COM_RA_EVENTS_ITEM_DELETED_SUCCESSFULLY'), 'success');
                $this->app->redirect(Route::_('index.php?option=com_ra_events&view=events', false));
            }

            // Redirect to the list screen.
            $menu = Factory::getApplication()->getMenu();
            $item = $menu->getActive();
            $this->setRedirect(Route::_($item->link, false));
        } else {
            throw new \Exception(500);
        }
    }

    public function showEmail() {
        /*
         * This can be invoked both view Myemails, view profileform/ layout=bookings
         * and from the task event.showEmails
         * In each case it must return to the same place, passing the appropriate parameters
         */
        $id = $this->app->input->getInt('id', '0');
        $ref_id = $this->app->input->getInt('ref_id', '0');
        $callback = $this->app->input->getAlnum('callback', '');
        $menu_id = $this->app->input->getInt('Itemid', '0');
        echo '<h2>Email ' . $id . '</h2>';
        $this->toolsHelper->showEmail($id);

        $back = 'index.php?option=com_ra_events&Itemid=' . $menu_id;
        if ($callback == 'myemails') {
            $back .= '&view=myemails&user_id=' . $ref_id;
        } elseif ($callback == 'profileform') {
            $back .= '&task=event.showEmails&id=' . $ref_id;
            $back .= '&callback=profileform';
        } else {
            $back .= '&task=event.showEmails&id=' . $ref_id;
        }
        echo $this->toolsHelper->backButton($back);
    }

    public function showEmails() {
        /*
         * Invoked from BookingHelper->showBookings, which in turn can be invoked from:
         *     administrator/tmpl/event/default.php
         *     administrator/tmpl/event/edit.php
         *     tmpl/event/default.php
         * However, for the first two, buttons are not shown, so this ALWAYS returns to event/default
         */

        $event_id = $this->app->input->getInt('id', '0');
        $callback = $this->app->input->getAlnum('callback', '0');

        $sql = 'SELECT title ';
        $sql .= 'FROM #__ra_events WHERE id=' . $event_id;
        $title = $this->toolsHelper->getValue($sql);
        echo '<h2>Emails for ' . $title . '</h2>';

        $target = 'index.php?option=com_ra_events&task=event.showEmail';
        $target .= '&ref_id=' . $event_id;
        $target .= '&callback=' . $callback;
        $target .= '&Itemid=' . $this->menu_id;
        $target .= '&id=';

        $toolsTable = new ToolsTable();
        $toolsTable->add_header("Type,Date,Title,Body,From,To,");
        $sql = 'SELECT id, record_type, date_sent, title, body, ';
        $sql .= 'sender_name, addressee_name ';
        $sql .= 'FROM #__ra_emails ';
        $sql .= 'WHERE sub_system="RA Events" ';
        $sql .= 'AND ref=' . $event_id . ' ';
        $sql .= 'ORDER BY record_type ';
        $rows = $this->toolsHelper->getRows($sql);
        $total = count($rows);
        foreach ($rows as $row) {
            $type = EventsHelper::emailType($row->record_type);
            $toolsTable->add_item($type);
            $toolsTable->add_item(HTMLHelper::_('date', $row->date_sent, 'H:i d/m/y'));
            $toolsTable->add_item($row->title);
            if (strlen($row->body) > 516) {
                $body = strip_tags(substr($row->body, 0, 516)) . ' ....';
//        $link = '';
//        echo $this->toolsHelper->buildLink($link, 'Read more', true, 'readmore') . PHP_EOL;
            } else {
                $body = strip_tags(rtrim($row->body));
            }
            $toolsTable->add_item($body);
            $toolsTable->add_item($row->sender_name);
            $toolsTable->add_item($row->addressee_name);

            $info = $this->toolsHelper->imageButton('I', $target . $row->id);
            $toolsTable->add_item($info);
            $toolsTable->generate_line();
        }

        $toolsTable->generate_table();
        echo 'Total number of emails ' . $total . '<br>';
//      Always return to view event, bur ensure it in turn can return to
//      its calling program
        $back = 'index.php?option=com_ra_events&view=event&id=' . $event_id;
        if (is_numeric($callback)) {
            $back .= '&record_type=' . $callback;
        } elseif ($callback == 'profileform') {
            $back .= '&view=profileform&layout=bookings';
        } else {
            $back .= '&layout=' . $callback;
        }
        echo $this->toolsHelper->backButton($back);
    }

    public function showTerms() {
        $title = 'Bookings: Terms and Conditions';
        $sql = 'SELECT `introtext` from #__content WHERE title="' . $title . '"';
//        echo $sql;
        $introtext = $this->toolsHelper->getValue($sql);
        if (is_null($introtext) OR ($introtext == '')) {
            echo '<h2>Terms of Use</h2>';

            echo 'We store details of your real name and email address for the purposes of communicating to you by email and for managing bookings that you make for Events.' . '<br><br>';

            echo 'In addition, we hold a "preferred name" of your choice, and this is shown above. '
            . 'The organiser of the Event will only be able to see this "preferred name" when using reports from the system.' . '<br><br>';

            echo 'We will never share your personal data with any other organisation. ' . '<br>';
        } else {
            echo $introtext . '<br>';
        }
        echo $this->toolsHelper->backButton($back);
    }

    public function test() {
        $bookingHelper = new BookingHelper;
        $bookingHelper->sendAcknowledgement(106, 1);
    }

}
