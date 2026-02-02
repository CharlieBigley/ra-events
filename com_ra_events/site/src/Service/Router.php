<?php

/**
 * @version    1.0.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_events\Site\Service;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Menu\AbstractMenu;

/**
 * Class Ra_eventsRouter
 *
 */
class Router extends RouterView {

    private $noIDs;

    /**
     * The category factory
     *
     * @var    CategoryFactoryInterface
     *
     * @since  1.0.1
     */
    private $categoryFactory;

    /**
     * The category cache
     *
     * @var    array
     *
     * @since  1.0.1
     */
    private $categoryCache = [];

    public function __construct(SiteApplication $app, AbstractMenu $menu, CategoryFactoryInterface $categoryFactory, DatabaseInterface $db) {
        $params = Factory::getApplication()->getParams('com_ra_events');
        $this->noIDs = (bool) $params->get('sef_ids');
        $this->categoryFactory = $categoryFactory;

        $events = new RouterViewConfiguration('events');
        $this->registerView($events);

        $ccEvent = new RouterViewConfiguration('event');
        $ccEvent->setKey('id')->setParent($events);
        $this->registerView($ccEvent);

        $eventform = new RouterViewConfiguration('eventform');
        $eventform->setKey('id');
        $this->registerView($eventform);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Method to get the segment(s) for an event
     *
     * @param   string  $id     ID of the event to retrieve the segments for
     * @param   array   $query  The request that is built right now
     *
     * @return  array|string  The segments of this item
     */
    public function getEventSegment($id, $query) {
        return array((int) $id => $id);
    }

    /**
     * Method to get the segment(s) for an eventform
     *
     * @param   string  $id     ID of the eventform to retrieve the segments for
     * @param   array   $query  The request that is built right now
     *
     * @return  array|string  The segments of this item
     */
    public function getEventformSegment($id, $query) {
        return $this->getEventSegment($id, $query);
    }

    /**
     * Method to get the segment(s) for an event
     *
     * @param   string  $segment  Segment of the event to retrieve the ID for
     * @param   array   $query    The request that is parsed right now
     *
     * @return  mixed   The id of this item or false
     */
    public function getEventId($segment, $query) {
        return (int) $segment;
    }

    /**
     * Method to get the segment(s) for an eventform
     *
     * @param   string  $segment  Segment of the eventform to retrieve the ID for
     * @param   array   $query    The request that is parsed right now
     *
     * @return  mixed   The id of this item or false
     */
    public function getEventformId($segment, $query) {
        return $this->getEventId($segment, $query);
    }

    /**
     * Method to get categories from cache
     *
     * @param   array  $options   The options for retrieving categories
     *
     * @return  CategoryInterface  The object containing categories
     *
     * @since   1.0.1
     */
    private function getCategories(array $options = []): CategoryInterface {
        $key = serialize($options);

        if (!isset($this->categoryCache[$key])) {
            $this->categoryCache[$key] = $this->categoryFactory->createCategory($options);
        }

        return $this->categoryCache[$key];
    }

}
