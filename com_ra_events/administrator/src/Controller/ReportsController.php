<?php

/**
 * @version     2.3.5
 * @package     com_ra_events
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 30/03/25 CB showAwaitingPublication
 * 08/04/25 CB datesToGo
 * 01/05/25 CB showEventsForMonth
 * 05/05/25 CB use tools/showMonthMatrix (not DateMatrix)
 * 17/06/25 CB sharedEvents
 * 30/06/25 CB show Emails
 * 01/07/25 CB importeddEvents
 * 07/07/25 CB breadcrumbs
 * 11/07/25 CB contactsReport
 * 15/09/25 CB two reports for shared events
 * 16/09/25 CB correct report of shared events
 * 03/11/25 CB Show unpublished contacts in red
 * 09/06/26 CB create missing Profiles
 */

namespace Ramblers\Component\Ra_events\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHtml;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

class ReportsController extends FormController {

    protected $breadcrumbs;
    protected $criteria_sql;
    protected $back;
    protected $db;
    protected $objApp;
    protected $toolsHelper;
    protected $prefix;
    protected $query;
    protected $scope;

    public function __construct() {
        parent::__construct();
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->objApp = Factory::getApplication();
        $this->prefix = 'Reports: ';
        $this->back = 'administrator/index.php?option=com_ra_events&view=reports';
        $this->breadcrumbs = $this->toolsHelper->buildLink('administrator/index.php', 'Home Dashboard');
        $this->breadcrumbs .= '>' . $this->toolsHelper->buildLink('administrator/index.php?option=com_ra_tools&view=dashboard', 'RA Dashboard');
        $this->breadcrumbs .= '>' . $this->toolsHelper->buildLink('administrator/index.php?option=com_ra_events&view=reports', 'Event Reports');

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function bookingSummary() {
        ToolBarHelper::title('Bookings summary');
        echo $this->breadcrumbs . '<br>';
        $sql = 'SELECT a.id,a.event_date,DATEDIFF(a.event_date, CURRENT_DATE) AS days_to_go, ';
        $sql .= 'a.event_time, a.event_type_id, event_type.description,title, ';
        $sql .= 'a.num_bookings, a.max_bookings ';
        $sql .= 'FROM `#__ra_events` AS a ';
        $sql .= 'LEFT JOIN #__ra_event_types AS event_type ON event_type.id = a.event_type_id ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = a.contact_id ';
        $sql .= 'WHERE a.state=1 AND a.bookable=1 ';
        $sql .= 'ORDER BY a.event_date DESC';
//        echo $sql;
//        return;
        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable();
//
        $objTable->add_header("Event Date,Days to go,Type,Title,Num bookings,Tot places,Confirmed bookings,Prov bookings,id");
        $sql_lookup = 'SELECT COUNT(id) as `count`, SUM(num_places) as `places` FROM #__ra_bookings ';
        $sql_lookup .= 'WHERE event_id=';
        foreach ($rows as $row) {
            $date = HTMLHelper::_('date', $row->event_date, 'd M y');
            if ($row->event_type_id == 4) {
                $date .= ' - ' . HTMLHelper::_('date', $row->event_date_end, 'd M y');
            } else {
                $date .= ' ' . $row->event_time;
            }
            $objTable->add_item($date);
            $objTable->add_item($row->days_to_go);
            $objTable->add_item($row->description);
            $objTable->add_item($row->title);
            $objTable->add_item($row->num_bookings . '/' . $row->max_bookings);

            $stats = $this->toolsHelper->getItem($sql_lookup . $row->id . ' AND state=1');
            if ($stats->count == 0) {
                $objTable->add_item('');
                $objTable->add_item('');
            } else {
                $objTable->add_item($stats->count);
                $objTable->add_item($stats->places);
            }

            $stats = $this->toolsHelper->getItem($sql_lookup . $row->id . ' AND state=0');
            if ($stats->count == 0) {
                $objTable->add_item('');
            } else {
                $objTable->add_item($stats->count);
            }

            $objTable->add_item($row->id);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton($this->back);
    }

    public function bookingsByUser() {
        ToolBarHelper::title('Bookings by User');
        echo $this->breadcrumbs . '<br>';
        $objTable = new ToolsTable();
        $objTable->add_header('Group,Name,Count bookings,Count places, Count mail lists');
        $sql = 'SELECT b.user_id, p.home_group, p.preferred_name, COUNT( b.id) AS num, ';
        $sql .= 'SUM(b.num_places) AS tot_places ';
        $sql .= 'FROM #__ra_profiles AS p INNER JOIN #__ra_bookings AS b ON b.user_id = p.id ';
        $sql .= 'GROUP BY b.user_id, p.home_group, p.preferred_name ';
        $sql .= 'ORDER BY p.home_group, p.preferred_name ';

        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->home_group);
            $objTable->add_item($row->preferred_name);
            $objTable->add_item($row->num);
            $objTable->add_item($row->tot_places);
            $sql = 'SELECT COUNT( s.id) ';
            $sql .= 'FROM #__ra_mail_subscriptions AS s ';
            $sql .= 'WHERE s.user_id=' . $row->user_id;
            $count = $this->toolsHelper->getValue($sql);
            $objTable->add_item($count);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton($this->back);
    }

    private function breadcrumbsExtra($label, $report) {
        // generates a link to be added to the standard breadcrumbs
        $target = 'administrator/index.php?option=com_ra_events&task=reports.' . $report;
        return '>' . $this->toolsHelper->buildLink($target, $label);
    }

    public function contactsReport() {
        ToolBarHelper::title('Contact names');
        echo $this->breadcrumbs . '<br>';
//      Check and fix Contacts without a profile records
        $sql = 'SELECT c.user_id, u.name ';
        $sql .= 'FROM `#__contact_details`AS c ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS p ON p.id = c.user_id ';
        $sql .= 'LEFT JOIN `#__users` AS u ON u.id = c.user_id ';
        $sql .= 'WHERE u.block =0 ';
        $sql .= 'AND p.preferred_name IS NULL ';
        $sql .= 'ORDER BY c.id';
        $rows = $this->toolsHelper->getRows($sql);

        foreach ($rows as $row) {
 $this->createProfile($row->user_id,$row->name);
        }
        // See if any Events without preferred_name
        $sql = 'SELECT e.id,e.event_date, e.title, e.contact_id, e.state,u.email, ';
        $sql .= 'c.published, p.preferred_name ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = c.user_id ';
        $sql .= 'LEFT JOIN #__users AS u ON u.id = c.user_id ';
        $sql .= 'WHERE c.id IS NULL ';
        $sql .= 'OR c.user_id = 0 ';
        $sql .= 'OR c.published = 0 ';
        $sql .= 'OR p.preferred_name IS NULL ';
        $rows = $this->toolsHelper->getRows($sql);
        if (count($rows) > 0) {
            echo '<h2>Events with invalid contact </h2>';
 //           $this->toolsHelper->showQuery($sql);
        }

//      Show details
        $sql = 'SELECT c.id, c.name AS `contact`, c.user_id, c.published,';
        $sql .= 'u.name, p.preferred_name,u.email ';
        $sql .= 'FROM `#__contact_details`AS c ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS p ON p.id = c.user_id ';
        $sql .= 'LEFT JOIN `#__users` AS u ON u.id = c.user_id ';
        $sql .= 'WHERE u.block =0 ';
        $sql .= 'ORDER BY c.id';

        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable();

        $sql = 'SELECT COUNT(id) FROM #__ra_events WHERE contact_id=';
        $target_drilldown = 'administrator/index.php?option=com_ra_events&task=reports.showEventsForContact';
        $objTable->add_header('Contact name, Contact user_id, Real name, Preferred name, Email,Contact id,Events');
        foreach ($rows as $row) {
            if ($row->published == 0) {
                $details = '<div style="color:red">' . $row->contact . ' unpublished</div>';
            } else {
                $details = $row->contact;
            }
            $objTable->add_item($details);
            $objTable->add_item($row->user_id);
            $objTable->add_item($row->name);
            $objTable->add_item($row->preferred_name);
            $objTable->add_item($row->email);
            $objTable->add_item($row->id);
            $count = $this->toolsHelper->getValue($sql . $row->id);
            $target = $target_drilldown . '&id=' . $row->id;
//            $space = array(' ');
//            $hex = array('%20');
//            $name = str_replace($space, $hex, $row->preferred_name);
            $name = str_replace(' ', '', $row->preferred_name);
            $target .= '&name=' . $name;
$objTable->add_item($this->toolsHelper->buildLink($target, $count));
            $objTable->generate_line();
        }

        $objTable->generate_table();
        echo count($rows) . ' Contacts<br>';

        echo $this->toolsHelper->backButton($this->back);
    }

private function createProfile($id,$preferred_name) {
    $sql = 'INSERT INTO `#__ra_profiles` (`id`, `home_group`,`preferred_name`, `state`) ';
    $sql .= 'VALUES ("' . $id . '","","' . $preferred_name . '",1);';
//    echo "$sql<br>";
    $this->toolsHelper->executeCommand($sql);
}

public function createProfiles() {
//      Check and fix Users without a profile records
        $sql = 'SELECT u.id, u.name ';
        $sql .= 'FROM `#__users`AS u ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS p ON p.id = u.id ';
        $sql .= 'WHERE u.block =0 ';
        $sql .= 'AND p.preferred_name IS NULL ';
        $rows = $this->toolsHelper->getRows($sql);
//    echo "$sql<br>";
        foreach ($rows as $row) {
           $this->createProfile($row->id,$row->name);
        }
        echo $this->toolsHelper->rows . ' created<br>';
        echo $this->toolsHelper->backButton($this->back);
}

    public function datesToGo() {
        ToolBarHelper::title('Events by Date intervals');
        echo $this->breadcrumbs . '<br>';
        $sql = 'SELECT a.id,a.event_date,DATEDIFF(a.event_date, CURRENT_DATE) AS days_to_go, ';
        $sql .= 'a.event_time, a.event_type_id, event_type.description,title, ';
        $sql .= 'a.publication_date,DATEDIFF(a.publication_date, CURRENT_DATE) AS pub_to_go ';
        $sql .= 'FROM `#__ra_events` AS a ';
        $sql .= 'LEFT JOIN #__ra_event_types AS event_type ON event_type.id = a.event_type_id ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = a.contact_id ';
        $sql .= 'WHERE a.state = 1 ';
        $sql .= 'ORDER BY a.event_date DESC';

//        $this->toolsHelper->showSql($sql);
        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable();
//
        $objTable->add_header("Event Date,Days to go,Type,Title,Publication date,Days to publication,id");
        foreach ($rows as $row) {
//            $objTable->add_item($row->api_site_id);
            $date = HTMLHelper::_('date', $row->event_date, 'd M y');
            if ($row->event_type_id == 4) {
                $date .= ' - ' . HTMLHelper::_('date', $row->event_date_end, 'd M y');
            } else {
                $date .= ' ' . $row->event_time;
            }
            $objTable->add_item($date);
            $objTable->add_item($row->days_to_go);
            $objTable->add_item($row->description);
            $objTable->add_item($row->title);
            $objTable->add_item($row->publication_date);
            $objTable->add_item($row->pub_to_go);
            $objTable->add_item($row->id);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton($this->back);
    }

public function missingProfiles() {
        $sql = 'SELECT u.id, u.name, u.email ';
        $sql .= 'FROM `#__users`AS U ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS p ON p.id = c.user_id ';
        $sql .= 'WHERE u.block =0 ';
        $sql .= 'AND p.preferred_name IS NULL ';
        $sql .= 'ORDER BY c.id';
        $rows = $this->toolsHelper->sgetRows($sql);
        if (count($rows) > 0) {
          echo '<h2>Users without profile records </h2>';
          $this->toolsHelper->showQuery($sql);
          echo $this->toolsHelper->rows . '<br>';
        }
       $target = 'administrator/index.php?option=com_ra_events&task=reports.createProfiles';
       $rows = $this->toolsHelper->buildButton($target,'Generate');
    $this->toolsHelper->backButton($this->back);
}
//    public function importedEvents() {
//        ToolBarHelper::title('Imported Events');
//        echo $this->breadcrumbs . '<br>';
//        $sql = 'UPDATE `#__ra_events`SET api_site_id = NULL WHERE api_site_id =0';
//        $this->toolsHelper->executeCommand($sql);
//        $sql = 'SELECT e.id, e.event_date AS `Date`,e.title,e.group_code,e.share_date, e.api_site_id, ';
//        $sql .= 'e.original_id, c.name, u.email, t.description ';
//        $sql .= 'FROM `#__ra_events` AS e ';
//        $sql .= 'INNER JOIN #__ra_event_types as t ON t.id = e.event_type_id ';
//        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
//        $sql .= 'LEFT JOIN #__users AS u ON u.id = c.user_id ';
//        $sql .= 'WHERE e.api_site_id IS NOT NULL ';
//        $sql .= 'ORDER BY e.api_site_id, original_id';
//
////        $this->toolsHelper->showSql($sql);
//        $rows = $this->toolsHelper->getRows($sql);
//        $objTable = new ToolsTable();
//
//        $objTable->add_header("Site,Original id,Event date,Type,Title,Share date,Contact,Group,id");
//        foreach ($rows as $row) {
//            $objTable->add_item($row->api_site_id);
//            $objTable->add_item($row->original_id);
//            $objTable->add_item($row->Date);
//            $objTable->add_item($row->description);
//            $objTable->add_item($row->title);
//            $objTable->add_item($row->share_date);
//            $objTable->add_item($row->name);
//            $objTable->add_item($row->group_code);
//            $objTable->add_item($row->id);
//            $objTable->generate_line();
//        }
//
//        $objTable->generate_table();
//        echo count($rows) . ' imported Events<br>';
//        echo $this->toolsHelper->backButton($this->back);
//    }

    public function provisionalBookings() {
        ToolBarHelper::title('Provisional Bookings');
        echo $this->breadcrumbs . '<br>';
        $target_edit = 'administrator/index.php?option=com_ra_events&task=booking.edit';
        $target_edit .= '&callback=provisionals&id=';
        $sql = 'SELECT e.event_date, e.event_date_end, e.title, e.group_code, ';
        $sql .= 'DATEDIFF(e.event_date, CURRENT_DATE) AS days_to_go, ';
        $sql .= 'e.event_time, e.event_type_id, c.name as `contact`, ';
        $sql .= 'p.preferred_name, t.description as event_type, ';
        $sql .= 'b.id, b.num_places, b.partner, member.preferred_name as `member` ';
        $sql .= 'FROM `#__ra_events` AS e ';
        $sql .= 'INNER JOIN #__ra_bookings AS `b` ON `b`.event_id = e.id ';
        $sql .= 'LEFT JOIN #__ra_event_types AS `t` ON `t`.id = e.`event_type_id` ';
        $sql .= 'LEFT JOIN #__contact_details AS `c` ON `c`.id = e.`contact_id` ';
        $sql .= 'LEFT JOIN #__ra_profiles AS `p` ON `p`.id = c.user_id ';
        $sql .= 'LEFT JOIN #__ra_profiles AS `member` ON `member`.id = b.user_id ';
        $sql .= 'WHERE b.state=0 ';
        $sql .= 'ORDER BY e.event_date ';
//        echo $sql . '<br>';
        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable;
$objTable->add_header('Date,Type,Title,Contact,Places,Participants,Booking');
        foreach ($rows as $row) {
            $date = HTMLHelper::_('date', $row->event_date, 'd M y');
            if ($row->event_type_id == 4) {
                $date .= ' - ' . HTMLHelper::_('date', $row->event_date_end, 'd M y');
            } else {
                $date .= ' ' . $row->event_time;
            }
            $objTable->add_item($date);

            $objTable->add_item($row->event_type);
            $objTable->add_item($row->title);
            if (is_null($row->preferred_name)) {
                $contact = $row->contact;
            } else {
                $contact = $row->preferred_name;
            }
            $objTable->add_item($contact);
            $objTable->add_item($row->num_places);

            if (is_null($row->partner)) {
                $objTable->add_item($row->member);
            } else {
                $bookings = $row->member;
                if ($row->partner !== '') {
                    $bookings .= '/' . $row->partner;
                }
                $objTable->add_item($bookings);
            }
            $link = $this->toolsHelper->buildLink($target_edit . $row->id, 'Edit');
            $objTable->add_item($link);
            if ($row->days_to_go < 0) {
                $objTable->generate_line('red');
            } else {
                $objTable->generate_line();
            }
        }
        $objTable->generate_table();
        $target = "administrator/index.php?option=com_ra_events&task=reports.showEventsByMonth";
        echo $this->toolsHelper->backButton($target);
    }

    private function setScopeCriteria() {
        switch ($this->scope) {
            case ($this->scope == 'D');
                $this->query->where('state<>1');
                break;
            case ($this->scope == 'F');
                $this->query->where('state=1');
                $this->query->where('datediff(walk_date, CURRENT_DATE) >= 0');
                break;
            case ($this->scope == 'H');
                $this->query->where('state=1');
                $this->query->where('datediff(walk_date, CURRENT_DATE) < 0');
        }
    }

    private function setSelectionCriteria($mode, $opt) {
        if ($mode == 'G') {
            $this->query->where("groups.code='" . $opt . "'");
        } else {
            if ($opt == 'NAT') {

            } else {
$this->query->where("SUBSTR(groups.code,1,2)='" . $opt . "'");
            }
        }
    }

    public function sharedEvents() {
        ToolBarHelper::title('Shared Events');
        echo $this->breadcrumbs . '<br>';
        $sql = 'UPDATE `#__ra_events`SET api_site_id = NULL WHERE api_site_id =0';
        $this->toolsHelper->executeCommand($sql);

        $sql = 'SELECT e.id, e.event_date,e.title,e.group_code,e.share_date, ';
        $sql .= 'e.num_bookings, e.max_bookings, e.state, e.location, ';
        $sql .= 'c.name, t.description, ';
        $sql .= 'DATEDIFF(e.event_date, CURRENT_DATE) as days_to_go ';
        $sql .= 'FROM `#__ra_events` AS e ';
        $sql .= 'INNER JOIN #__ra_event_types as t ON t.id = e.event_type_id ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';

        $sql .= 'WHERE e.shareable=1 ';
        $sql .= 'AND e.api_site_id IS NULL ';
        $sql .= 'AND DATEDIFF(e.event_date, CURRENT_DATE) > 0 ';
        $sql .= 'AND DATEDIFF(e.share_date,CURRENT_DATE) < 0 ';
        $sql .= 'ORDER BY e.event_date DESC';

//        $this->toolsHelper->showSql($sql);
        $rows = $this->toolsHelper->getRows($sql);
        if (count($rows) == 0) {
            echo '<h4>No future Events are being shared</h4>';
        } else {
            echo '<h4>Future Events that are being shared</h4>';
            $objTable = new ToolsTable();

            $objTable->add_header("Event date,Days,Type,Title,Location,Bookings,Contact,Share date,State");
            foreach ($rows as $row) {
                $objTable->add_item(HTMLHelper::_('date', $row->event_date, 'd-M-y'));
                $objTable->add_item($row->days_to_go);
                $objTable->add_item($row->description);
                $objTable->add_item($row->title);
                $objTable->add_item($row->location);
                $objTable->add_item($row->num_bookings . '/' . $row->max_bookings);
                $objTable->add_item($row->name);
                $objTable->add_item(HTMLHelper::_('date', $row->share_date, 'd-M-y'));
                $objTable->add_item($row->state);
//                $objTable->add_item($row->id);
                $objTable->generate_line();
            }
            $objTable->generate_table();
            echo count($rows) . ' Events<br><br>';
        }
        // Show Shared Events from other sites
        $sql = 'SELECT e.id, e.event_date,e.title AS `event`,e.share_date, e.api_site_id, ';
        $sql .= 'e.num_bookings, e.max_bookings, e.location, c.name, s.title, t.description ';

        $sql .= 'FROM `#__ra_events` AS e ';
        $sql .= 'INNER JOIN #__ra_event_types as t ON t.id = e.event_type_id ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'LEFT JOIN #__ra_api_sites AS s ON s.id = e.api_site_id ';
        $sql .= 'WHERE e.api_site_id IS NOT NULL ';
//        $sql .= 'AND DATEDIFF(e.event_date, CURRENT_DATE) > 0 ';
        $sql .= 'ORDER BY s.title, e.event_date DESC';

        $rows = $this->toolsHelper->getRows($sql);
        if (count($rows) == 0) {
            echo '<h4>No shared Events have been imported</h4>';
        } else {
            echo '<h4>Events that have been imported</h4>';
            $objTable = new ToolsTable();

            $objTable->add_header("Site,Event date,Type,Title,Location,Bookings,Contact");
            foreach ($rows as $row) {
                $objTable->add_item($row->title);
                $objTable->add_item(HTMLHelper::_('date', $row->event_date, 'd-M-y'));
                $objTable->add_item($row->description);
                $objTable->add_item($row->event);
                $objTable->add_item($row->location);
                $objTable->add_item($row->num_bookings . '/' . $row->max_bookings);
                $objTable->add_item($row->name);

                $objTable->generate_line();
            }
            $objTable->generate_table();
            echo count($rows) . ' Events<br>';
            if ($this->toolsHelper->isSuperuser()) {
                $target = 'administrator/index.php?option=com_ra_tools&task=apisites.deleteSharedEvents';
                echo "$target<br>";
                echo $this->toolsHelper->buildButton($target, 'Delete all imported Events', false, 'red');
            }
        }

        echo $this->toolsHelper->backButton($this->back);
    }

    public function showAwaitingPublication() {
        ToolBarHelper::title('Events awaiting publication');
        echo $this->breadcrumbs . '<br>';
        $sql = 'SELECT e.event_date, t.description, e.title, e.publication_date, e.bookable, e.shareable, e.share_date ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__ra_event_types as t ON t.id = e.event_type_id ';
        $sql .= 'WHERE (DATEDIFF(e.event_date, CURRENT_DATE)>=0) ';
        $sql .= 'AND (e.state>0) ';
        $sql .= 'AND ((DATEDIFF(e.publication_date, CURRENT_DATE)>=0) ';
        $sql .= 'OR ((e.shareable = 1) AND (DATEDIFF(e.share_date, CURRENT_DATE)>=0))) ';
        $sql .= ' ORDER BY e.event_date';
//        echo $sql . '<br>';
        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable();

        $objTable->add_header("Event date,Type,Title,Publication date,Bookable,Shareable,Share date");
        foreach ($rows as $row) {

            $objTable->add_item($row->event_date);
            $objTable->add_item($row->description);
            $objTable->add_item($row->title);
            $objTable->add_item($row->publication_date);
            $objTable->add_item($row->bookable);
            $objTable->add_item($row->shareable);
            $objTable->add_item($row->share_date);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showEventsByGroup() {
        ToolBarHelper::title('Events by Group');
        echo $this->breadcrumbs . '<br>';
        $sql = "SELECT a.group_code AS 'GroupCode', count(a.id) AS `Number`, ";
        $sql .= "MIN(a.event_date) AS 'Earliest', ";
        $sql .= "MAX(a.event_date) as 'Latest' ";
        $sql .= "FROM #__ra_events AS a ";
        $sql .= 'GROUP BY a.group_code ';
        $sql .= 'ORDER BY a.group_code ';
        $this->toolsHelper->showSql($sql);
        /*
          $rows = $this->toolsHelper->getRows($sql);
          //      Show link that allows page to be printed
          $target = 'index.php?option=com_ra_tools&task=reports.countUsers';
          echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
          $objTable = new ToolsTable;
          $objTable->add_header("Code,Group,Count,Earliest walk,Latest walk");
          $target = 'administrator/index.php?option=com_ra_tools&task=reports.showUsersForGroup&group=';
          foreach ($rows as $row) {
          if ($row->GroupCode == '') {
          $objTable->add_item('');
          } else {
          // URI cannot handle commas as part of the parameters
          //$param = str_replace(',', '%5C%2C%20', $row->GroupCode);
          $param = str_replace(',', '_', $row->GroupCode);
$objTable->add_item($this->toolsHelper->buildLink($target . $param, $row->GroupCode));
//$objTable->add_item($this->toolsHelper->buildLink($target . $row->GroupCode, $row->GroupCode));
          }
          $objTable->add_item($row->name);
          $objTable->add_item($row->Number);
          $objTable->add_item($row->Earliest);
          $objTable->add_item($row->Latest);
          $objTable->generate_line();
          }
          $objTable->generate_table();
         *
         */
        echo $this->toolsHelper->backButton($this->back);
//        echo "<p>";
    }

    public function showEventsByMonth() {
//        ToolBarHelper::title('Events by month');
        echo $this->breadcrumbs . '<br>';
        $field = 'event_date';
        $table = ' #__ra_events';
        $criteria = '';
        $title = 'Events by month';
        $link = 'administrator/index.php?option=com_ra_events&task=reports.showEventsForMonth';
        $back = $this->back;
        $this->toolsHelper->showMonthMatrix($field, $table, $criteria, $title, $link, $back);
    }

    public function showEventsByType() {
        ToolBarHelper::title('Events by Type');
        echo $this->breadcrumbs . '<br>';
        $sql = "SELECT t.description as `Type`, count(a.id) AS `Number` ";
        $sql .= "FROM #__ra_events AS a ";
        $sql .= "LEFT JOIN #__ra_event_types AS t ON t.id = a.event_type_id ";
        $sql .= 'GROUP BY t.`description` ';
        $sql .= 'ORDER BY t.`description` ';
        $this->toolsHelper->showSql($sql);
        /*
          $rows = $this->toolsHelper->getRows($sql);
          //      Show link that allows page to be printed
          $target = 'index.php?option=com_ra_tools&task=reports.countUsers';
          echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
          $objTable = new ToolsTable;
          $objTable->add_header("Code,Group,Count,Earliest walk,Latest walk");
          $target = 'administrator/index.php?option=com_ra_tools&task=reports.showUsersForGroup&group=';
          foreach ($rows as $row) {

          $objTable->add_item($row->name);
          $objTable->add_item($row->Number);
          $objTable->add_item($row->Earliest);
          $objTable->add_item($row->Latest);
          $objTable->generate_line();
          }
          $objTable->generate_table();
         *
         */
        echo $this->toolsHelper->backButton($this->back);
//        echo "<p>";
    }

    public function showEventsForContact() {
        $contact_name = $this->objApp->input->getWord('name', '');
        $contact_id = $this->objApp->input->getInt('id', '0');
        ToolBarHelper::title('Events for Contact ' . $contact_name);
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Contact names', 'contactsReport');

        $bookingHelper = new BookingHelper;
        $sql = 'SELECT e.id, e.event_date, e.event_date_end, e.title, e.group_code, ';
        $sql .= 'e.event_time, e.event_type_id, c.name as `contact`, ';
        $sql .= 'p.preferred_name, t.description as event_type, e.bookable, ';
        $sql .= 'e.max_bookings ';
        $sql .= 'FROM `#__ra_events` AS e ';
        $sql .= 'LEFT JOIN #__ra_event_types AS `t` ON `t`.id = e.`event_type_id` ';
        $sql .= 'LEFT JOIN #__contact_details AS `c` ON `c`.id = e.`contact_id` ';
        $sql .= 'LEFT JOIN #__ra_profiles AS `p` ON `p`.id = c.user_id ';
        $sql .= 'WHERE e.contact_id=' . $contact_id;
        $sql .= ' ORDER BY e.event_date DESC';
//        echo $sql . '<br>';

        $rows = $this->toolsHelper->getRows($sql);
        if (count($rows) == 0) {
            echo '<br>No Events found<br>';
        } else {
            $objTable = new ToolsTable;
$objTable->add_header('Date,Type,Title,Contact,Group,Bookable,bookings');
            foreach ($rows as $row) {
                $date = HTMLHelper::_('date', $row->event_date, 'd M y');
                if ($row->event_type_id == 4) {
                    $date .= ' - ' . HTMLHelper::_('date', $row->event_date_end, 'd M y');
                } else {
                    $date .= ' ' . $row->event_time;
                }
                $objTable->add_item($date);

                $objTable->add_item($row->event_type);
                $objTable->add_item($row->title);
                if (is_null($row->preferred_name)) {
                    $contact = $row->contact;
                } else {
                    $contact = $row->preferred_name;
                }
                $objTable->add_item($contact);
                $objTable->add_item($row->group_code);
                $objTable->add_item($row->bookable);
                $count = $bookingHelper->countBookings($row->id);
                $bookings = $count . '/' . $row->max_bookings;
                $objTable->add_item($bookings);
                $objTable->generate_line();
            }
            $objTable->generate_table();
            echo count($rows) . ' Events<br>';
        }


        $back = 'administrator/index.php?option=com_ra_events&task=reports.contactsReport';
        echo $this->toolsHelper->backButton($back);
    }

    public function showEventsForMonth() {
        // Not possible to LEFT join onto bookings as this would include provisional bookings
        $year = $this->objApp->input->getInt('year', '2025');
        $month = $this->objApp->input->getInt('month', '5');
        ToolBarHelper::title('Events for ' . $month . '/' . $year);
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Events by month', 'showEventsByMonth');
        $bookingHelper = new BookingHelper;
        $sql = 'SELECT e.id, e.event_date, e.event_date_end, e.title, e.group_code, ';
        $sql .= 'e.event_time, e.event_type_id, c.name as `contact`, ';
        $sql .= 'p.preferred_name, t.description as event_type, e.bookable, ';
        $sql .= 'e.max_bookings ';
        $sql .= 'FROM `#__ra_events` AS e ';
        $sql .= 'LEFT JOIN #__ra_event_types AS `t` ON `t`.id = e.`event_type_id` ';
        $sql .= 'LEFT JOIN #__contact_details AS `c` ON `c`.id = e.`contact_id` ';
        $sql .= 'LEFT JOIN #__ra_profiles AS `p` ON `p`.id = c.user_id ';
        $sql .= 'WHERE YEAR(e.event_date)="' . $year . '" AND MONTH(e.event_date)="' . $month . '" ';
        $sql .= 'ORDER BY e.event_date ';
        //       echo $sql . '<br>';
        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable;
$objTable->add_header('Date,Type,Title,Contact,Group,Bookable,bookings');
        foreach ($rows as $row) {
            $date = HTMLHelper::_('date', $row->event_date, 'd M y');
            if ($row->event_type_id == 4) {
                $date .= ' - ' . HTMLHelper::_('date', $row->event_date_end, 'd M y');
            } else {
                $date .= ' ' . $row->event_time;
            }
            $objTable->add_item($date);

            $objTable->add_item($row->event_type);
            $objTable->add_item($row->title);
            if (is_null($row->preferred_name)) {
                $contact = $row->contact;
            } else {
                $contact = $row->preferred_name;
            }
            $objTable->add_item($contact);
            $objTable->add_item($row->group_code);
            $objTable->add_item($row->bookable);
            $count = $bookingHelper->countBookings($row->id);
            $bookings = $count . '/' . $row->max_bookings;
            $objTable->add_item($bookings);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        $target = "administrator/index.php?option=com_ra_events&task=reports.showEventsByMonth";
        echo $this->toolsHelper->backButton($target);
    }

    public function showFeed() {
        ToolBarHelper::title($this->prefix . 'Feed update for ' . $this->toolsHelper->lookupGroup($group_code));
        echo $this->breadcrumbs . '<br>';
        $group_code = $this->objApp->input->getCmd('group_code', 'NS03');
        $this->scope = $this->objApp->input->getCmd('scope', '');
        $csv = substr($this->objApp->input->getCmd('csv', ''), 0, 1);

        $objTable = new ToolsTable();

        $objTable->add_header("Date,Message");
        $sql = "SELECT date_amended, field_value ";
        $sql .= "FROM #__ra_groups_audit AS audit ";
        $sql .= "INNER JOIN #__ra_groups `groups` ON `groups`.id = audit.object_id ";
        $sql .= "WHERE `groups`.code='" . $group_code . "' ";
        $sql .= 'ORDER BY date_amended DESC ';
        //        echo $sql;
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->date_amended);
            $objTable->add_item($row->field_value);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        $back = "administrator/index.php?option=com_ra_tools&view=reports_group&group_code=" . $group_code . '&scope=' . $this->scope;
        echo $this->toolsHelper->backButton($back);
//        if ($csv == '') {
//            $target = "administrator/index.php?option=com_ra_tools&task=reports.showFeed&csv=feed&group_code=" . $group_code . '&scope=' . $this->scope;
//            echo $this->toolsHelper->buildLink($target, "Extract as CSV", False,  "btn btn-small button-new");
//        }
    }

    public function showFeedSummary() {
        $this->scope = $this->objApp->input->getCmd('scope', '');
        $csv = substr($this->objApp->input->getCmd('csv', ''), 0, 1);
        echo "<h2>Feed Summary</h2>";
        $objTable = new ToolsTable();
        $objTable->set_csv($csv);

        $objTable->add_header("Date,Message");
        $sql = "SELECT log_date, message ";
        $sql .= "FROM #__ra_logfile ";
        $sql .= "WHERE record_type='B9' AND ref=2 ";
        $sql .= 'ORDER BY log_date DESC ';
        $sql .= "Limit 28";
        //        echo $sql;
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->log_date);
            $objTable->add_item($row->message);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        $back = "administrator/index.php?option=com_ra_tools&view=reports_area&area=NAT&scope=" . $this->scope;
        echo $this->toolsHelper->backButton($back);
        if ($csv == '') {
            $target = "administrator/index.php?option=com_ra_tools&task=reports.showFeedSummary&csv=feedSummary";
            echo $this->toolsHelper->buildLink($target, "Extract as CSV", False, "btn btn-small button-new");
        }
    }

    public function showFeedSummaryArea() {
        $area = $this->objApp->input->getCmd('area_code', 'NS');
        $this->scope = $this->objApp->input->getCmd('scope', '');
        $current_group = '';
        $groups_count = 0;
        $groups_found = 0;
        $area_code = 'NS';
        echo "<h2>Feed update for " . $this->toolsHelper->lookupArea($area) . "</h2>";
        $sql = "SELECT code from #__ra_groups where code LIKE '" . $area . "%' ORDER BY code";
        $objTable = new ToolsTable();
        $objTable->add_header("Group,Date,Message");

        $groups = $this->toolsHelper->getRows($sql);
        $groups_count = $this->toolsHelper->rows;
        foreach ($groups as $group) {
            $sql = "SELECT `groups`.code, date_amended, field_value ";
            $sql .= "FROM #__ra_groups_audit AS audit ";
            $sql .= "INNER JOIN #__ra_groups `groups` ON `groups`.id = audit.object_id ";
            $sql .= "WHERE `groups`.code='" . $group->code . "' ";
            $sql .= 'ORDER BY date_amended DESC LIMIT 7';
//            echo $sql . '<br>';
            $rows = $this->toolsHelper->getRows($sql);
            foreach ($rows as $row) {
                if ($current_group == $row->code) {

                } else {
                    $groups_found++;
                    $current_group = $row->code;
                }
                $objTable->add_item($group->code);
                $objTable->add_item($row->date_amended);
                $objTable->add_item($row->field_value);
                $objTable->generate_line();
            }
        }

        $objTable->generate_table();
        echo $groups_found . " groups out of " . $groups_count;
        $back = "administrator/index.php?option=com_ra_tools&view=reports_area&area=" . $area . '&scope=' . $this->scope;
        echo $this->toolsHelper->backButton($back);
    }

    public function showLogfile() {

        $offset = $this->objApp->input->getCmd('offset', '0');
        $next_offset = $offset - 1;
        $previous_offset = $offset + 1;

        $date_difference = (int) $offset;
        $today = date_create(date("Y-m-d 00:00:00"));
        if ($date_difference === 0) {
            $target = $today;
        } else {
            if ($date_difference > 0) { // positive number
                $target = date_add($today, date_interval_create_from_date_string("-" . $date_difference . " days"));
            } else {
                $target = date_add($today, date_interval_create_from_date_string($date_difference . " days"));
            }
        }
        ToolBarHelper::title($this->prefix . 'Logfile records for ' . date_format($target, "D d M"));

        $sql = "SELECT date_format(log_date, '%a %e-%m-%y') as Date, ";
        $sql .= "date_format(log_date, '%H:%i:%s.%u') as Time, ";
        $sql .= "record_type, ";
        $sql .= "ref, ";
        $sql .= "message ";
        $sql .= "FROM #__ra_logfile ";
        $sql .= "WHERE log_date >='" . date_format($target, "Y/m/d H:i:s") . "' ";
        $sql .= "AND log_date <'" . date_format($target, "Y/m/d 23:59:59") . "' ";
        $sql .= "ORDER BY log_date DESC, record_type ";
        if ($this->toolsHelper->showSql($sql)) {
            echo "<h5>End of logfile records for " . date_format($target, "D d M") . "</h5>";
        } else {
            echo 'Error: ' . $this->toolsHelper->error . '<br>';
        }

        echo $this->toolsHelper->buildLink("administrator/index.php?option=com_ra_tools&task=reports.showLogfile&offset=" . $previous_offset, "Previous day", False, 'grey');
        if ($next_offset >= 0) {
            echo $this->toolsHelper->buildLink("administrator/index.php?option=com_ra_tools&task=reports.showLogfile&offset=" . $next_offset, "Next day", False, 'teal');
        }
        $target = "administrator/index.php?option=com_ra_tools&view=reports";
        echo $this->toolsHelper->backButton($target);
    }

    public function showSummary() {
        $csv = substr($this->objApp->input->getCmd('csv', ''), 0, 1);
        $group_code = $this->objApp->input->getCmd('group_code', 'NS03');
        $scope = $this->objApp->input->getCmd('scope', 'F');
        echo "<h2>Walks history for " . $this->toolsHelper->lookupGroup($group_code) . "</h2>";
        $objTable = new ToolsTable();
        if ($csv === 'Y') {
            $objTable->set_csv('Summary');
        }
        $objTable->add_header("Month, Total walks,Joint walks,Guest walks,Total leaders,Total miles, Min miles,Max miles,Avg miles");
        $sql = "SELECT ym,num_walks,joint_walks,guest_walks, ";
        $sql .= "num_leaders,total_miles,min_miles,max_miles,avg_miles ";
        $sql .= "FROM #__ra_snapshot ";
        $sql .= "WHERE group_code='" . $group_code . "' ";
        $sql .= 'ORDER BY ym ';
//        echo $sql;
        $rows = $this->toolsHelper->getRows($sql);
        $total_miles = 0;
        $total_walks = 0;
        foreach ($rows as $row) {
            $total_miles += $row->total_miles;
            $total_walks += $row->num_walks;

            $objTable->add_item($row->ym);
            if ($row->num_walks == 0) {
                $objTable->add_item('');
            } else {
                $objTable->add_item($row->num_walks);
            }
$objTable->add_item(number_format($row->joint_walks));
            $objTable->add_item($row->guest_walks);
            $objTable->add_item($row->num_leaders);
            $objTable->add_item($row->total_miles);
            $objTable->add_item($row->min_miles);
            $objTable->add_item($row->max_miles);
            $objTable->add_item($row->avg_miles);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo 'Total walks: ' . $total_walks . ', Total miles: ' . $total_miles . '<br>';

        $back = "administrator/index.php?option=com_ra_tools&view=reports_group&group_code=" . $group_code . '&scope=' . $scope;
        echo $this->toolsHelper->backButton($back);
        if (!$csv == 'Y') {
            $target = "administrator/index.php?option=com_ra_tools&task=reports.showSummary&csv=Y&group_code=" . $group_code;
            echo $this->toolsHelper->buildLink($target, "Extract as CSV", False, "btn btn-small button-new");
        }
    }

}

