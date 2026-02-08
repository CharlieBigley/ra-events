<?php

/**
 * @version     1.1.2
 * @package     Ra_events.Console
 * @subpackage  plg_raeventscli
 *
 * @copyright   Copyright (C) 2005 - 2021 Clifford E Ford. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 21/08/23 CB created from Walksload
 * 15/09/25 CB use Eventshelper
 * 18/09/25 CB check for NULL events
 * 05/10/25 CB review log messages
 * 11/10/25 CB change creation of log messages
 */

namespace Ramblers\Plugin\Console\Ra_eventscli\Command;

\defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Console\Command\AbstractCommand;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class EventscopyCommand extends AbstractCommand {

    /**
     * The default command name
     *
     * @var    string
     *
     * @since  4.0.0
     */
    protected static $defaultName = 'ra_events:eventscopy';

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
    protected $eventsHelper;
    protected $site_id;
    protected $toolsHelper;

    /**
     * Instantiate the command.
     *
     * @since   4.0.0
     */
    public function __construct() {
        parent::__construct();
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->eventsHelper = new EventsHelper;
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
        $help = "<info>%command.name%</info> Copies data from all configured remote sites
            \nProcesses all sites in #__ra_apisites where sub_system = 'RA Events'
            \nUsage: <info>php %command.full_name%</info>";

        $this->setDescription('Called by cron to copy Events from configured remote sites.');
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
        $this->ioStyle->info('Processing start at ' . date('Y-m-d H:i:s'));
        // Get all sites where sub_system = 'RA Events'
        $sql = 'SELECT id, url, token from #__ra_api_sites ';
        $sql .= 'WHERE sub_system = ' . $this->db->quote('RA Events');
        
        $sites = $this->toolsHelper->getRows($sql);
        
        if (empty($sites)) {
            $message = 'No API sites configured for RA Events';
            $this->ioStyle->warning($message);
            $this->logit($message, '2');
            return 1;
        }
        
        $siteCount = count($sites);
        $message = "Processing $siteCount configured site(s)";
        $this->ioStyle->comment($message);
        $this->logit($message);
        
        foreach ($sites as $site) {
            $this->site_id = $site->id;

            $message = 'Copying Events from site ' . $this->site_id;
            $message .= ', url is ' . $site->url;
            $this->ioStyle->comment($message);
            $this->logit($message);

            $details = $this->eventsHelper->getSharedEvents($this->site_id);
            
            // Check if getSharedEvents failed
            if (is_null($details) || !is_array($details)) {
                $message = 'Failed to retrieve events from ' . $site->url;
                $this->ioStyle->error($message);
                $this->logit($message, '1');
                foreach ($this->eventsHelper->messages as $msg) {
                    $this->logit($msg, '5');
                }
                continue;
            }

            $events = $details["data"] ?? null;
            if (is_null($events)) {
                $message = 'No Events in feed for ' . $site->url;
                $this->ioStyle->comment($message);
                foreach ($this->eventsHelper->messages as $message) {
                    $this->logit($message, '5');
                }
                continue;
            }
            $count = count($events);

            $message = "Events in feed = $count";
            $this->ioStyle->comment($message);
            $this->eventsHelper->storeShared($this->site_id, $events);
            foreach ($this->eventsHelper->messages as $message) {
                $this->logit($message, '5');
            }
        }
        $message = "Processing completed";
        $this->logit($message, '9');
        $this->ioStyle->info('Processing completed at ' . date('Y-m-d H:i:s'));
        return 0;
    }

    /**
     *   Store a log entry
     */
    public function logit($text, $record_type = '3') {

        $query = $this->db->getQuery(true);

        $query->insert('#__ra_logfile')
                ->set("sub_system = " . $this->db->quote('RA Events'))
                ->set("record_type = " . $this->db->quote($record_type))
                ->set("ref = " . $this->db->quote($this->site_id))
                ->set("message = " . $this->db->quote($text))
        ;
        $result = $this->db->setQuery($query)->execute();
    }

}
