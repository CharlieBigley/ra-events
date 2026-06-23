<?php

/**
 * @version    2.3.5
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 21/10/24 CB check for checked_out_time without value
 * 01/12/24 CB set empty fields to NULL
 * 02/12/24 CB attachments
 * 19/02/25 CB set up created/modified in store, not bind
 * 06/03/25 CB set publication_date in store
 * 16/06/25 CB check share date after publication date, check group_code
 * 26/06/25 CB support NULL original_id, remove diagnostic on save
 * 27/07/25 CB set up defaults from Template
 * 15/09/25 CB ensure api_site_id is NULL
 * 01/10/25 CB trim fields custom1 and custom2
 * 13/10/25 CB validate booking hints
 * 14/10/25 CB correction for validation
 * 18/10/25 CB allow text/comma-separated-values
 */

namespace Ramblers\Component\Ra_events\Administrator\Table;

// No direct access
defined('_JEXEC') or die;

use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Access\Access;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table as Table;
use \Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use \Joomla\Database\DatabaseDriver;
use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Filesystem\File;
use \Joomla\Registry\Registry;
use \Joomla\CMS\Helper\ContentHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Event table
 *
 * @since 1.0.1
 */
class EventTable extends Table implements VersionableTableInterface, TaggableTableInterface {

    use TaggableTableTrait;

    protected $image_path;
    protected $okMIMETypes;

    /**
     * Constructor
     *
     * @param   JDatabase  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db) {
        $this->typeAlias = 'com_ra_events.event';
        parent::__construct('#__ra_events', 'id', $db);
        $this->setColumnAlias('published', 'state');
        $this->image_path = JPATH_ROOT . '/images/com_ra_events/';
        $this->okMIMETypes = 'image/jpeg,image/bmp,image/png,application/pdf,text/plain,text/csv,text/comma-separated-values';
    }

    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   1.0.1
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
     * @since   1.0.1
     * @throws  \InvalidArgumentException
     */
    public function bind($array, $ignore = '') {

//        echo 'bind: event_type_id ' . $this->event_type_id . '/' . $array['event_type_id'] . '<br>';
//        echo 'bind: url ' . $this->url . '/' . $array['url'] . '<br>';
//        die('bind' . var_dump($array));
//        $date = Factory::getDate();
        $task = Factory::getApplication()->input->get('task');
        $user = Factory::getApplication()->getIdentity();

        $input = Factory::getApplication()->input;
        $task = $input->getString('task', '');

        // Support for empty field: api_site_id
        if ($array['api_site_id'] == '0' || empty($array['api_site_id'])) {
            $array['api_site_id'] = NULL;
            $this->api_site_id = NULL;
            if (($array['event_type_id'] == 1) and $array['details'] == '') {
                // If committee meeting, set up defaults
                $toolsHelper = new ToolsHelper;
                $sql = 'SELECT details, reports, minutes FROM #__ra_events ';
                $sql .= 'WHERE title = "template"';
                $event = $toolsHelper->getItem($sql);
                if ($array['details'] == '') {
                    $array['details'] = $event->details;
                }
                if ($array['reports'] == '') {
                    $array['reports'] = $event->reports;
                }
                if ($array['minutes'] == '') {
                    $array['minutes'] = $event->minutes;
                }
            }
        }

        $array['group_code'] = strtoupper($array['group_code']);
        if (isset($array['event_date'])) {
// Support for empty date field: event_date
            if ($array['event_date'] == '0000-00-00' || empty($array['event_date'])) {
                $array['event_date'] = NULL;
                $this->event_date = NULL;
            }
        }
// Support for multi file field: attachments
        if (!empty($array['attachments'])) {
            if (is_array($array['attachments'])) {
                $array['attachments'] = implode(',', $array['attachments']);
            } elseif (strpos($array['images'], ',') != false) {
                $array['attachments'] = explode(',', $array['attachments']);
            }
        } else {
            $array['attachments'] = '';
        }

// Support for fields that must be null
        if ($array['details'] == '') {
            $array['details'] = NULL;
            $this->details = NULL;
        }
        if ($array['reports'] == '') {
            $array['reports'] = NULL;
            $this->reports = NULL;
        }
        if ($array['minutes'] == '') {
            $array['minutes'] = NULL;
            $this->minutes = NULL;
        }
        if ($array['original_id'] == '') {
            $array['original_id'] = NULL;
            $this->original_id = NULL;
        }
        if ($array['api_site_id'] == '') {
            $array['api_site_id'] = NULL;
            $this->api_site_id = NULL;
        }
        if ($array['created'] == '') {
            $array['created'] = NULL;
            $this->created = NULL;
        }
// Support for empty date field: date_reported
        if ($array['checked_out_time'] == '') {
            $array['checked_out_time'] = NULL;
            $this->checked_out_time = NULL;
        }
// Ensure no spaces
        $array['booking1'] = trim($array['booking1']);
        $array['booking1_hint'] = trim($array['booking1_hint']);
        $array['booking2'] = trim($array['booking2']);
        $array['booking2_hint'] = trim($array['booking2_hint']);
        /*
          // Support for multiple or not foreign key field: event_type_id
          if (!empty($array['event_type_id'])) {
          if (is_array($array['event_type_id'])) {
          $array['event_type_id'] = implode(',', $array['event_type_id']);
          } else if (strrpos($array['event_type_id'], ',') != false) {
          $array['event_type_id'] = explode(',', $array['event_type_id']);
          }
          } else {
          $array['event_type_id'] = 0;
          }
         *
         */
        if (isset($array['params']) && is_array($array['params'])) {
            $registry = new Registry;
            $registry->loadArray($array['params']);
            $array['params'] = (string) $registry;
        }

        if (isset($array['metadata']) && is_array($array['metadata'])) {
            $registry = new Registry;
            $registry->loadArray($array['metadata']);
            $array['metadata'] = (string) $registry;
        }

        if (!$user->authorise('core.admin', 'com_ra_events.event.' . $array['id'])) {
            $actions = Access::getActionsFromFile(
                            JPATH_ADMINISTRATOR . '/components/com_ra_events/access.xml',
                            "/access/section[@name='event']/"
            );
            $default_actions = Access::getAssetRules('com_ra_events.event.' . $array['id'])->getData();
            $array_jaccess = array();

            foreach ($actions as $action) {
                if (key_exists($action->name, $default_actions)) {
                    $array_jaccess[$action->name] = $default_actions[$action->name];
                }
            }

            $array['rules'] = $this->JAccessRulestoArray($array_jaccess);
        }

// Bind the rules for ACL where supported.
        if (isset($array['rules']) && is_array($array['rules'])) {
            $this->setRules($array['rules']);
        }

        return parent::bind($array, $ignore);
    }

