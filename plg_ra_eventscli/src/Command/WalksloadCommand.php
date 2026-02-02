
<?php

/**
 * @package     Ra_walks.Console
 * @subpackage  Walksload
 *
 * @copyright   Copyright (C) 2005 - 2021 Clifford E Ford. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 11/12/24 CB created from OnoffbydateCommand
 * 17/12/24 CB return 1 from doExecute
 * 05/01/25 CB incorporate code from LoadHelper
 * 07/01/25 CB allow to run in two parts
 * 09/01/25 CB three parts
 * 13/01/25 CB change logging
 */

namespace Ramblers\Plugin\System\Ramblerswalks\Command;

\defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Console\Command\AbstractCommand;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class WalksloadCommand extends AbstractCommand {

    /**
     * The default command name
     *
     * @var    string
     *
     * @since  4.0.0
     */
    protected static $defaultName = 'ramblerswalks:walksload';

    /**
     * @var InputInterface
     * @since version
     */
    private $cliInput;

    /**
     * SymfonyStyle Object
     * @var SymfonyStyle
     * @since 4.0.0
     */
    private $ioStyle;
    protected $db;
    protected $app;
    protected $objHelper;
    private $part;
    private $walksfound = 0;
    private $walksupdated = 0;
    private $walkscreated = 0;
    private $counter = 0;

    /**
     * Instantiate the command.
     *
     * @since   4.0.0
     */
    public function __construct() {
        parent::__construct();
        $this->db = Factory::getDbo();
        $this->objHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
    }

    /**
     * Configures the IO
     *
     * @param   InputInterface   $input   Console Input
     * @param   OutputInterface  $output  Console Output
     *
     * @return void
     *
     * @since 4.0.0
     *
     */
    private function configureIO(InputInterface $input, OutputInterface $output) {
        $this->cliInput = $input;
        $this->ioStyle = new SymfonyStyle($input, $output);
    }

    /**
     * Initialise the command.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    protected function configure(): void {
        $this->addArgument('part', InputArgument::REQUIRED, 'part (1 or 2)');
        $help = "<info>%command.name%</info> Loads walk data from WalksManager feed
            \nUsage: <info>php %command.full_name%";

        $this->setDescription('Called by cron to load walks from the WalksManager feed.');
        $this->setHelp($help);
    }

    /**
     * Internal function to execute the command.
     *
     * @param   InputInterface   $input   The input to inject into the command.
     * @param   OutputInterface  $output  The output to inject into the command.
     *
     * @return  integer  The command exit code
     *
     * @since   4.0.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int {
        $this->configureIO($input, $output);
        $this->part = $this->cliInput->getArgument('part');
        /*

          // validate input
          if (!strtoupper($areacode) == 'ALL') {

          } else {
          if (!strlen($areacode) == 2) {
          $this->ioStyle->error("Invalid area code {$areacode}");
          return 0;
          }
          }
         *
         */
        $message = 'WalksRefresh started in batch mode';

        $params = ComponentHelper::getParams('com_ra_walks');
        $option = $params->get('option');
        $sql .= 'SELECT code from #__ra_areas ';
        /*
          if ($option == '1') {
          $code = $params->get('area');
          $message .= ' for Area ' . $code;
          $sql .= 'WHERE code="' . $code . '"';
          } else {
          $message .= ' for all Areas';
          }
         */

        if ($this->part == '1') {
            $sql .= 'WHERE code<"I"';
            $message .= ' for Areas < I';
        } elseif ($this->part == '2') {
            $sql .= 'WHERE code>"I" AND code<"R"';
            $message .= ' for Areas > I AND < R ';
        } else {
            $sql .= 'WHERE code>"R"';
            $message .= ' for Areas > R';
        }
        $sql .= ' ORDER BY code ';

        $this->ioStyle->comment($message);
        $this->logit($message, '1');
//        $this->ioStyle->comment($sql);
        $rows = $this->objHelper->getRows($sql);
        foreach ($rows as $row) {
            $message = 'Processing ' . $row->code . ' ';
            $walkslist = $this->getWalksData($row->code);
            if (is_null($walkslist)) {
                $message .= ': Failed to get data';
                $this->logit($message, '1');
                $this->ioStyle->error($message, '1');
            } else {
                $this->walksfound = count($walkslist);
                $message = $this->walksfound . ' records found for ' . $row->code;
                $message .= ' (running total ' . $this->counter + $this->walksfound . ')';
                $this->ioStyle->comment($message);
                //               $this->logit($message);
                $this->processwalks($walkslist);
            }
        }
        $message = "Walks in feed = $this->counter , Walks created = $this->walkscreated , Walks updated = $this->walksupdated";
        $this->ioStyle->comment($message);
        $this->logit($message, '9');
        return 1;
    }

    private function doesWalkExist($walkid) {

        $query = $this->db->getQuery(true);

// Select everything from the table that matches the walk id.
        $query->select(' a.*')
                ->from('`#__ra_walks` AS a')
                ->where('a.walk_id = ' . (int) $walkid)
        ;

        $this->db->setQuery((string) $query);

        $results = $this->db->loadObjectList();

// See if we got anything back - ie does the walk exist
        if (count($results) == 0) {
            return false;
        } else {
            return true;
        }
    }

