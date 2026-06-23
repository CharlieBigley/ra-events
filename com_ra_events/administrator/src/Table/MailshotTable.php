<?php

/**
 * @version    2.5.0
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2024 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt

 */

namespace Ramblers\Component\Ra_events\Administrator\Table;

// No direct access
defined('_JEXEC') or die;
use \Joomla\CMS\Access\Access;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table as Table;
use \Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use \Joomla\Database\DatabaseDriver;
use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Filesystem\File;
use \Joomla\Registry\Registry;
use \Joomla\Utilities\ArrayHelper;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Mailshot table
 *
 * @since 1.0.0
 */
class MailshotTable extends Table implements VersionableTableInterface, TaggableTableInterface {

    use TaggableTableTrait;

    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    protected $_supportNullValue = true;
    protected $image_path;
    protected $okMIMETypes;

    /**
     * Constructor
     *
     * @param   JDatabase  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db) {
        $this->typeAlias = 'com_ra_events.mailshot';
        $this->image_path = JPATH_ROOT . '/images/com_ra_events/';
        $this->okMIMETypes = 'image/jpeg,image/bmp,image/png,application/pdf,text/plain,text/csv,text/comma-separated-values';

        parent::__construct('#__ra_mail_shots', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   1.0.0
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
     * @since   1.0.0
     * @throws  \InvalidArgumentException
     */
    public function bind($array, $ignore = '') {
        $date = Factory::getDate();
//        $task = Factory::getApplication()->input->get('task');
        $app = Factory::getApplication();
        $user = Factory::getApplication()->getSession()->get('user');

        $input = $app->input;
        $task = $input->getString('task', '');

        if ($array['id'] == 0) {
            $array['created'] = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
            $array['created_by'] = $user->id;
        }
        // Support for multi file field: attachment
        if (!empty($array['attachment'])) {
            if (is_array($array['attachment'])) {
                $array['attachment'] = implode(',', $array['attachment']);
            } elseif (strpos($array['attachment'], ',') != false) {
                $array['attachment'] = explode(',', $array['attachment']);
            }
        } else {
            $array['attachment'] = '';
        }

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

        if (!$user->authorise('core.admin', 'com_ra_events.mailshot.' . $array['id'])) {
            $actions = Access::getActionsFromFile(
                            JPATH_ADMINISTRATOR . '/components/com_ra_events/access.xml',
                            "/access/section[@name='mailshot']/"
            );
            $default_actions = Access::getAssetRules('com_ra_events.mailshot.' . $array['id'])->getData();
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
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   1.0.0
     */
    public function store($updateNulls = true) {
        if ($this->id > 0) {
            $this->modified_by = Factory::getApplication()->getSession()->get('user')->id;
            $this->modified = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        }
        return parent::store($updateNulls);
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
     * Overloaded check function
     *
     * @return bool
     */
    public function check() {

// Support multi file field: attachment
        $app = Factory::getApplication();
        $files = $app->input->files->get('jform', array(), 'raw');
        $array = $app->input->get('jform', array(), 'ARRAY');
        if (empty($files['attachment'][0])) {
            $temp = $files;
            $files = array();
            $files['attachment'][] = $temp['attachment'];
        }

        if ($files['attachment'][0]['size'] > 0) {
// Deleting existing files
            $oldFiles = ToolsHelper::getFiles($this->id, $this->_tbl, 'attachment');

            foreach ($oldFiles as $f) {
                $oldFile = $this->image_path . $f;

                if (file_exists($oldFile) && !is_dir($oldFile)) {
                    unlink($oldFile);
                }
            }

            $this->attachment = "";

            foreach ($files['attachment'] as $singleFile) {
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
                    if (isset($array['attachment'])) {
                        $this->attachment = $array['attachment'];
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
                        $app->enqueueMessage('File ' . $filename . ' uploaded', 'info');
                    } else {
                        if (!File::upload($fileTemp, $uploadPath)) {
                            $app->enqueueMessage('Error moving file ' . $uploadPath, 'warning');
                            return false;
                        }
                    }

                    $this->attachment .= (!empty($this->attachment)) ? "," : "";
                    $this->attachment .= $filename;
                }
            }
        } else {
            $this->attachment .= $array['attachment_hidden'];
        }

        return parent::check();
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
        $assetParent->loadByName('com_ra_mailman');

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
        /*
         * No, not safe to delete any of the files that have been uploaded, since they
         * may be required for other mailshots
          if ($result) {
          jimport('joomla.filesystem.file');

          $checkImageVariableType = gettype($this->attachment);

          switch ($checkImageVariableType) {
          case 'string':
          File::delete($this->image_path . $this->attachment);
          break;
          default:
          foreach ($this->attachment as $attachmentFile) {
          File::delete($this->image_path . $attachmentFile);
          }
          }
          }
         */

        return $result;
    }

}
