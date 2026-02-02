<?php
/**
 * @version    1.0.2
 * @package    Com_Ra_events
 * @author     Martin King <martinkingesra@gmail.com>
 * @copyright  2025 Martin King
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * 08/06/25 MK Add shared events API
 */

namespace Ramblers\Plugin\WebServices\Ra_events\Extension;


defined('_JEXEC') or die;

use Joomla\CMS\Event\Application\BeforeApiRouteEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;
/**
 * Web Services adapter for ra_events.
 *
 * @since  2.0.1
 */
class Ra_events extends CMSPlugin implements SubscriberInterface
{
   /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   5.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeApiRoute' => 'onBeforeApiRoute',
        ];
    }	

    /**
     * Registers com_content's API's routes in the application
     *
     * @param   BeforeApiRouteEvent  $event  The event object
     *
     * @return  void
     *
     * @since   4.0.0
     */	
	
	
	public function onBeforeApiRoute(BeforeApiRouteEvent $event): void
	{
		
		$router = $event->getRouter();
		
// Normal apis		
	
		$router->createCRUDRoutes('v1/ra_events/events', 'events', ['component' => 'com_ra_events']);
		
		$router->createCRUDRoutes('v1/ra_events/sharedevents', 'sharedevents', ['component' => 'com_ra_events']);		
		
// Add special case apis (eg public access)		

		$router->addRoutes ( [
			new Route
				(
				  ['GET'], 'v1/ra_events/eventspub/:id', 'events.displayitem',
				  ['id' => '\d+' ],  
				  [
					 'component' => 'com_ra_events',
					 'public'    => true
				  ]
				) ,
						
			]);	

		$router->addRoutes ( [
			new Route
				(
				  ['GET'], 'v1/ra_events/eventspub/', 'events.displaylist',
				  [ ],  
				  [
					 'component' => 'com_ra_events',
					 'public'    => true
				  ]
				) ,
						
			]);				

		$router->addRoutes ( [
			new Route
				(
				  ['GET'], 'v1/ra_events/sharedevents2/', 'sharedevents.displaylist',
				  [ ],  
				  [
					 'component' => 'com_ra_events',
					 'public'    => false
				  ]
				) ,
						
			]);		
	
		
	}
}