// doesWalkExist ($walkid)

    /**
     *   Get the walks data from the feed (either a test file or the ramblers JSON feed);
     */
    private function getWalksData($code) {
//        if ($code == 'ER') {
//            return;
//        }
        $url = 'https://walks-manager.ramblers.org.uk/api/volunteers/walksevents?types=group-walk';
        $url .= '&api-key=742d93e8f409bf2b5aec6f64cf6f405e';
        $url .= '&groups=' . $code;
//        $url .= '&limit=3';
//        $url .= '&dow=7';
//        $url = 'https://www.ramblers.org.uk/api/lbs/walks?groups=NS01&dow=1&limit=3';
//      set up maximum time of 10 minutes
        $max = 10 * 60;
        set_time_limit($max);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false); // do not include header in output
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // do not follow redirects
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // do not output result
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);  // allow xx seconds for timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);  // allow xx seconds for timeout
//            curl_setopt($ch, CURLOPT_REFERER, JURI::base()); // say who wants the feed

        curl_setopt($ch, CURLOPT_REFERER, "com_ra_wf"); // say who wants the feed

        $data = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            print('Error code: ' . $error . "\n");
            print('Http return: ' . $httpCode . "\n");
            $this->showMessage('Access failed', '3');

            $this->logit("Feed access failed for feed : " . $url, 'E' . $httpCode);
            return;
        }

        $temp = json_decode($data);
        $summary = $temp->summary;
//        print('Limit=', $summary->limit . ', count=' . $summary->count. "\n");
        return $temp->data;
    }

// getWalksData()

    /**
     *   Store a log entry
     */
    public function logit($text, $record_type = '3') {

        $query = $this->db->getQuery(true);

        $query->insert('#__ra_logfile')
                ->set("record_type = " . $this->db->quote($record_type))
                ->set("sub_system = " . $this->db->quote('WH'))
                ->set("message = " . $this->db->quote($text))
                ->set("ref = " . $this->db->quote('LoadWalks'))
        ;

        $result = $this->db->setQuery($query)->execute();
    }

