<?php
/**
 * @version    2.4.12
 * @package    com_ra_events
 * @author     Martin King <martinkingesra@gmail.com>
 * @copyright  2025 Martin King
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 27/02/26 GPT Changed version to 2.4.12
 * 27/02/26 GPT moved to folder src/Controller, changed namespace, added getModel proxy method
 */
namespace Ramblers\Component\Ra_events\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Ramblers\Component\Ra_events\Api\Model\EventsModel as ApiEventsModel;

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

	/**
	 * Proxy for getModel to ensure Api models are used.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  object  The model
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Events', $prefix = 'Api', $config = array())
	{
		$model = parent::getModel($name, $prefix, array('ignore_request' => true));

		if (!$model instanceof ApiEventsModel) {
			$model = new ApiEventsModel(array('ignore_request' => true));
		}

		return $model;
	}
	
}