    /**
     * Overloaded check function
     *
     * @return bool
     */
    public function check() {
        $app = Factory::getApplication();

// Support multi file field: attachment

        $files = $app->input->files->get('jform', array(), 'raw');
        $array = $app->input->get('jform', array(), 'ARRAY');
        $temp = HTMLHelper::_('date', $array['event_date'], 'Y-m-d') . ' 00:00:00';
        $this->event_date = $temp;
//        $app->enqueueMessage('check: Event date is ' . $array['event_date'] . ', temp=' . $temp, 'info');
        $id = (int) $array['id'];
//        var_dump($array);
//        echo 'check: event_type_id ' . $this->event_type_id . '/' . $array['event_type_id'] . '<br>';
        if ($id > 0) {
            if ($array['event_type_id'] == 4) {
                if (empty($array['event_date_end'])) {
                    $app->enqueueMessage('Last date of the Holiday must be given', 'error');
                    return false;
                }
                $from = strtotime($array['event_date']);
                $to = strtotime($array['event_date_end']);
                if ($from > $to) {
                    $app->enqueueMessage('End date of the Holiday must be after ' . $array['event_date'], 'error');
                    return false;
                }
            }
            if (empty($array['publication_date'])) {
                $app->enqueueMessage('Publication date must be given', 'error');
                return false;
            }
            if ($array['shareable'] == 1) {
                if (empty($array['group_code'])) {
                    $app->enqueueMessage('Group code must be given if Shareable ', 'error');
                    return false;
                }
                if (empty($array['publication_date'])) {
                    $app->enqueueMessage('Publication date must be given', 'error');
                    return false;
                }
                if (empty($array['share_date'])) {
                    $app->enqueueMessage('Share date must be given', 'error');
                    return false;
                }
                $from = strtotime($array['publication_date']);
                $to = strtotime($array['share_date']);
                if ($from > $to) {
                    $app->enqueueMessage('Share date must be after the Publication date (' . $array['publication_date'] . ')', 'error');
                    return false;
                }
            }
        }
        if ($array['bookable'] == '1') {
            if ($array['booking1'] !== '') {
                if ($array['booking1_hint'] == '') {
                    $app->enqueueMessage('Hint for ' . $array['booking1'] . ' must be given', 'error');
                    return false;
                }
            }

            if ($array['booking2'] !== '') {
                if ($array['booking2_hint'] == '') {
                    $app->enqueueMessage('Hint for ' . $array['booking2'] . ' must be given', 'error');
                    return false;
                }
            }
        }
        if (empty($files['attachments'][0])) {
            $temp = $files;
            $files = array();
            $files['attachments'][] = $temp['attachments'];
        }

        if ($files['attachments'][0]['size'] > 0) {
// Deleting existing files
            $oldFiles = ToolsHelper::getFiles($this->id, $this->_tbl, 'attachments');

            foreach ($oldFiles as $f) {
                $oldFile = $this->image_path . $f;

                if (file_exists($oldFile) && !is_dir($oldFile)) {
                    unlink($oldFile);
                }
            }

            $this->attachments = "";

            foreach ($files['attachments'] as $singleFile) {
                jimport('joomla.filesystem.file');

// Check if the server found any error.
                $fileError = $singleFile['error'];
                $message = '';

                if ($fileError > 0 && $fileError != 4) {
                    switch ($fileError) {
                        case 1:
                            $message = Text::_('File size exceeds allowed by the server');
                            break;
                        case 2:
                            $message = Text::_('File size exceeds allowed by the html form');
                            break;
                        case 3:
                            $message = Text::_('Partial upload error');
                            break;
                    }

                    if ($message != '') {
                        $app->enqueueMessage($message, 'warning');

                        return false;
                    }
                } elseif ($fileError == 4) {
                    if (isset($array['attachments'])) {
                        $this->attachments = $array['attachments'];
                    }
                } else {

// Check for filetype

                    $validMIMEArray = explode(',', $this->okMIMETypes);
                    $fileMime = $singleFile['type'];

                    if (!in_array($fileMime, $validMIMEArray)) {
                        $app->enqueueMessage('Only allowed filetypes ' . $this->okMIMETypes . ' ( not ' . $fileMime . ')', 'error');
                        return false;
                    }

// Replace any special characters in the filename
                    jimport('joomla.filesystem.file');
                    $filename = File::stripExt($singleFile['name']);
                    $extension = File::getExt($singleFile['name']);
                    $filename = preg_replace("/[^A-Za-z0-9]/i", "-", $filename);
                    $filename = $filename . '.' . $extension;
                    $uploadPath = $this->image_path . $filename;
                    $fileTemp = $singleFile['tmp_name'];

                    if (File::exists($uploadPath)) {
                        $app->enqueueMessage('File ' . $filename . ' uploaded OK', 'info');
                    } else {
                        if (!File::upload($fileTemp, $uploadPath)) {
                            $app->enqueueMessage('Error moving file ' . $uploadPath, 'warning');
                            return false;
                        }
                    }

                    $this->attachments .= (!empty($this->attachments)) ? "," : "";
                    $this->attachments .= $filename;
                }
            }
        } else {
            $this->attachments .= $array['attachment_hidden'];
        }

        return parent::check();
    }

