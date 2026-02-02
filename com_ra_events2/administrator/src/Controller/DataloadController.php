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
 * 02/04/25 CB reinstate model->save (to actually copy the data file)
 * 07/04/25 CB use JPATH_ROOT for uploaded file
 * 10/04/25 CB validateUser to check username not already in use
 */

namespace Ramblers\Component\Ra_events\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
//use Joomla\CMS\Language\Multilanguage;
//use Joomla\CMS\Language\Text;
//use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Dataload controller class.
 *
 * @since  1.0.2
 */
class DataloadController extends FormController {

    protected $view_item = 'dataload';
// Ensure control returns to Dashboard, not dataloads
    protected $view_list = 'dashboard';
    // These four variables are passed as parameters by the calling program
    public $group_code;
    public $event_id;
    public $mode;
    public $filename;
    // These variables are used internally
    public $email;
    public $name;
    public $partner;
    public $user_id;
    protected $open;
    protected $toolsHelper;
    protected $error_count;
    protected $record_count;
    protected $bookings_created = 0;
    protected $bookings_required = 0;
    protected $users_created = 0;
    protected $users_required = 0;

//    public function __construct($config = array(), MVCFactoryInterface $factory = null, $app = null, $input = null) {
//        parent::__construct($config, $factory, $app, $input);
//        $this->db = Factory::getDbo();
//        $this->toolsHelper = new ToolsHelper;
//        $this->app = Factory::getApplication();
//        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
//        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
//    }

