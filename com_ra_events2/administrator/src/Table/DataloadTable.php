<?php

/**
 * @version    4.1.10
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Processing to create the User records is done in the save function of the model
 *
 * 25/03/25 CB created from MailMan
 */

namespace Ramblers\Component\Ra_events\Administrator\Table;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Access\Access;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Filesystem\File;
// use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table as Table;
use \Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use \Joomla\Database\DatabaseDriver;
use \Joomla\Registry\Registry;
use \Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_events\Site\Helpers\UserHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Upload table
 *
 * @since 1.0.4
 */
class DataloadTable extends Table implements VersionableTableInterface, TaggableTableInterface {

    use TaggableTableTrait;

    /**
     * Constructor
     *
     * @param   JDatabase  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db) {
        echo 1 / 0;
        $this->typeAlias = 'com_ra_events.upload';
        // Don't access the database, but must give a valid database table
        parent::__construct('#__ra_areas', 'id', $db);
    }

    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   1.0.4
     */
    public function getTypeAlias() {
        return $this->typeAlias;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array  $array   Named array
     * @param   mixed  $ignore  Optional array or list of parameters to ignore
     *
     * @return  boolean  True on success.
     *
     * @see     Table:bind
     * @since   1.0.4
     * @throws  \InvalidArgumentException
     */
    public function bind($array, $ignore = '') {
        $input = Factory::getApplication()->input;

        // Support for multi file field: file_name
        if (!empty($array['file_name'])) {
            if (is_array($array['file_name'])) {
                $array['file_name'] = implode(',', $array['file_name']);
            } elseif (strpos($array['file_name'], ',') != false) {
                $array['file_name'] = explode(',', $array['file_name']);
            }
        } else {
            $array['file_name'] = '';
        }

        return parent::bind($array, $ignore);
    }

    /**
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   1.0.4
     */
    public function store($updateNulls = true) {
        echo 'Table store<br>';

        $app = Factory::getApplication();
        $files = $app->input->files->get('jform', array(), 'raw');
        $array = $app->input->get('jform', array(), 'ARRAY');
        $singleFile = $files['csv_file']['name'];

        // Replace any special characters in the filename
        jimport('joomla.filesystem.file');
        $filename = File::stripExt($files['csv_file']['name']);
        $extension = File::getExt($files['csv_file']['name']);
        $filename = preg_replace("/[^A-Za-z0-9]/i", "-", $filename);
        $filename = $filename . '.' . $extension;
        $uploadPath = JPATH_ROOT . '/images/com_ra_events/';
        $working_file = $uploadPath . $filename;
        $fileTemp = $files['csv_file']['tmp_name'];

        if (!File::exists($uploadPath)) {
            if (!File::upload($fileTemp, $working_file)) {
                $app->enqueueMessage('Error moving file ' . $filename, 'warning');
                return false;
            }
        }


//        $working_file = 'images/com_ra_events/' . $filename;
//        var_dump($array);
//        echo '<br>files<br> ';
//        var_dump($files);
//        echo '<br>';
        $method_id = $array['data_type'];
        $processing = $array['processing'];
        $event_id = $array['event'];
        if (JDEBUG) {
            echo 'file ' . $working_file . '<br>';
            echo 'event ' . $event_id . '<br>';
            echo 'method ' . $method_id . '<br>';
            echo 'processing ' . $processing . '<br>';
            echo '<br>';
        }

        $objUserHelper = new UserHelper;
        $objUserHelper->method_id = $method_id;
        $objUserHelper->list_id = $event_id;
        $objUserHelper->processing = $processing;
        $objUserHelper->filename = $working_file;

//        $objUserHelper->purgeTestData();   // <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<



        $response = $objUserHelper->processFile();
        return true;
    }

    /**
     * Overloaded check function
     *
     * @return bool
     */
    public function check() {
        return true;
    }

}
