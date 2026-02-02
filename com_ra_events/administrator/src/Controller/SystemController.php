<?php

/**
 * @version    2.2.1
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 04/03/25 CB Created
 * 29/03/25 CB max_bookings, ra_event_types
 *  09/09/25 CB deleted function notifyOrganiser
 */

namespace Ramblers\Component\Ra_events\Administrator\Controller;

\defined('_JEXEC') or die;

//use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
// use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;

/**
 * Booking class.
 *
 * @since  4.1.0
 */
class SystemController extends FormController {

    protected $app;
    protected $toolsHelper;
    protected $view_list = 'bookings';

//    protected $params;

    public function __construct() {
        parent::__construct();
        $this->app = Factory::getApplication();
//        $id = $this->app->input->getInt('id', '0');
//        $this->table = Factory::getApplication()->bootComponent('com_ra_events')->getMVCFactory()->createTable('Bookings', 'Administrator');
//        if ($id > 0) {
//            $this->table->load($id);
//        }
        $this->toolsHelper = new ToolsHelper;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancelBooking() {

    }

    public function checkSchema() {

        $helper = New SchemaHelper;

        // new field in ra_events
        $helper->checkColumn('ra_areas', 'bespoke', 'A', 'INT DEFAULT "0" AFTER details; ');
        $sql = 'UPDATE #__ra_areas SET bespoke=0';
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Our footpaths and bridleways span a varied countryside taking in farmland, canals, rivers, fine villages and woodland. Visit our website for more information – www.nottsarearamblers.org.uk' , bespoke=1 WHERE code ='NE'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Shropshire is a diverse county, offering walks in landscapes ranging from the rugged hills of the south to the special landscape of the Meres and Mosses in the north.' , bespoke=1 WHERE code = 'SS'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Supporting the work of the ten groups in the Buckinghamshire, Milton Keynes and West Middlesey Area.' , bespoke=1 WHERE code = 'BU'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'The Area consists of 5 Groups, each aiming to promote walking, arrange locally led walks and protect our rights of way. We also organise campaigning events, rallies and promotional events.' , bespoke=1 WHERE code = 'NP'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'The Herefordshire area helps to look after the paths and green spaces throughout the county. We are a leading voice on walking matters in the county.' , bespoke=1 WHERE code = 'HW'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'The Highlands is one of Europe\'s most unspoilt scenic regions, a rugged landscape of imposing mountains, sheltered fertile glens and scattered offshore islands offering unsurpassed walking for all.' , bespoke=1 WHERE code = 'SC'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'The Wiltshire Ramblers currently total some 1,550 members divided into 6 groups, one of which is our young persons group for 20 to 40 year olds. Our groups work towards the charitable aims of Ramblers in different ways, but one thing they all do is ...' , bespoke=1 WHERE code = 'WE'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'There are eleven Groups in the Lothian and Borders Ramblers Area, each arranges a walks programme suitable for people with different levels of eyperience and fitness.  ' , bespoke=1 WHERE code = 'LB'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'This Area covers most of the former County of Avon.' , bespoke=1 WHERE code = 'AV'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'This Area organises a regular programme of walks for families.' , bespoke=1 WHERE code = 'SO'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Unlike many other Ramblers Areas, Manchester and High Peak itself organises a full led walks programme covering Greater Manchester and beyond, with all walks making use of public transport connections.' , bespoke=1 WHERE code = 'MR'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Visit the Surrey Area website for details of our 17 walking groups covering the county of Surrey and the London boroughs of Croydon, Kingston, Merton, Richmond and Sutton.' , bespoke=1 WHERE code = 'SR'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Walking in Devon you will enjoy both Dartmoor and Eymoor National Parks; (over 630 miles long), The Dartmoor Way, The Two Moors Way, The Tarka Trail and much more' , bespoke=1 WHERE code = 'DN'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'We have 9 Berkshire Area groups: 7 traditional groups covering the county, a 20s & 30s group and a flexi group – something for everybody!' , bespoke=1 WHERE code = 'BK'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'We organise walks to suit all abilities, using car, coach and public transport. <b>When dogs are allowed on our walks they are usually on a lead. Contact the leader if this concerns you.</b>' , bespoke=1 WHERE code = 'MC'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'We support the work of the groups in our Area, including seven geographic and two age-related groups. We work to protect and enhance the Rights of Way in Hertfordshire, Barnet, Enfield and Haringey.' , bespoke=1 WHERE code = 'HF'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'We work with our Groups protecting/enhancing footpaths & countryside; and creating opportunities to enjoy the outdoors through led walks. ' , bespoke=1 WHERE code = 'SW'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Welcome to our beautiful county in The Heart Of England!' , bespoke=1 WHERE code = 'WK'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Welcome to our Cornwall Ramblers page!  We cover a large area with diverse walking, from coastal, to countryside, eyploring industrial mining areas and discovering parish paths and some hidden gems...' , bespoke=1 WHERE code = 'CL'";
        $this->toolsHelper->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Welcome to the Inner London Area Ramblers. Our area has 10 walking groups, three London-wide, and seven based in two or three boroughs, stretching from Heathrow to Thamesmead.' , bespoke=1 WHERE code = 'IL'";
        $this->toolsHelper->executeCommand($sql);

        $sql = 'DELETE FROM #__ra_groups WHERE details="Ramblers Web Editor Group"';
        $this->toolsHelper->executeCommand($sql);
        echo $this->toolsHelper->backButton($back);
    }

    public function createBooking() {
        // redirect to display form
        $target = 'index.php?option=com_ra_events&view=event&id=' . $event_id . '&Itemid=' . $menu_id;
        $this->redirect($target);
    }

    public function getDbVersion($component = 'com_ra_events') {
        $sql = 'SELECT s.version_id ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        return $this->toolsHelper->getValue($sql);
    }

    public function getVersion($component = 'com_ra_events') {
        // This retuns the version as display by System / Manage extensions
        $sql = 'SELECT manifest_cache ';
        $sql .= 'FROM  #__extensions  ';
        $sql .= 'WHERE element="' . $component . '"';
        $data = json_decode($this->toolsHelper->getValue($sql));
        return $data->version;
    }

    private function lookupUsername($id) {
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . $id;
        //       echo "$sql<br>";
        return $this->toolsHelper->getValue($sql);
    }

    public function showBookings() {
        // Only available to the event organiser
        // invoked from tmpl/event/book
        $event_id = $this->app->input->getInt('event_id', '0');

        if ($current->user->id !== $this->item->contact_id) {
            throw new \Exception('This function only available to the event organiser', 403);
        }
        $table = new TableHelper;
        $table->addHeader('Name,Status,Created');

        $sql = 'SELECT p.preferred_name, s.description, b.state, b.created ';
        $sql .= 'FROM #__ra_bookings AS b ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = b.user_id  ';
        $sql .= 'INNER JOIN #__ra_states AS s ON s.id = b.state  ';
        $sql .= 'WHERE b.event_id=' . $event_id;
        $sql .= ' ORDER BY s.seq, p.preferred_name';

        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $table->addItem($row->preferred_name);
            $table->addItem($row->description);
            $table->addItem(HTML('date', $row->created, 'dd/M/yy'));
            $table->addLine();
        }
        $table . showTable();

        $back = 'index.php&option=com_ra_events&view=event&id=' .
                $this->item->id;

        echo $this->toolsHelper->backButton($back);
    }

