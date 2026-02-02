<?php

/**
 * @version    1.0.0
 * @package    Com_Ra_events
 * @author     Martin King <martinkingesra@gmail.com>
 * @copyright  2025 Martin King
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

use Ramblers\Plugin\WebServices\Ra_events\Extension\Ra_events;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.4.0
     */
    public function register(Container $container): void
    {
        $container->set
			(
				PluginInterface::class,
				function (Container $container) 
				{
					$dispatcher = $container->get(DispatcherInterface::class);
					$plugin     = new Ra_events
						(
							$dispatcher,
							(array) PluginHelper::getPlugin('webservices', 'ra_events')
						);
					$plugin->setApplication(Factory::getApplication());

					return $plugin;
				}
			);
    }
};
