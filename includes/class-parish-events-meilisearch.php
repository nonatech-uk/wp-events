<?php
/**
 * Parish Events Meilisearch integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Parish_Events_Meilisearch {

    private $index_name = 'parish_search';

    /**
     * Get Meilisearch connection settings
     * Reuses parish-search plugin settings if available
     */
    private function get_connection() {
        // Try parish-search settings first (shared config)
        $api_url = get_option('parish_search_api_url', '');
        $admin_key = get_option('parish_search_admin_key', '');

        // Fall back to plugin-specific settings if needed
        if (empty($api_url)) {
            $api_url = get_option('parish_events_api_url', '');
        }
        if (empty($admin_key)) {
            $admin_key = get_option('parish_events_admin_key', '');
        }

        return array(
            'url' => rtrim($api_url, '/'),
            'key' => $admin_key,
        );
    }

    /**
     * Index a single event
     */
    public function index_event($post_id, $post = null) {
        if (!$post) {
            $post = get_post($post_id);
        }

        // Only index published events
        if ($post->post_status !== 'publish' || $post->post_type !== 'parish_event') {
            return;
        }

        $connection = $this->get_connection();
        if (empty($connection['url']) || empty($connection['key'])) {
            return;
        }

        $event_date = get_post_meta($post_id, '_parish_event_date', true);
        $event_time = get_post_meta($post_id, '_parish_event_time', true);
        $event_location = get_post_meta($post_id, '_parish_event_location', true);

        // Create date_sortable for filtering (YYYYMMDD format)
        $date_sortable = $event_date ? intval(str_replace('-', '', $event_date)) : 0;

        // Extract year
        $year = $event_date ? intval(substr($event_date, 0, 4)) : null;

        // Format date for display
        $date_display = $event_date ? date('j F Y', strtotime($event_date)) : '';

        $document = array(
            'id'             => 'event_' . $post_id,
            'type'           => 'event',
            'title'          => $post->post_title,
            'content'        => wp_strip_all_tags($post->post_content),
            'url'            => get_permalink($post_id),
            'date_display'   => $date_display,
            'date_sortable'  => $date_sortable,
            'year'           => $year,
            'event_time'     => $event_time,
            'event_location' => $event_location,
        );

        $this->send_to_meilisearch($connection, $document);
    }

    /**
     * Delete event from index
     */
    public function delete_event($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'parish_event') {
            return;
        }

        $connection = $this->get_connection();
        if (empty($connection['url']) || empty($connection['key'])) {
            return;
        }

        $this->delete_from_meilisearch($connection, 'event_' . $post_id);
    }

    /**
     * Sync all events to Meilisearch
     */
    public function sync_all() {
        $events = get_posts(array(
            'post_type'      => 'parish_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ));

        foreach ($events as $event) {
            $this->index_event($event->ID, $event);
        }

        return count($events);
    }

    /**
     * Send document to Meilisearch
     */
    private function send_to_meilisearch($connection, $document) {
        $url = $connection['url'] . '/indexes/' . $this->index_name . '/documents';

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $connection['key'],
            ),
            'body'    => json_encode(array($document)),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            error_log('Parish Events: Failed to index event - ' . $response->get_error_message());
        }
    }

    /**
     * Delete document from Meilisearch
     */
    private function delete_from_meilisearch($connection, $document_id) {
        $url = $connection['url'] . '/indexes/' . $this->index_name . '/documents/' . $document_id;

        $response = wp_remote_request($url, array(
            'method'  => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $connection['key'],
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            error_log('Parish Events: Failed to delete event - ' . $response->get_error_message());
        }
    }
}
