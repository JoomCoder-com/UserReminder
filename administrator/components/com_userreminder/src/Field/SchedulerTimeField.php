<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_userreminder
 *
 * @copyright   Copyright (C) 2026 JoomCoder. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomCoder\Component\UserReminder\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;

/**
 * Scheduler time field — renders hour / weekday / day-of-month options in a
 * single select. The options switch based on the parent's
 * `scheduledExecutionType` (1=daily, 2=weekly, 3=monthly) via JS in
 * the admin form definition.
 *
 * @since  4.0.0
 */
class SchedulerTimeField extends ListField
{
    protected $type = 'SchedulerTime';

    /**
     * @var  string[]
     *
     * @since  4.0.0
     */
    private const HOURS = [
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        '10', '11', '12', '13', '14', '15', '16', '17', '18', '19',
        '20', '21', '22', '23',
    ];

    /**
     * @var  string[]
     *
     * @since  4.0.0
     */
    private const WEEKDAYS = ['1', '2', '3', '4', '5', '6', '7'];

    /**
     * @var  string[]
     *
     * @since  4.0.0
     */
    private const MONTHDAYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10',
        '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
        '21', '22', '23', '24', '25', '26', '27', '28'];

    /**
     * @return  string[]  The full set of options, including the placeholder row.
     *
     * @since   4.0.0
     */
    protected function getOptions(): array
    {
        $hourLabel    = Text::_('COM_USERREMINDER_TIME_HOUR');
        $weekdayLabel = Text::_('COM_USERREMINDER_TIME_WEEKDAY1');
        $selectLabel  = Text::_('COM_USERREMINDER_SELECT_TIME');
        $daySuffix1   = Text::_('COM_USERREMINDER_TIME_MONTHDAY1');
        $daySuffix2   = Text::_('COM_USERREMINDER_TIME_MONTHDAY2');
        $daySuffix3   = Text::_('COM_USERREMINDER_TIME_MONTHDAY3');
        $daySuffix4   = Text::_('COM_USERREMINDER_TIME_MONTHDAY4');

        $options = [
            (object) ['value' => '', 'text' => $selectLabel, 'disable' => false],
        ];

        // Hours 0..23 — guarded by `disabled` so they only show on type=1.
        foreach (self::HOURS as $hour) {
            $options[] = (object) [
                'value'   => $hour,
                'text'    => $hour . ':00 ' . $hourLabel,
                'disable' => false,
                'class'   => 'ur-hour-option',
            ];
        }

        // Weekdays 1..7 — guarded for type=2.
        foreach (self::WEEKDAYS as $day) {
            $key       = 'COM_USERREMINDER_TIME_WEEKDAY' . $day;
            $label    = Text::_($key);
            $options[] = (object) [
                'value'   => $day,
                'text'    => $label,
                'disable' => false,
                'class'   => 'ur-weekday-option',
            ];
        }

        // Day-of-month 1..28 — guarded for type=3.
        foreach (self::MONTHDAYS as $day) {
            $suffix = match ((int) $day) {
                1, 21, 31 => $daySuffix1,
                2, 22     => $daySuffix2,
                3, 23     => $daySuffix3,
                default   => $daySuffix4,
            };

            $options[] = (object) [
                'value'   => $day,
                'text'    => $day . $suffix,
                'disable' => false,
                'class'   => 'ur-monthday-option',
            ];
        }

        return array_merge(parent::getOptions(), $options);
    }
}