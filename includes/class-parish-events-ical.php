<?php
/**
 * Parish Events iCal Feed
 *
 * Each event is exported as an individual VEVENT (no RRULE).
 * Recurring events are stored as separate posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Parish_Events_ICal {

    public function init() {
        add_action('template_redirect', array($this, 'handle_ical_request'));
    }

    /**
     * Handle iCal feed request
     */
    public function handle_ical_request() {
        if (!get_query_var('parish_events_ical')) {
            return;
        }

        $this->output_ical();
        exit;
    }

    /**
     * Output iCal feed
     */
    public function output_ical() {
        $events = $this->get_events_for_ical();

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="albury-parish-events.ics"');

        echo $this->generate_ical($events);
    }

    /**
     * Get events for iCal
     */
    private function get_events_for_ical() {
        $query_args = array(
            'post_type'      => 'parish_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_parish_event_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        );

        $events_query = new WP_Query($query_args);
        $events = array();

        if ($events_query->have_posts()) {
            while ($events_query->have_posts()) {
                $events_query->the_post();
                $post_id = get_the_ID();

                $events[] = array(
                    'id'         => $post_id,
                    'title'      => get_the_title(),
                    'content'    => wp_strip_all_tags(get_the_content()),
                    'permalink'  => get_permalink(),
                    'date'       => get_post_meta($post_id, '_parish_event_date', true),
                    'time'       => get_post_meta($post_id, '_parish_event_time', true),
                    'end_date'   => get_post_meta($post_id, '_parish_event_end_date', true),
                    'end_time'   => get_post_meta($post_id, '_parish_event_end_time', true),
                    'location'   => get_post_meta($post_id, '_parish_event_location', true),
                    'modified'   => get_the_modified_time('c'),
                );
            }
        }
        wp_reset_postdata();

        return $events;
    }

    /**
     * Generate iCal content
     */
    private function generate_ical($events) {
        $site_name = get_bloginfo('name');

        $lines = array();
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//Albury Parish Council//Parish Events//EN';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:' . $this->escape_ical($site_name . ' Events');
        $lines[] = 'X-WR-TIMEZONE:Europe/London';

        // Timezone definition
        $lines[] = 'BEGIN:VTIMEZONE';
        $lines[] = 'TZID:Europe/London';
        $lines[] = 'BEGIN:STANDARD';
        $lines[] = 'DTSTART:19701025T020000';
        $lines[] = 'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10';
        $lines[] = 'TZOFFSETFROM:+0100';
        $lines[] = 'TZOFFSETTO:+0000';
        $lines[] = 'TZNAME:GMT';
        $lines[] = 'END:STANDARD';
        $lines[] = 'BEGIN:DAYLIGHT';
        $lines[] = 'DTSTART:19700329T010000';
        $lines[] = 'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3';
        $lines[] = 'TZOFFSETFROM:+0000';
        $lines[] = 'TZOFFSETTO:+0100';
        $lines[] = 'TZNAME:BST';
        $lines[] = 'END:DAYLIGHT';
        $lines[] = 'END:VTIMEZONE';

        foreach ($events as $event) {
            $lines = array_merge($lines, $this->event_to_vevent($event));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines);
    }

    /**
     * Convert event to VEVENT
     */
    private function event_to_vevent($event) {
        $lines = array();
        $lines[] = 'BEGIN:VEVENT';

        // UID
        $uid = 'event-' . $event['id'] . '@' . parse_url(get_bloginfo('url'), PHP_URL_HOST);
        $lines[] = 'UID:' . $uid;

        // Dates
        if (!empty($event['time'])) {
            $dtstart = $this->format_datetime($event['date'], $event['time']);
            $lines[] = 'DTSTART;TZID=Europe/London:' . $dtstart;

            if (!empty($event['end_date']) && !empty($event['end_time'])) {
                $dtend = $this->format_datetime($event['end_date'], $event['end_time']);
            } elseif (!empty($event['end_time'])) {
                $dtend = $this->format_datetime($event['date'], $event['end_time']);
            } else {
                $dtend = $this->format_datetime($event['date'], $event['time'], '+1 hour');
            }
            $lines[] = 'DTEND;TZID=Europe/London:' . $dtend;
        } else {
            // All-day event
            $dtstart = str_replace('-', '', $event['date']);
            $lines[] = 'DTSTART;VALUE=DATE:' . $dtstart;

            if (!empty($event['end_date'])) {
                $end = new DateTime($event['end_date']);
                $end->modify('+1 day');
                $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
            } else {
                $end = new DateTime($event['date']);
                $end->modify('+1 day');
                $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
            }
        }

        // Summary
        $lines[] = 'SUMMARY:' . $this->escape_ical($event['title']);

        // Description
        if (!empty($event['content'])) {
            $lines[] = 'DESCRIPTION:' . $this->escape_ical($event['content']);
        }

        // Location
        if (!empty($event['location'])) {
            $lines[] = 'LOCATION:' . $this->escape_ical($event['location']);
        }

        // URL
        if (!empty($event['permalink'])) {
            $lines[] = 'URL:' . $event['permalink'];
        }

        // Timestamps
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');

        if (!empty($event['modified'])) {
            $modified = new DateTime($event['modified']);
            $lines[] = 'LAST-MODIFIED:' . $modified->format('Ymd\THis\Z');
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * Format date and time for iCal
     */
    private function format_datetime($date, $time, $modify = null) {
        $dt = new DateTime($date . ' ' . $time);
        if ($modify) {
            $dt->modify($modify);
        }
        return $dt->format('Ymd\THis');
    }

    /**
     * Escape text for iCal
     */
    private function escape_ical($text) {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace("\n", '\\n', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(';', '\\;', $text);
        return $text;
    }
}
