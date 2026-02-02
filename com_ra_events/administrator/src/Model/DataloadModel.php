<?php

/**
 * @version    2.3.5
 * @package    com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * This model can be invoked more than once for the same file.
 * The first time, the file details are taken from the input->files array and
 * stored in the form->data. If processing is aborted, for example because the
 * wrong input parameters were given, the file details are taken from the form data.
 *
 * 18/10/23 CB take files from images/com_ra_events
 * 25/03/25 CB created from MailMan
 * 18/10/25 CB allow text/comma-separated-values
 */

namespace Ramblers\Component\Ra_events\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Table\Table;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Filesystem\File;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\Utilities\ArrayHelper;

//use Ramblers\Component\Ra_events\Site\Helpers\UserHelper;
//use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Mail_lst model.
 *
 * @since  1.0.6
 */
class DataloadModel extends AdminModel {

    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  1.0.6
     */
    protected $text_prefix = 'COM_RA_EVENTS';
    protected $csv_file;
    protected $tmp_name;

    /**
     * @var    string  Alias to manage history control
     *
     * @since  1.0.6
     */
    public $typeAlias = 'com_ra_events.dataload';
    private $item = null;

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return  void
     *
     * @since   1.0.4
     *
     * @throws  Exception
     */
    protected function populateState() {
        $app = Factory::getApplication('com_ra_events');

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_ra_events.edit.upload.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_ra_events.edit.upload.id', $id);
        }
        return true; ///////////////////////

        $this->setState('upload.id', $id);

        // Load the parameters.
        $params = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('upload.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to get an object.
     *
     * @param   integer $id The id of the object to get.
     *
     * @return  Object|boolean Object on success, false on failure.
     *
     * @throws  Exception
     */
    public function getItem($id = null) {
        return $this->item;
    }

    /**
     * Method to get the data form.
     *
     * The base form is loaded from XML
     *
     * @param   array   $data     An optional array of data for the form to interogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  Form    A Form object on success, false on failure
     *
     * @since   1.0.4
     */
    public function getForm($data = array(), $loadData = true) {
        // Get the form.
        $form = $this->loadForm('com_ra_events.upload', 'dataload', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
// if a file has been selected, show its name
        $data = Factory::getApplication()->getUserState('com_ra_events.edit.upload.data', array());
        $file = $data['file'];
        if ($file != '') {
            $form->removeField('csv_file');
            $form->setFieldAttribute('csv_file', 'hidden', "true");
            $form->setFieldAttribute('file', 'type', "textfield");
        }
        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array  The default data is an empty array.
     * @since   1.0.4
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_events.edit.upload.data', array());
        if (empty($data)) {
            $data = $this->getItem();
        }
        if ($data) {
            return $data;
        }

        return array();
    }

    /**
     * Method to save the form data.
     *
     * @param   array $data The form data
     *
     * @return  bool
     *
     * @throws  Exception
     * @since   1.0.4
     */
    public function save($data) {

        // The file details will have been set up in the function validate.
        $app = Factory::getApplication();
        $user = $this->getCurrentUser();
        // Check the user can create new items in this section
        $authorised = $user->authorise('core.create', 'com_ra_events');

        if ($authorised !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $files = $app->input->files->get('jform', array(), 'raw');

        $file_array = $files['csv_file'];
        $csv_file = $file_array['name'];
        $tmp_name = $file_array['tmp_name'];
//        $app->enqueueMessage('DataloadModel/Save: file is  ' . $csv_file, 'info');
        // Replace any special characters in the filename
        jimport('joomla.filesystem.file');
        $file_root = File::stripExt($csv_file);
        $extension = File::getExt($csv_file);
        $filename = preg_replace("/[^A-Za-z0-9]/i", "-", $file_root);
        $filename = $filename . '.' . $extension;
        $fileTemp = $tmp_name;
        $upload_folder = JPATH_ROOT . '/images/com_ra_events/';
        $upload_file = $upload_folder . $filename;
        if (File::exists($upload_file)) {
            $message = 'File ' . $filename . ' already present in ' . $upload_folder;
            //           $app->enqueueMessage($message, 'info');
            if (file_exists($upload_file) && !is_dir($upload_file)) {
                unlink($upload_file);
                $message .= ', deleted';
                $app->enqueueMessage($message, 'info');
            }
        }
        if (!File::upload($fileTemp, $upload_file)) {
            $app->enqueueMessage('Error moving file', 'warning');
            return false;
        } else {
            $app->enqueueMessage('File ' . $filename . ' uploaded', 'info');
        }

//        $app->enqueueMessage('DataloadModel: returning TRUE from save', 'info');

        return true;
    }

    public function validate($form, $data, $group = true) {
        $app = Factory::getApplication();
        $array = $app->input->get('jform', array(), 'ARRAY');
        $MIMETypes = 'text/plain,text/csv,text/comma-separated-values';

        $this->files = $app->input->files->get('jform', array(), 'raw');

        $file_array = $this->files['csv_file'];
        $filename = $file_array['name'];

        jimport('joomla.filesystem.file');

        // Check if the server found any error.
        $fileError = $file_array['error'];
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

            // Check for filetype
            $MIMETypes = 'text/plain,text/csv,text/comma-separated-values';
            $validMIMEArray = explode(',', $MIMETypes);
            $fileMime = $file_array['type'];

            if (!in_array($fileMime, $validMIMEArray)) {
                $app->enqueueMessage('Filetype <b>' . $fileMime . '</b> is not allowed (must be ' . $MIMETypes . ')', 'warning');

                return false;
            }
            $data['file'] = $filename;
            $data['tmp_name'] = $file_array['tmp_name'];
            return $data;
        }
    }

}
