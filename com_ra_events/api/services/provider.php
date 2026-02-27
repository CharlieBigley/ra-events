<?php
/**
 * @version    2.4.12
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 27/02/26 CB created by GPT-5.1-Codex
 * 27/02/26 GPT Changed version to 2.4.12
 */

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Ramblers\Component\Ra_events\Api\Extension\Ra_eventsComponent;

/**
 * The Ra_events API service provider.
 *
 * @since  1.0.1
 */
return new class implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   1.0.1
	 */
	public function register(Container $container)
	{
		$container->registerServiceProvider(new MVCFactory('\\Ramblers\\Component\\Ra_events\\Api'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\\Ramblers\\Component\\Ra_events\\Api'));

		$container->set(
			ComponentInterface::class,
			function (Container $container)
			{
				$component = new Ra_eventsComponent($container->get(ComponentDispatcherFactoryInterface::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));

				return $component;
			}
		);
	}
};
