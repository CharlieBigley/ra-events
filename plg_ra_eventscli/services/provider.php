<?php

/**
 * @version     1.0.5    
 * @package     Ramblers.Walks
 * @subpackage  System.ramblerswalks
 *
 * @copyright   (C) 2024 Charlie Bigley
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Ramblers\Plugin\Console\Ra_eventscli\Extension\Ra_eventscli;

return new class implements ServiceProviderInterface {

    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function register(Container $container) {
        $container->set(
                PluginInterface::class,
                function (Container $container) {
                    $subject = $container->get(DispatcherInterface::class);
                    $config = (array) PluginHelper::getPlugin('console', 'ra_eventscli');
                    return new Ra_eventscli($subject, $config);
                }
        );
    }
};