    public function red($text) {
        echo '<p><span style="color: #ff0000;"><strong>';
        echo $text;
        echo '</strong></span></p>';
    }

    public function test() {
        echo $this->red('Hello');
        if (ComponentHelper::isEnabled('com_ra_events', true)) {
            $this->current_version = $this->getVersion();
            echo 'com_ra_events already present, version=' . $this->getVersion();
            echo ', DB version=' . $this->getDbVersion() . '<br>';
        }
        if (!ComponentHelper::isEnabled('com_ra_tools', true)) {
            echo 'Can only be installed if com_ra_tools is already present';
            return false;
        }

        $tools_required = '3.3.0';
        $tools_version = $this->getVersion('com_ra_tools');
        echo '<p>Version ' . $tools_required . ' of com_ra_tools required<br>';
        if (version_compare($tools_version, $tools_required, 'ge')) {
            echo 'Version ' . $tools_version . ' of com_ra_tools found</p>';
        } else {
            echo 'Version ' . $tools_version . ' of com_ra_tools found</p>';
            echo '<p>WARNING: Requires version of com_ra_tools >=' . $tools_required . '</p>';
            return false;
        }
        return;
        //       echo 'test<br>';
        //       return;
        //       Created2025-03-05 17:20:51
        $sql = 'INSERT INTO #__ra_bookings (created_by, created,event_id,user_id) values ';
        $sql .= '(1,"2025-03-05 17:20:51",4,1)';
        echo $sql;
//        return;
        $this->toolsHelper->executeCommand($sql);
// index.php?option=com_ra_events&task=booking.createBooking&user_id=994&event_id=4
        $id = 1;
//        $this->table = Factory::getTable('#__ra_bookings', 'Administrator');
        if ($id > 0) {
            $this->table->load($id);
            echo 'id ' . $this->id . '<br>';
            echo 'Created ' . $this->table->created . '<br>';
            echo 'Created by ' . $this->table->created_by . '<br>';

            echo 'Event_id ' . $this->table->event_id . '<br>';
            echo 'User_id ' . $this->table->user_id . '<br>';
            echo 'State ' . $this->table->state . '<br>';
        }
        $this->confirmBooking();
//$this->cancel();
//$this->showBookings(1,1,true);
    }

    // localhost/administrator/index.php?option=com_ra_events&task=system.checkUserLinks&id=979
    // Alie Hagendoorn
    private function checkUserlinks($id) {
        // Checks that records exist in
        //  Links User to given group
        $db = Factory::getDbo();
        $helper = New ToolsHelper;

        for ($i = 1; $i < 3; $i++) {
            $sql = 'SELECT COUNT(user_id) FROM #__user_usergroup_map WHERE user_id=' . $id . ' AND group_id=' . $i;
            $record_count = $helper->getValue($sql);
            if ($record_count == 0) {
                $query = $db->getQuery(true);
                $query
                        ->insert($db->quoteName('#__user_usergroup_map'))
                        ->set('user_id =' . $db->quote($id))
                        ->set('group_id=' . $db->quote($i));
                $db->setQuery($query);
                $return = $db->execute();

                if ($return == false) {
                    $this->error = 'Unable to link ' . $this->user_id . ' to ' . $group_id;
                    Factory::getApplication()->enqueueMessage('Unable to link user ' . $group_id, 'Warning');
                }
            }
        }
        return $return;
    }

    public function testUserlinks() {
        $id = $this->app->input->getInt('id', '0');
        $this->checkUserlinks($id);
    }

}
