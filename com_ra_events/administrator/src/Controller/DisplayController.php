<?php

/**
 * @version    1.0.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_events\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Ra_events master display controller.
 *
 * @since  1.0.1
 */
class DisplayController extends BaseController {

    /**
     * The default view.
     *
     * @var    string
     * @since  1.0.1
     */
    protected $default_view = 'events';

    /**
     * Method to display a view.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types, for valid values see {@link InputFilter::clean()}.
     *
     * @return  BaseController|boolean  This object to support chaining.
     *
     * @since   1.0.1
     */
    public function display($cachable = false, $urlparams = array()) {
        return parent::display();
    }

}
