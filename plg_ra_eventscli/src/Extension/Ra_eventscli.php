<?php

/**
 * @version     1.0.7
 * @package     Ra_events.Console
 * @subpackage  plg_raeventscli
 *
 * @copyright   (C) 2024 Charlie Bigley
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Plugin\Console\Ra_eventscli\Extension;

\defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Ramblers\Plugin\Console\Ra_eventscli\Command\EventscopyCommand;

class Ra_eventscli extends CMSPlugin {

    protected $app;

    public function __construct(&$subject, $config = []) {
        parent::__construct($subject, $config);

        if (!$this->app->isClient('cli')) {
            return;
        }

        $this->registerCLICommands();
    }

    public static function getSubscribedEvents(): array {
        if ($this->app->isClient('cli')) {
            return [
                Joomla\Application\ApplicationEvents\ApplicationEvents::BEFORE_EXECUTE => 'registerCLICommands',
            ];
        }
    }

    public function registerCLICommands() {

        $commandObject = new EventscopyCommand;
        $this->app->addCommand($commandObject);
    }

}
