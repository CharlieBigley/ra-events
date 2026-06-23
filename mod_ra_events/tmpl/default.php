<?php

/**
 * @module	mod_ra_events sidebar
 * @author	Charlie Bigley
 * version      2.0.4
 * @website	https://demo.stokeandnewcastleramblers.org.uk
 * @copyleft	Copyleft 2021 Charlie Bigley webmaster@stokeandnewcastleramblers.org.uk All rights reserved.
 * @license	http://www.gnu.org/licenses/gpl.html GNU/GPL

 * 16/09/23 CB created from mod_ra_tools
 * 22/12/23 CB don't show start time for holidays
 * 04/12/24 CB change description to title
 * 17/01/25 CB prepare for publication date
 * 30/03/25 CB Don't show events until their publication date
 * 05/07/25 CB use table event_types
 * 29/09/25 CB show date before time
 * 17/06/26 CB show holiday end date
 */
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Component\ComponentHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

// no direct access
defined("_JEXEC") or die("Restricted access");
$restrict_events = $params->get('restrict_events');
$lookahead_weeks = $params->get('lookahead_weeks');
$limit = (int) $params->get('limit');
//echo "restrict_events = $restrict_events<br>";
//echo "lookahead_weeks = $lookahead_weeks<br>";
//echo "limit= $limit  <br>";
//echo $display_type;
$objHelper = new ToolsHelper;
$sql = 'SELECT a.id, a.event_type_id, event_date, event_date_end, event_time, group_code, a.title ';
$sql .= 'FROM `#__ra_events` AS a ';
$sql .= 'LEFT JOIN #__ra_event_types AS event_type ON event_type.id = a.event_type_id ';
$sql .= 'WHERE a.state=1 ';
$sql .= 'AND (datediff(event_date, CURRENT_DATE) >= 0) ';
$sql .= 'AND (datediff(publication_date, CURRENT_DATE) <= 0) ';
if ($restrict_events == 2) {
    $days = 7 * $lookahead_weeks;
    $sql .= 'AND (datediff(event_date, CURRENT_DATE) <' . $days . ') ';
}
$sql .= 'ORDER BY event_date ';
if ($restrict_events == 1) {
    $sql .= 'LIMIT ' . $limit;
}
//echo $sql;
//$objHelper->showSql($sql);
$target = 'index.php?option=com_ra_events&view=event&id=';
$rows = $objHelper->getRows($sql);
foreach ($rows as $row) {
    echo HTMLHelper::_('date', $row->event_date, 'd-m-y') . ', ';
    if ($row->event_type_id == 4) {
        echo HTMLHelper::_('date', $row->event_date_end, 'd-m-y') . ', ';
    } else {
        echo $row->event_time . ', ';
    }
    echo $objHelper->buildLink($target . $row->id, $row->title) . '<br>';
}


