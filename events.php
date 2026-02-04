<?php
/**
 * Plugin Name: Events
 * Plugin URI: https://github.com/nonatech-uk/wp-events
 * Description: Manage and display parish events with calendar, iCal feed, and Meilisearch integration
 * Version: 1.5.3
 * Author: NonaTech Services Ltd
 * License: GPL v2 or later
 * Text Domain: events
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PARISH_EVENTS_VERSION', '1.5.3');
define('PARISH_EVENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PARISH_EVENTS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PARISH_EVENTS_PLUGIN_DIR . 'includes/class-parish-events.php';
require_once PARISH_EVENTS_PLUGIN_DIR . 'includes/class-parish-events-recurrence.php';
require_once PARISH_EVENTS_PLUGIN_DIR . 'includes/class-parish-events-ical.php';
require_once PARISH_EVENTS_PLUGIN_DIR . 'includes/class-parish-events-meilisearch.php';
require_once PARISH_EVENTS_PLUGIN_DIR . 'includes/class-parish-events-settings.php';
require_once PARISH_EVENTS_PLUGIN_DIR . 'includes/class-github-updater.php';

function parish_events_init() {
    global $parish_events_plugin, $parish_events_settings;
    $parish_events_settings = new Parish_Events_Settings();
    $parish_events_plugin = new Parish_Events();
    $parish_events_plugin->init();

    // Initialize GitHub updater
    if (is_admin()) {
        new Events_GitHub_Updater(
            __FILE__,
            'nonatech-uk/wp-events',
            PARISH_EVENTS_VERSION
        );
    }
}
add_action('plugins_loaded', 'parish_events_init');

function parish_events_activate() {
    // Flush rewrite rules for custom post type and iCal feed
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'parish_events_activate');

function parish_events_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'parish_events_deactivate');
