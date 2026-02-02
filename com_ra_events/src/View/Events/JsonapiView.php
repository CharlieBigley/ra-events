<?php

/**
 * @version    2.2.1
 * @package    com_ra_events
 * @author     Martin King <martinkingesra@gmail.com>
 * @copyright  2025 Martin King
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 01/09/25 CB add state and contact_details
 * 10/09/25 CB add original_id
 */

namespace Ramblers\Component\Ra_events\Api\View\Events;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

/**
 * The Events view
 *
 * @since  1.0.9
 */
class JsonapiView extends BaseApiView {

    /**
     * The fields to render item in the documents
     *
     *  NOTE - the api form MUST reflect the contents of this list in order to pass the
     * data through to the write to the database functions
     *
     * @var    array
     * @since  1.0.9
     */
    protected $fieldsToRenderItem = [
        'id',
        'original_id',
        'state',
        'bookable',
        'event_id',
        'event_date',
        'event_date_end',
        'event_time',
        'event_time_end',
        'event_type_id',
        'title',
        'details',
        'reports',
        'minutes',
        'group_code',
        'location',
        'contact_id',
        'url',
        'url_description',
        'attachments',
        'attachment_description',
    ];

    /**
     * The fields to render items in the documents
     * N.B. the sequence in which fields are listed does not effect the order they are presented
     *
     * @var    array
     * @since  1.0.9
     */
    protected $fieldsToRenderList = [
        'id',
        'event_type_id',
        'event_date',
        'event_date_end',
        'event_time',
        'title',
        'details',
        'reports',
        'minutes',
        'group_code',
        'location',
        'url',
        'url_description',
        'attachments',
        'attachment_description',
        'shareable',
        'share_date',
        'publication_date',
        'bookable',
        'notify_organiser',
        'booking_info',
        'num_bookings',
        'max_bookings',
        'state',
        'contact_name',
    ];

}