    public function cancel($key = null, $urlVar = null) {
        // Flush the data from the session..
        $this->app->setUserState('com_ra_events.edit.upload.data', null);
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    public function createUser() {
        // This creates a User record and a record in ra_profiles
        $table = Factory::getApplication()->bootComponent('com_ra_events')->getMVCFactory()->createTable('Profile', 'Administrator');
        $table->home_group = $this->group_code;
        $table->real_name = $this->name;
        $table->user_email = $this->email;
        $response = $table->store();
        if ($response == false) {
            $this->app->enqueueMessage($table->message, 'error');
        }
        return $response;
    }

    protected function parseLine($data) {
        /*
         * Sets up the internal fields this->name, this->email etc
         */

        if ($this->record_count == 1) {
            echo 'Ignoring header row<br>';
            return 0;
        } else {
            $return = 1;
            $this->group_code = $data[0];
            $this->name = $data[1];
            $this->email = $data[2];
            $this->partner = $data[3];
            if ($this->group_code == '') {
                $this->error_count++;
                echo '<b>First column (Group code) is blank' . "</b><br>";
                $return = 0;
            }

            if ($this->name == '') {
                $this->error_count++;
                $message = '<b>Second column (name) is blank</b>';
                $message .= ', email=' . $this->email;
                echo $message . "<br>";
                $return = 0;
            }

            if ($this->email == '') {
                $this->error_count++;
                echo '<b>Third column (email) is blank' . "</b><br>";
                $return = 0;
            }

            return $return;
        }
    }

    public function processFile() {
        /*
         * This function is called twice:
         * The first time it is invoked from function save without a parameter,
         * so mode defaults to 1 - display mode
         *
         * After this, it invokes itself a second time passing parameter mode=2.
         * This carries out the actual updating of the database
         *
         * It needs two items of data: firstly the id of the event, secondly the
         * filename. Because these must be saved between two transactions they are stored in the user state.
         */
        // See if this is validation mode or processing mode
        $app = Factory::getApplication();
        $this->mode = $app->input->getInt('mode', '1');
        // Input details cannot be passed as parameters but are held in the state

        $this->event_id = $app->getUserState('com_ra_events.dataload.event_id', 0);
        if ($this->event_id == 0) {
            throw new \Exception("Can't find event number", 403);
        }
        $filename = $app->getUserState('com_ra_events.dataload.filename', '');
        if ($filename == '') {
            throw new \Exception("Can't find filename", 403);
        }
        $this->filename = JPATH_ROOT . '/images/com_ra_events/' . $filename;
        if (JDEBUG) {
            $diagnostic = "Event=$this->event_id, mode= $this->mode, filename= $this->filename";
            $app->enqueueMessage("Controller: " . $diagnostic, 'Message');
        }

        // Initialise variables.
        $back = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        $this->record_count = 0;
        $this->error_count = 0;
        $this->users_required = 0;
        $this->users_created = 0;
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
        $toolsHelper = new ToolsHelper;

        $sql = "Select group_code, title from `#__ra_events` "
                . "WHERE id='" . $this->event_id . "'";
        $item = $toolsHelper->getItem($sql);

        $this->group_code = $item->group_code;
        $title = $item->group_code . ' ' . $item->title;

        if ($this->mode == 2) {
            echo '<h2>Processing ';
        } else {
            echo '<h2>Validating ';
        }
        echo 'CSV';

        echo '<h4>List=' . $title . '<br>File=';
        if (file_exists($this->filename)) {
            echo $filename . '</h4>';
        } else {
            echo $this->filename . ' not found</h4>';
            echo $toolsHelper->backButton($back);
            return false;
        }

//        if (substr(JPATH_ROOT, 14, 6) == 'joomla') {
//            echo '<h4>deleting test data</h4> ';
//            $this->purgeTestData();
//        }

        $response = $this->processRecords();
        echo '<br>' . $this->record_count . ' records read<br>';
        if ($this->error_count > 0) {
            echo "<b>$this->error_count errors</b><br>";
        }

        // Redirect as appropriate

        if ($this->mode == 1) {
            if ($this->users_required > 0) {
                echo $this->users_required . ' Users required<br>';
            }
            if ($this->bookings_required > 0) {
                echo $this->bookings_required . ' Bookings required<br>';
            }
            echo '<h4>If you continue, updates will be applied to the database.</h4>';
            $target = 'administrator/index.php?option=com_ra_events&view=dataload';
            echo $toolsHelper->buildButton($target, 'Cancel', False, 'granite');
            if (($this->bookings_required > 0 ) OR ($this->users_required > 0)) {
                $target = 'administrator/index.php?option=com_ra_events&task=dataload.processFile&mode=2';
                echo $toolsHelper->buildButton($target, 'Continue', False, 'red');
            }
        } else {
            if ($this->users_created > 0) {
                echo $this->users_created . ' Users created<br>';
            }
            if ($this->bookings_created > 0) {
                echo $this->bookings_created . ' Bookings created<br>';
            }
            // Flush the data from the session..
            $app->setUserState('com_ra_events.edit.upload.data', null);
            $app->setUserState('com_ra_events.dataload.event_id', null);
            $app->setUserState('com_ra_events.dataload.filename', null);

            echo $toolsHelper->backButton($back);
        }
    }

    protected function processRecords() {
        //       return true;

        $helper = New BookingHelper;

        $this->record_count = 0;
        $handle = fopen($this->filename, "r");
        if ($handle == 0) {
            echo 'Unable to open ' . $this->filename . '<br>';
            return 0;
        }
//        die('File ' . $this->filename . ' opened OK');
        $sql_lookup = 'SELECT id FROM #__users WHERE email="';
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $this->record_count++;
            if (JDEBUG) {
                echo $this->record_count . ': ';
            }
            if ($this->record_count == 1) {
                echo 'Ignoring header row<br>';
            } elseif (substr($data[0], 0, 1) == '#') {
                echo 'Ignoring comment ' . $data[0] . ',' . $data[1], '<br>';
            } else {
                /*
                 * After $this->parseLine, the following variables will have been set up:
                 *     $this->group_code
                 *     $this->name
                 *     $this->email
                 */
                if (($this->parseLine($data))) {
                    if (JDEBUG) {
                        echo 'group=' . $this->group_code . ', name=' . $this->name . ', email=' . $this->email;
                        if ($this->partner !== '') {
                            echo ', partner=' . $this->partner;
                        }
                    }
                    $booking_required = false;
                    $message = '';
                    $user_id = $helper->validateUser($this->email, $this->name);
                    if (JDEBUG) {
                        echo ', user_id=' . $user_id . "<br>";
                    }
                    if ($user_id == 0) {
                        $this->users_required++;
                        $this->bookings_required++;
                        $message .= 'User ' . $this->name . ' <b>not present</b> (' . $this->email . ')';
                        if ($this->mode == 2) {
                            $response = $this->createUser();
                            if ($response) {
                                $user_id = $response;
                                $message .= ', User created';
                                if (JDEBUG) {
                                    $message .= ', id=' . $response;
                                }
                                $this->users_created++;
                                $booking_required = true;
                            } else {
                                $booking_required = false;
                                $message .= ', Error creating User ' . $this->name . '/' . $this->email;
                            }
                        }
                    } elseif ($user_id > 0) {
                        // User already exists
                        $message .= 'User ' . $this->name . ' exists for ' . $this->email;
                        $method = $helper->isBooked($this->event_id, $user_id);
                        if ($method == '') {
                            $message .= ', Booking <b>not present</b>';
                            $booking_required = true;
                            $this->bookings_required++;
                        } else {
                            $message .= ', Booking <b>present</b>';
                        }
                    } else {
                        // User exists buit is invalid
                        $message = $helper->message;
                    }

                    if (($booking_required) AND ($this->mode == 2)) {
                        $new_id = $helper->createBooking($this->event_id, $user_id, 1, $this->partner);
                        $message .= ', <b>Booking created</b> ';
                        $this->bookings_created++;
                    }
                    echo $message . '<br>';
                }
            }
//            if (($this->record_count == 30) AND ($app->isClient('site'))) {            // Development
//                return;
//            }
        }
        fclose($handle);
        return true;
    }