    protected function prepareTable($table) {

        $table->group_code = strtoupper($table->group_code);
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   1.0.1
     */
    public function store($updateNulls = true) {
        $user = Factory::getApplication()->getIdentity();
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        if ($this->id == 0) {
            $this->created = $date;
            $this->publication_date = $date;
            $this->share_date = $date;
            $this->created_by = $user->id;
        } else {
            $this->modified_by = $user->id;
            $this->modified = $date;
        }
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('Storing date ' . $this->event_date_end, 'info');
        }
        $response = parent::store($updateNulls);
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('After Store, date ' . $this->event_date_end, 'info');
        }
                return $response;
    }

    /**
     * This function convert an array of Access objects into an rules array.
     *
     * @param   array  $jaccessrules  An array of Access objects.
     *
     * @return  array
     */
    private function JAccessRulestoArray($jaccessrules) {
        $rules = array();

        foreach ($jaccessrules as $action => $jaccess) {
            $actions = array();

            if ($jaccess) {
                foreach ($jaccess->getData() as $group => $allow) {
                    $actions[$group] = ((bool) $allow);
                }
            }

            $rules[$action] = $actions;
        }

        return $rules;
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see Table::_getAssetName
     */
    protected function _getAssetName() {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Returns the parent asset's id. If you have a tree structure, retrieve the parent's id using the external key field
     *
     * @param   Table   $table  Table name
     * @param   integer  $id     Id
     *
     * @see Table::_getAssetParentId
     *
     * @return mixed The id on success, false on failure.
     */
    protected function _getAssetParentId($table = null, $id = null) {
// We will retrieve the parent-asset from the Asset-table
        $assetParent = Table::getInstance('Asset');

// Default: if no asset-parent can be found we take the global asset
        $assetParentId = $assetParent->getRootId();

// The item has the component as asset-parent
        $assetParent->loadByName('com_ra_events');

// Return the found asset-parent-id
        if ($assetParent->id) {
            $assetParentId = $assetParent->id;
        }

        return $assetParentId;
    }

//XXX_CUSTOM_TABLE_FUNCTION

    /**
     * Delete a record by id
     *
     * @param   mixed  $pk  Primary key value to delete. Optional
     *
     * @return bool
     */
    public function delete($pk = null) {
        $this->load($pk);
        $result = parent::delete($pk);

        return $result;
    }

}