// logit ($text , $code = 0)

    /**
     *   Process the walks data
     */
    private function processwalks($walkslist) {
        foreach ($walkslist as $walk) {
            $this->counter++;
            $this->writeWalk($walk);
//            if ($this->counter == 100) {
//                return;
//            }
        }
    }

    private function showMessage($message, $type = '3') {
        $this->logit($message, $type);
        if ($type == '1') {
            $this->ioStyle->error($message);
        } elseif ($type == '2') {
            $this->ioStyle->warning($message);
        } else {
            $this->ioStyle->comment($message);
        }
    }

    /**
     *   Write a walk to the database
     */
    private function writeWalk($walk) {
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $walk_date = substr($walk->start_date_time, 0, 10);

//        if ($walk->id == 100295864) {
//            var_dump($walk);
//            echo '<br>';
//            return;
//        }
        if (($walk->distance_miles > 99.9) OR ($walk->distance_km > 99.9)) {
            $error = 'Group=' . $walk->group_code;
            $error .= ': Out of range: id ' . $walk->id;
            $error .= ', date ' . $walk_date;
            $error .= ', km ' . $walk->distance_km;
            $error .= ', mi ' . $walk->distance_miles;
            $this->showMessage($error, '2');
            $distance_km = 99.9;
            $distance_miles = 99.9;
        } elseif (($walk->distance_miles == '') OR ($walk->distance_km == '')) {
            $error = 'Group=' . $walk->group_code;
            $error .= ': Blank distance: id ' . $walk->id;
            $error .= ', date ' . $walk_date;
//            $error .= ', km ' . $walk->distance_km;
//            $error .= ', mi ' . $walk->distance_miles;
//            $this->showMessage($error, '2);
            $distance_km = 0;
            $distance_miles = 0;
            return;
        } else {
            $distance_km = $walk->distance_km;
            $distance_miles = $walk->distance_miles;
        }

        if ((int) $walk->id == 0) {
            $error = "Walk $this->counter has blank id field";
            $this->showMessage($error, '2');
        } else {
            $query = $this->db->getQuery(true);

            $walk_date = substr($walk->start_date_time, 0, 10);
            $start_time = substr($walk->start_date_time, 11, 5);
            $end_time = substr($walk->end_date_time, 11, 5);
            if (is_null($walk->description)) {
                $description = '(blank)';
            } else {
                $description = $walk->description;
            }
            $difficulty = substr($walk->difficulty->description, 0, 10);
            if ($walk->shape == 'linear') {
                $shape = 'L';
            } else {
                $shape = 'C';
            }

            if (is_null($walk->start_location)) {
                $start_details = '';
                $start_grid_ref = '';
                $start_latitude = 0;
                $start_longitude = 0;
                $start_postcode = '';
            } else {
                $start_details = $walk->start_location->description;
                $start_grid_ref = $walk->start_location->grid_reference_10;
                $start_latitude = (float) $walk->start_location->latitude;
                $start_longitude = (float) $walk->start_location->longitude;
                $start_postcode = $walk->start_location->postcode;
            }

//            $title = substr($walk->title, 0, 120);
//            $title = substr(iconv(($walk->title, mb_detect_order(), true), "UTF-8", $walk->title), 0, 120);

            $title = substr($walk->title, 0, 120);

            if ((is_null($walk->walk_leader)) or (is_array($walk->walk_leader))) {
                $phone = '';
                $leader_name = '';
            } else {
                $phone = substr(preg_replace('/\D/', '', $walk->walk_leader->telephone), 0, 15);
                $leader_name = $walk->walk_leader->name;
            }

            $query->set("walk_id = " . $this->db->quote($walk->id))
                    ->set("walk_date = " . $this->db->quote($walk_date))
                    ->set("group_code = " . $this->db->quote($walk->group_code))
                    ->set("contact_display_name = " . $this->db->quote($leader_name))
// must increase size of database field
//                ->set('contact_email = ' . $this->db->quote($walk->walk_leader->email_form))
                    ->set('contact_tel1 = ' . $this->db->quote($phone))
                    ->set("title = " . $this->db->quote(substr($title, 0, 120)))
                    ->set("description = " . $this->db->quote($description))
                    ->set("start_time = " . $this->db->quote($start_time))
                    ->set("start_gridref = " . $this->db->quote($start_grid_ref))
                    ->set("start_latitude = " . $this->db->quote($start_latitude))
                    ->set("start_longitude = " . $this->db->quote($start_longitude))
                    ->set("start_postcode = " . $this->db->quote($start_postcode))
                    ->set("start_details = " . $this->db->quote($start_details))
                    ->set("distance_km = " . $this->db->quote($distance_km))
                    ->set("distance_miles = " . $this->db->quote($distance_miles))
                    ->set("difficulty = " . $this->db->quote($difficulty))
//                ->set("pace = " . $this->db->quote($walk->pace))
                    ->set("ascent_feet = " . $this->db->quote($walk->ascent_feet))
                    ->set("ascent_metres = " . $this->db->quote($walk->ascent_metres))
                    ->set("circular_or_linear = " . $this->db->quote($shape))
                    ->set("finish_time = " . $this->db->quote($end_time))
                    ->set("state=1")
            ;

            if ($this->doesWalkExist($walk->id)) {
                $query->update('#__ra_walks')
                        ->set("modified = " . $this->db->quote($date))
                        ->where('walk_id=' . $walk->id);

                $result = $this->db->setQuery($query)->execute();
                $this->walksupdated++;
            } else {
                $query->insert('#__ra_walks')  // utf8mb4_unicode_ci
                        ->set("created = " . $this->db->quote($date));
                $result = $this->db->setQuery($query)->execute();
                $this->walkscreated++;
            }
        }
    }

// writeWalk
}
