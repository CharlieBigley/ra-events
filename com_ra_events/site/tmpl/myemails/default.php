<?php

/**
 * @version     2.1.9
 * @package     com_ra_events
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 05/08/25 CB created
 */
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

$app = Factory::getApplication();

// set callback in globals so reports can return as appropriate
Factory::getApplication()->setUserState('com_ra_wf.callback', 'reports');
echo '<h2>Email report for ' . $this->item->preferred_name . '</h2>';

$target = 'index.php?option=com_ra_events&task=event.showEmail';
$target .= '&callback=myemails';
$target .= '&ref_id=' . $this->user_id;
$target .= '&Itemid=' . $this->menu_id;
$target .= '&id=';

echo '<h4>Contact details</h4>';
echo '<b>Name</b> ' . $this->item->preferred_name . '<br>';
echo '<b>Category</b> ' . $this->item->title . '<br>';
echo '<b>Role</b> ' . $this->item->con_position . '<br>';
$sql = 'SELECT id, record_type, date_sent, title, body, ';
$sql .= 'sender_name, addressee_name ';
$sql .= 'FROM #__ra_emails ';
$sql .= 'WHERE sender_email="' . $this->item->email . '" ';
$sql .= 'ORDER BY record_type ';

$rows = $this->toolsHelper->getRows($sql);
$total = count($rows);
if ($total > 0) {
    echo '<h4>Emails sent</h4>';
    $objTable = new ToolsTable();
    $objTable->add_header("Type,Date,Title,Body,From,To,");
    foreach ($rows as $row) {
        $type = EventsHelper::emailType($row->record_type);
        $objTable->add_item($type);
        $objTable->add_item(HTMLHelper::_('date', $row->date_sent, 'H:i d/m/y'));
        $objTable->add_item($row->title);
        if (strlen($row->body) > 516) {
            $body = strip_tags(substr($row->body, 0, 516)) . ' ....';
//        $link = '';
//        echo $this->objHelper->buildLink($link, 'Read more', true, 'readmore') . PHP_EOL;
        } else {
            $body = strip_tags(rtrim($row->body));
        }
        $objTable->add_item($body);
        $objTable->add_item($row->sender_name);
        $objTable->add_item($row->addressee_name);

        $info = $this->toolsHelper->imageButton('I', $target . $row->id);
        $objTable->add_item($info);
        $objTable->generate_line();
    }

    $objTable->generate_table();
    echo 'Total number of emails ' . $total . '<br><br>';
}

//////////////////////////////////////////////////////
$sql = 'SELECT id, record_type, date_sent, title, body, ';
$sql .= 'sender_name, addressee_name ';
$sql .= 'FROM #__ra_emails ';
$sql .= 'WHERE addressee_email="' . $this->item->email . '" ';
$sql .= 'ORDER BY record_type ';

$rows = $this->toolsHelper->getRows($sql);
$total = count($rows);
if ($total > 0) {
    echo '<h4>Emails received</h4>';
    $objTable = new ToolsTable();
    $objTable->add_header("Type,Date,Title,Body,From,To,");
    foreach ($rows as $row) {
        $type = EventsHelper::emailType($row->record_type);
        $objTable->add_item($type);
        $objTable->add_item(HTMLHelper::_('date', $row->date_sent, 'H:i d/m/y'));
        $objTable->add_item($row->title);
        if (strlen($row->body) > 516) {
            $body = strip_tags(substr($row->body, 0, 516)) . ' ....';
//        $link = '';
//        echo $this->objHelper->buildLink($link, 'Read more', true, 'readmore') . PHP_EOL;
        } else {
            $body = strip_tags(rtrim($row->body));
        }
        $objTable->add_item($body);
        $objTable->add_item($row->sender_name);
        $objTable->add_item($row->addressee_name);
        $info = $this->toolsHelper->imageButton('I', $target . $row->id);
        $objTable->add_item($info);
        $objTable->generate_line();
    }

    $objTable->generate_table();
    echo 'Total number of emails ' . $total . '<br>';
}