    /**
     * Method to save data.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   1.0.4
     */
    public function save($key = NULL, $urlVar = NULL) {
//        die('Controller save');
        // The actual processing of the data file is carried out in function process File
        // Check for request forgeries.
        $this->checkToken();

        // Initialise variables.
        $model = $this->getModel('Dataload', 'Administrator');

        // Get the user data.
        $app = Factory::getApplication();
        $data = $this->input->get('jform', array(), 'array');

        $files = $app->input->files->get('jform', array(), 'raw');
        $csv_file = $files['csv_file'];
        $filename = $csv_file['name'];

        // Validate the posted data.
        $data = $model->validate($form, $data);
        //       var_dump($filename);
        //       echo 'file is ' . $filename . '<br><br>';

        if ($filename == '') {
            $this->app->enqueueMessage('Please select the file', 'warning');
            $jform = $this->input->get('jform', array(), 'ARRAY');

            // Save the data in the session.
            $this->app->setUserState('com_ra_mailman.edit.upload.data', $jform);

            // Redirect back to the edit screen.
            $this->setRedirect(Route::_('/administrator/index.php?option=com_ra_mailman&view=dataload', false));
            $this->redirect();
        }
        // Save the data in the session.
        $this->app->setUserState('com_ra_mailman.edit.upload.data', $data);

        // Attempt to save the data.
        $return = $model->save($data);

        // Check for errors (lack of authority)
        if ($return === false) {
            // Redirect back to the edit screen.
            $this->setMessage('Save failed', $model->getError(), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ra_events&view=dataload&layout=edit', false));
            $this->redirect();
        }
//        echo 'Controller save 2<br>';
        $data = $this->input->get('jform', array(), 'array');
//        var_dump($data);
//        die;
        $this->app->setUserState('com_ra_events.dataload.event_id', $data['event']);
        $this->app->setUserState('com_ra_events.dataload.filename', $filename);
        $this->processFile();
    }

}
