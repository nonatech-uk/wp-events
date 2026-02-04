<?php
/**
 * Parish Events Recurrence Handler
 *
 * Generates occurrence dates for recurring events.
 * Individual posts are created for each occurrence.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Parish_Events_Recurrence {

    /**
     * Generate all occurrence dates for a recurring event
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $rule Recurrence rule (weekly, monthly, monthly_ordinal, yearly)
     * @param string $end_date End date (Y-m-d)
     * @param array $options Additional options (interval, ordinal, weekday)
     * @return array Array of date strings (Y-m-d format)
     */
    public function get_occurrence_dates($start_date, $rule, $end_date, $options = array()) {
        $dates = array();

        if (empty($rule) || empty($start_date)) {
            return array($start_date);
        }

        $start = new DateTime($start_date);
        $end = !empty($end_date)
            ? new DateTime($end_date)
            : new DateTime('+1 year');

        // Safety limit
        $max_occurrences = 100;

        // First occurrence is the start date
        $dates[] = $start->format('Y-m-d');
        $current = clone $start;

        while (count($dates) < $max_occurrences) {
            $current = $this->get_next_occurrence($current, $rule, $options);

            if ($current > $end) {
                break;
            }

            $dates[] = $current->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * Get the next occurrence date based on recurrence rule
     */
    private function get_next_occurrence($date, $rule, $options = array()) {
        $next = clone $date;

        switch ($rule) {
            case 'weekly':
                $interval = isset($options['interval']) ? (int) $options['interval'] : 1;
                $next->modify("+{$interval} week");
                break;

            case 'monthly':
                $day = (int) $date->format('j');
                $next->modify('first day of next month');
                $max_day = (int) $next->format('t');
                $next->setDate(
                    (int) $next->format('Y'),
                    (int) $next->format('n'),
                    min($day, $max_day)
                );
                break;

            case 'monthly_ordinal':
                $ordinal = isset($options['ordinal']) ? $options['ordinal'] : 'first';
                $weekday = isset($options['weekday']) ? $options['weekday'] : 'monday';
                $next = $this->get_next_ordinal_weekday($date, $ordinal, $weekday);
                break;

            case 'yearly':
                $next->modify('+1 year');
                break;
        }

        return $next;
    }

    /**
     * Get the next occurrence of an ordinal weekday
     */
    private function get_next_ordinal_weekday($date, $ordinal, $weekday) {
        $next = clone $date;
        $next->modify('first day of next month');

        $modifier = "{$ordinal} {$weekday} of this month";
        $next->modify($modifier);

        return $next;
    }
}
