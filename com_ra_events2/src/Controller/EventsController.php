<?php
/**
 * @version    1.0.0
 * @package    com_ra_events
 * @author     Martin King <martinkingesra@gmail.com>
 * @copyright  2025 Martin King
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Ramblers\Component\Ra_events\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;

/**
 * The Events controller
 *
 * @since  1.0.0
 */
class EventsController extends ApiController 
{
	/**
	 * The content type of the item.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $contentType = 'events';

	/**
	 * The default view for the display method.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $default_view = 'events';
	
}