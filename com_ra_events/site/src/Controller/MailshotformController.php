<?php
/*
 * @version    2.5.0
 * @component  com_ra_events
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 30/03/26 GPT copied from com_ra_mailman
 */

namespace Ramblers\Component\Ra_events\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class MailshotformController extends FormController {

    public function cancel($key = NULL) {
        $editId = (int) $this->app->getUserState('com_ra_events.edit.mailshot.id');
        $model = $this->getModel('Mailshotform', 'Site');
        if ($editId) {
            $model->checkin($editId);
        }
        $url = 'index.php?option=com_ra_events&view=event';
        $event_id = $this->app->getUserState('com_ra_events.event.id');
         if (!empty($event_id)) {
             $url .= '&id=' . $event_id;
         }
        // find the menu item to which we should return
        $menu_id = $this->app->getUserState('com_ra_events.event.menu_id');
        if (!empty($menu_id)) {
            $url .= '&Itemid=' . $menu_id;
        }
        
        $this->setRedirect(Route::_($url, false));
    }
    
    public function edit($key = NULL, $urlVar = NULL) {
        $previousId = (int) $this->app->getUserState('com_ra_events.edit.mailshot.id');
        $editId = $this->input->getInt('id', 0);
        $this->app->setUserState('com_ra_events.edit.mailshot.id', $editId);
        $model = $this->getModel('Mailshotform', 'Site');
        if ($editId) {
            $model->checkout($editId);
        }
        if ($previousId) {
            $model->checkin($previousId);
        }
        $this->setRedirect(Route::_('index.php?option=com_ra_events&view=mailshotform&layout=edit', false));
    }

        private function handleValidationErrors($model, $event_id) {
        $errors = $model->getErrors();
        for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
            if ($errors[$i] instanceof \Exception) {
                $this->app->enqueueMessage($errors[$i]->getMessage(), 'warning');
            } else {
                $this->app->enqueueMessage($errors[$i], 'warning');
            }
        }
        $jform = $this->input->get('jform', array(), 'ARRAY');
        $this->app->setUserState('com_ra_events.edit.mailshot.data', $jform);
        $id = (int) $this->app->getUserState('com_ra_events.edit.mailshot.id');
        $target = 'index.php?option=com_ra_events&view=mailshotform&layout=edit&id=' . $id;
        $target .= '&event_id=' . $event_id;
        // find the menu item to which we should return
        $menu_id = $this->app->getUserState('com_ra_events.event.menu_id');
        if (!empty($menu_id)) {
            $target .= '&Itemid=' . $menu_id;
        }        
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    private function handleSaveErrors($model, $data, $event_id) {
        $this->app->setUserState('com_ra_events.edit.mailshot.data', $data);
        $id = (int) $this->app->getUserState('com_ra_events.edit.mailshot.id');
        $this->setMessage(Text::sprintf('Save failed', $model->getError()), 'warning');
        $target = 'index.php?option=com_ra_events&view=mailshotform&layout=edit&id=' . $id;
        $target .= '&event_id=' . $event_id;
        // find the menu item to which we should return
        $menu_id = $this->app->getUserState('com_ra_events.event.menu_id');
        if (!empty($menu_id)) {
            $target .= '&Itemid=' . $menu_id;
        }        
        $this->setRedirect(Route::_($target, false));
        $this->redirect();
    }

    public function save($key = NULL, $urlVar = NULL) {
        $return = $this->performSave();
        if ($return !== false) {        
            $this->app->setUserState('com_ra_events.edit.mailshot.id', null);
            $url = 'index.php?option=com_ra_events&view=event';
            $event_id = $this->input->get('jform', array(), 'array')['event_id'];
            if (!empty($event_id)) {
                $url .= '&id=' . $event_id;
            }
            // find the menu item to which we should return
            $menu_id = $this->app->getUserState('com_ra_events.event.menu_id');
            if (!empty($menu_id)) {
                $url .= '&Itemid=' . $menu_id;
            }
        
            $this->setRedirect(Route::_($url, false));
        }
    }

    public function savecontinue($key = NULL, $urlVar = NULL) {
        $return = $this->performSave();
        if ($return !== false) {
            $event_id = $this->input->get('jform', array(), 'array')['event_id'];
            $this->app->setUserState('com_ra_events.edit.mailshot.id', $return);
            $target = 'index.php?option=com_ra_events&view=mailshotform&layout=edit&id=' . $return;
            $target .= '&event_id=' . $event_id;
            $this->setRedirect(Route::_($target, false));
        }
    }

    private function performSave() {
        $this->checkToken();
        $model = $this->getModel('Mailshotform', 'Site');
        $data = $this->input->get('jform', array(), 'array');
        $form = $model->getForm();
        if (!$form) {
            throw new \Exception($model->getError(), 500);
        }
        $objData = (object) $data;
        $this->app->triggerEvent(
            'onContentNormaliseRequestData',
            array($this->option . '.' . $this->context, $objData, $form)
        );
        $data = (array) $objData;
        $data = $model->validate($form, $data);
        $event_id = $data['event_id'];
        
        if ($data === false) {
            $this->handleValidationErrors($model, $event_id);
            return false;
        }
        
        $return = $model->save($data);
        if ($return === false) {
            $this->handleSaveErrors($model, $data, $event_id);
            return false;
        }
        
        if ($return) {
            $model->checkin($return);
        }
        
        if (!empty($return)) {
            $this->setMessage(Text::_('Mailshot saved successfully'));
        }
        
        $this->app->setUserState('com_ra_events.edit.mailshot.data', null);
        $this->postSaveHook($model, $data);
        
        return $return;
    }

    public function remove() {
        $model = $this->getModel('Mailshotform', 'Site');
        $pk = $this->input->getInt('id');
        try {
            $return = $model->checkin($return);
            $this->app->setUserState('com_ra_events.edit.mailshot.id', null);
            $menu = $this->app->getMenu();
            $item = $menu->getActive();
            $url = (empty($item->link) ? 'index.php?option=com_ra_events&view=mailshots' : $item->link);
            if ($return) {
                $model->delete($pk);
                $this->setMessage(Text::_('COM_RA_EVENTS_ITEM_DELETED_SUCCESSFULLY'));
            } else {
                $this->setMessage(Text::_('COM_RA_EVENTS_ITEM_DELETED_UNSUCCESSFULLY'), 'warning');
            }
            $this->setRedirect(Route::_($url, false));
            $this->app->setUserState('com_ra_events.edit.mailshot.data', null);
        } catch (\Exception $e) {
            $errorType = ($e->getCode() == '404') ? 'error' : 'warning';
            $this->setMessage($e->getMessage(), $errorType);
            $this->setRedirect('index.php?option=com_ra_events&view=mailshots');
        }
    }

    protected function postSaveHook(BaseDatabaseModel $model, $validData = array()) {
    }
}
