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

namespace Ramblers\Component\Ra_events\Api\Extension;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Psr\Container\ContainerInterface;

/**
 * API component class for Ra_events.
 *
 * @since  1.0.1
 */
class Ra_eventsComponent extends MVCComponent implements BootableExtensionInterface
{
	/** @inheritdoc */
	public function boot(ContainerInterface $container)
	{
		// No API-specific bootstrapping required.
	}
}
