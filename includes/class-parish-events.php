<?php
/**
 * Main Parish Events class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Parish_Events {

    private $meilisearch;
    private $ical;
    private $recurrence;

    public function __construct() {
        $this->meilisearch = new Parish_Events_Meilisearch();
        $this->ical = new Parish_Events_ICal();
        $this->recurrence = new Parish_Events_Recurrence();
    }

    /**
     * Get settings instance
     */
    private function get_settings() {
        global $parish_events_settings;
        return $parish_events_settings;
    }

    /**
     * Resolve file pattern placeholders from event date
     */
    private function resolve_file_pattern($pattern, $date) {
        $dt = new DateTime($date);
        $replacements = array(
            '{YYYY}' => $dt->format('Y'),
            '{YY}'   => $dt->format('y'),
            '{MM}'   => $dt->format('m'),
            '{DD}'   => $dt->format('d'),
        );
        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    /**
     * Convert relative path to URL
     */
    private function get_document_url($relative_path) {
        $settings = $this->get_settings();
        $base_path = $settings->get_setting('nextcloud_base_path');

        // Get URL by converting filesystem path to web path
        // Assumes base_path is under DOCUMENT_ROOT (e.g., /var/www/html)
        $doc_root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html', '/');

        if (strpos($base_path, $doc_root) === 0) {
            $url_path = str_replace($doc_root, '', $base_path);
            $full_path = $url_path . '/' . $relative_path;
            // Encode spaces but keep path separators
            $full_path = str_replace(' ', '%20', $full_path);
            return home_url($full_path);
        }

        // Fallback: use documents_url_base if base_path isn't under web root
        $url_base = $settings->get_setting('documents_url_base');
        if (!empty($url_base)) {
            return $url_base . rawurlencode($relative_path);
        }

        return '#';
    }

    /**
     * Get files from annexes folder
     */
    private function get_annex_files($annexes_path, $base_relative_path) {
        $annexes = array();

        if (!is_dir($annexes_path)) {
            return $annexes;
        }

        $files = scandir($annexes_path);
        if ($files === false) {
            return $annexes;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $file_path = $annexes_path . '/' . $file;
            if (is_file($file_path)) {
                $relative_path = $base_relative_path . '_annexes/' . $file;
                $annexes[] = array(
                    'title'    => pathinfo($file, PATHINFO_FILENAME),
                    'url'      => $this->get_document_url($relative_path),
                    'is_annex' => true,
                );
            }
        }

        // Sort annexes by title
        usort($annexes, function($a, $b) {
            return strnatcasecmp($a['title'], $b['title']);
        });

        return $annexes;
    }

    /**
     * Get auto-linked documents for an event
     */
    public function get_auto_documents($post_id) {
        $result = array(
            'documents'      => array(),
            'status_message' => '',
        );

        $preset_name = get_post_meta($post_id, '_parish_event_auto_document_preset', true);
        if (empty($preset_name)) {
            return $result;
        }

        $settings = $this->get_settings();
        $preset = $settings->get_preset_by_name($preset_name);
        if (!$preset) {
            return $result;
        }

        $event_date = get_post_meta($post_id, '_parish_event_date', true);
        if (empty($event_date)) {
            return $result;
        }

        // Resolve the file pattern
        $resolved_pattern = $this->resolve_file_pattern($preset['file_pattern'], $event_date);
        $relative_path = $preset['base_path'] . '/' . $resolved_pattern;

        // Build full filesystem path
        $base_path = $settings->get_setting('nextcloud_base_path');
        $full_path = rtrim($base_path, '/') . '/' . $relative_path;

        // Check if file exists
        if (file_exists($full_path)) {
            // Add main document
            $result['documents'][] = array(
                'title' => pathinfo($resolved_pattern, PATHINFO_FILENAME),
                'url'   => $this->get_document_url($relative_path),
            );

            // Check for annexes folder
            $annexes_path = $full_path . '_annexes';
            $annexes = $this->get_annex_files($annexes_path, $relative_path);
            $result['documents'] = array_merge($result['documents'], $annexes);
        } else {
            // File doesn't exist - show appropriate status message
            $today = date('Y-m-d');
            if ($event_date >= $today) {
                $result['status_message'] = $settings->get_setting('message_future');
            } else {
                $result['status_message'] = $settings->get_setting('message_past');
            }
        }

        return $result;
    }

    public function init() {
        // Register CPT
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_rewrite_rules'));

        // Use classic editor for events
        add_filter('use_block_editor_for_post_type', array($this, 'disable_block_editor'), 10, 2);

        // Meta boxes
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_parish_event', array($this, 'save_meta'), 10, 2);

        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Admin columns
        add_filter('manage_parish_event_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_parish_event_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
        add_filter('manage_edit-parish_event_sortable_columns', array($this, 'sortable_columns'));
        add_action('pre_get_posts', array($this, 'sort_by_event_date'));
        add_action('restrict_manage_posts', array($this, 'add_event_filter_dropdown'));

        // Shortcodes
        add_shortcode('parish_events', array($this, 'render_events_shortcode'));
        add_shortcode('parish_events_calendar', array($this, 'render_calendar_shortcode'));
        add_shortcode('next_parish_event', array($this, 'render_next_event_shortcode'));

        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Single event template
        add_filter('single_template', array($this, 'load_single_template'));

        // Archive template
        add_filter('archive_template', array($this, 'load_archive_template'));

        // iCal feed
        $this->ical->init();

        // AJAX for calendar
        add_action('wp_ajax_parish_events_get_month', array($this, 'ajax_get_month'));
        add_action('wp_ajax_nopriv_parish_events_get_month', array($this, 'ajax_get_month'));

        // AJAX for pagination
        add_action('wp_ajax_parish_events_paginate', array($this, 'ajax_paginate'));
        add_action('wp_ajax_nopriv_parish_events_paginate', array($this, 'ajax_paginate'));

        // AJAX for auto-document preview
        add_action('wp_ajax_parish_events_check_auto_doc', array($this, 'ajax_check_auto_doc'));

        // Meilisearch hooks
        add_action('save_post_parish_event', array($this->meilisearch, 'index_event'), 20, 2);
        add_action('before_delete_post', array($this->meilisearch, 'delete_event'));
        add_action('trashed_post', array($this->meilisearch, 'delete_event'));
    }

    /**
     * Register the Event custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => 'Events',
            'singular_name'      => 'Event',
            'menu_name'          => 'Parish Events',
            'add_new'            => 'Add New Event',
            'add_new_item'       => 'Add New Event',
            'edit_item'          => 'Edit Event',
            'new_item'           => 'New Event',
            'view_item'          => 'View Event',
            'search_items'       => 'Search Events',
            'not_found'          => 'No events found',
            'not_found_in_trash' => 'No events found in trash',
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'events'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 2.1,  // Just below Dashboard, alphabetical
            'menu_icon'           => 'dashicons-calendar-alt',
            'supports'            => array('title', 'editor', 'thumbnail', 'revisions'),
            'show_in_rest'        => true,
        );

        register_post_type('parish_event', $args);

        // Register meta fields for REST API
        $meta_fields = array(
            '_parish_event_date',
            '_parish_event_time',
            '_parish_event_end_date',
            '_parish_event_end_time',
            '_parish_event_location',
            '_parish_event_url',
            '_parish_event_series_id',
            '_parish_event_cancelled',
        );

        foreach ($meta_fields as $meta_key) {
            register_post_meta('parish_event', $meta_key, array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
            ));
        }
    }

    /**
     * Register rewrite rules for iCal feed
     */
    public function register_rewrite_rules() {
        add_rewrite_rule('^events/feed\.ics$', 'index.php?parish_events_ical=1', 'top');
        add_rewrite_tag('%parish_events_ical%', '1');
    }

    /**
     * Disable block editor for events (use classic editor)
     */
    public function disable_block_editor($use_block_editor, $post_type) {
        if ($post_type === 'parish_event') {
            return false;
        }
        return $use_block_editor;
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'parish_event_details',
            'Event Details',
            array($this, 'render_details_meta_box'),
            'parish_event',
            'normal',
            'high'
        );

        add_meta_box(
            'parish_event_documents',
            'Related Documents',
            array($this, 'render_documents_meta_box'),
            'parish_event',
            'normal',
            'default'
        );

    }

    /**
     * Render event details meta box
     */
    public function render_details_meta_box($post) {
        wp_nonce_field('parish_event_meta', 'parish_event_meta_nonce');

        $event_date = get_post_meta($post->ID, '_parish_event_date', true);
        $event_time = get_post_meta($post->ID, '_parish_event_time', true);
        $event_end_date = get_post_meta($post->ID, '_parish_event_end_date', true);
        $event_end_time = get_post_meta($post->ID, '_parish_event_end_time', true);
        $event_location = get_post_meta($post->ID, '_parish_event_location', true);
        $event_url = get_post_meta($post->ID, '_parish_event_url', true);
        $series_id = get_post_meta($post->ID, '_parish_event_series_id', true);
        $is_cancelled = get_post_meta($post->ID, '_parish_event_cancelled', true);

        // Only show recurrence fields for new events (not part of a series)
        $show_recurrence = empty($series_id);
        $recurrence_rule = get_post_meta($post->ID, '_parish_event_recurrence', true);
        $recurrence_end = get_post_meta($post->ID, '_parish_event_recurrence_end', true) ?: date('Y-m-d', strtotime('+12 months'));
        $recurrence_interval = get_post_meta($post->ID, '_parish_event_recurrence_interval', true) ?: 1;
        $recurrence_ordinal = get_post_meta($post->ID, '_parish_event_recurrence_ordinal', true) ?: 'first';
        $recurrence_weekday = get_post_meta($post->ID, '_parish_event_recurrence_weekday', true) ?: 'monday';
        ?>
        <table class="form-table">
            <tr>
                <th><label for="parish_event_cancelled">Cancelled</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="parish_event_cancelled" name="parish_event_cancelled" value="1" <?php checked($is_cancelled, '1'); ?>>
                        Mark this event as cancelled
                    </label>
                    <p class="description">Cancelled events remain visible but show as struck through with "Cancelled:" prefix</p>
                </td>
            </tr>
            <tr>
                <th><label for="parish_event_date">Event Date <span class="required">*</span></label></th>
                <td>
                    <input type="date" id="parish_event_date" name="parish_event_date"
                           value="<?php echo esc_attr($event_date); ?>" required style="width: 200px;">
                </td>
            </tr>
            <tr>
                <th><label for="parish_event_time">Start Time</label></th>
                <td>
                    <input type="time" id="parish_event_time" name="parish_event_time"
                           value="<?php echo esc_attr($event_time); ?>" style="width: 150px;">
                </td>
            </tr>
            <tr>
                <th><label for="parish_event_end_date">End Date</label></th>
                <td>
                    <input type="date" id="parish_event_end_date" name="parish_event_end_date"
                           value="<?php echo esc_attr($event_end_date); ?>" style="width: 200px;">
                    <p class="description">Leave blank for single-day events</p>
                </td>
            </tr>
            <tr>
                <th><label for="parish_event_end_time">End Time</label></th>
                <td>
                    <input type="time" id="parish_event_end_time" name="parish_event_end_time"
                           value="<?php echo esc_attr($event_end_time); ?>" style="width: 150px;">
                </td>
            </tr>
            <?php if ($show_recurrence): ?>
            <tr>
                <th><label for="parish_event_recurrence">Repeat</label></th>
                <td>
                    <select id="parish_event_recurrence" name="parish_event_recurrence" style="width: 200px;">
                        <option value="" <?php selected($recurrence_rule, ''); ?>>Does not repeat</option>
                        <option value="weekly" <?php selected($recurrence_rule, 'weekly'); ?>>Weekly</option>
                        <option value="monthly" <?php selected($recurrence_rule, 'monthly'); ?>>Monthly (same date)</option>
                        <option value="monthly_ordinal" <?php selected($recurrence_rule, 'monthly_ordinal'); ?>>Monthly (e.g. first Monday)</option>
                        <option value="yearly" <?php selected($recurrence_rule, 'yearly'); ?>>Yearly</option>
                    </select>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <th>Series</th>
                <td><em>This event is part of a series.</em></td>
            </tr>
            <?php endif; ?>
            <tr id="parish-event-interval-row" style="<?php echo ($show_recurrence && $recurrence_rule === 'weekly') ? '' : 'display:none;'; ?>">
                <th><label for="parish_event_recurrence_interval">Every</label></th>
                <td>
                    <select id="parish_event_recurrence_interval" name="parish_event_recurrence_interval" style="width: 200px;">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php selected($recurrence_interval, $i); ?>>
                                <?php echo $i === 1 ? 'Every week' : "Every $i weeks"; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </td>
            </tr>
            <tr id="parish-event-ordinal-row" style="<?php echo ($show_recurrence && $recurrence_rule === 'monthly_ordinal') ? '' : 'display:none;'; ?>">
                <th><label>On the</label></th>
                <td>
                    <select id="parish_event_recurrence_ordinal" name="parish_event_recurrence_ordinal" style="width: 100px;">
                        <option value="first" <?php selected($recurrence_ordinal, 'first'); ?>>First</option>
                        <option value="second" <?php selected($recurrence_ordinal, 'second'); ?>>Second</option>
                        <option value="third" <?php selected($recurrence_ordinal, 'third'); ?>>Third</option>
                        <option value="fourth" <?php selected($recurrence_ordinal, 'fourth'); ?>>Fourth</option>
                        <option value="last" <?php selected($recurrence_ordinal, 'last'); ?>>Last</option>
                    </select>
                    <select id="parish_event_recurrence_weekday" name="parish_event_recurrence_weekday" style="width: 120px;">
                        <option value="monday" <?php selected($recurrence_weekday, 'monday'); ?>>Monday</option>
                        <option value="tuesday" <?php selected($recurrence_weekday, 'tuesday'); ?>>Tuesday</option>
                        <option value="wednesday" <?php selected($recurrence_weekday, 'wednesday'); ?>>Wednesday</option>
                        <option value="thursday" <?php selected($recurrence_weekday, 'thursday'); ?>>Thursday</option>
                        <option value="friday" <?php selected($recurrence_weekday, 'friday'); ?>>Friday</option>
                        <option value="saturday" <?php selected($recurrence_weekday, 'saturday'); ?>>Saturday</option>
                        <option value="sunday" <?php selected($recurrence_weekday, 'sunday'); ?>>Sunday</option>
                    </select>
                </td>
            </tr>
            <tr id="parish-event-recurrence-end-row" style="<?php echo (!$show_recurrence || empty($recurrence_rule)) ? 'display:none;' : ''; ?>">
                <th><label for="parish_event_recurrence_end">Repeat Until</label></th>
                <td>
                    <input type="date" id="parish_event_recurrence_end" name="parish_event_recurrence_end"
                           value="<?php echo esc_attr($recurrence_end); ?>" style="width: 200px;">
                </td>
            </tr>
            <tr>
                <th><label for="parish_event_location">Location</label></th>
                <td>
                    <input type="text" id="parish_event_location" name="parish_event_location"
                           value="<?php echo esc_attr($event_location); ?>" class="regular-text"
                           placeholder="e.g., Albury Village Hall">
                </td>
            </tr>
            <tr>
                <th><label for="parish_event_url">More Info URL</label></th>
                <td>
                    <input type="url" id="parish_event_url" name="parish_event_url"
                           value="<?php echo esc_url($event_url); ?>" class="regular-text"
                           placeholder="https://...">
                    <p class="description">Optional link to external event page or tickets</p>
                </td>
            </tr>
        </table>
        <script>
        jQuery(document).ready(function($) {
            $('#parish_event_recurrence').on('change', function() {
                var val = $(this).val();
                $('#parish-event-recurrence-end-row').toggle(val !== '');
                $('#parish-event-interval-row').toggle(val === 'weekly');
                $('#parish-event-ordinal-row').toggle(val === 'monthly_ordinal');
            });
        });
        </script>
        <?php
    }

    /**
     * Render documents meta box (repeatable field)
     */
    public function render_documents_meta_box($post) {
        $documents = get_post_meta($post->ID, '_parish_event_documents', true);
        if (!is_array($documents)) {
            $documents = array();
        }

        // Get current preset and available presets
        $current_preset = get_post_meta($post->ID, '_parish_event_auto_document_preset', true);
        $settings = $this->get_settings();
        $presets = $settings ? $settings->get_presets() : array();

        // Get event date for preview
        $event_date = get_post_meta($post->ID, '_parish_event_date', true);
        ?>
        <div id="parish-event-documents">
            <!-- Auto-link Documents Section -->
            <div style="margin-bottom: 20px; padding: 15px; background: #f0f6fc; border: 1px solid #c3d9ed; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; font-size: 14px;">Auto-link Documents</h4>
                <?php if (!empty($presets)): ?>
                <p class="description" style="margin-bottom: 10px;">Automatically link to documents in Nextcloud based on event date.</p>

                <label for="parish_event_auto_document_preset" style="display: block; margin-bottom: 5px;"><strong>Document Preset:</strong></label>
                <select id="parish_event_auto_document_preset" name="parish_event_auto_document_preset" style="width: 300px;">
                    <option value="">None (manual documents only)</option>
                    <?php foreach ($presets as $preset): ?>
                        <option value="<?php echo esc_attr($preset['name']); ?>" <?php selected($current_preset, $preset['name']); ?>>
                            <?php echo esc_html($preset['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="parish-event-auto-doc-preview" style="margin-top: 10px; font-size: 13px; color: #666;">
                    <?php if ($current_preset && $event_date): ?>
                        <?php
                        $preset_data = $settings->get_preset_by_name($current_preset);
                        if ($preset_data):
                            $resolved = $this->resolve_file_pattern($preset_data['file_pattern'], $event_date);
                            $relative_path = $preset_data['base_path'] . '/' . $resolved;
                            $base_path = $settings->get_setting('nextcloud_base_path');
                            $full_path = rtrim($base_path, '/') . '/' . $relative_path;
                            $exists = file_exists($full_path);
                        ?>
                        <strong>Resolved path:</strong> <?php echo esc_html($relative_path); ?><br>
                        <strong>Status:</strong>
                        <?php if ($exists): ?>
                            <span style="color: #46b450;">File found</span>
                        <?php else: ?>
                            <span style="color: #dc3232;">File not found</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    <?php elseif ($current_preset && !$event_date): ?>
                        <em>Set an event date to see the resolved path.</em>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="description">
                    No document presets configured. <a href="<?php echo admin_url('options-general.php?page=parish-events-settings'); ?>">Configure presets</a> in Settings &gt; Parish Events.
                </p>
                <?php endif; ?>
            </div>

            <!-- Manual Documents Section -->
            <h4 style="margin: 0 0 10px 0; font-size: 14px;">Manual Documents</h4>
            <p class="description">Add links to additional documents (agenda, minutes, reports, etc.)</p>
            <div id="parish-event-documents-list">
                <?php if (!empty($documents)): ?>
                    <?php foreach ($documents as $index => $doc): ?>
                        <div class="parish-event-document-row" style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                            <input type="text" name="parish_event_documents[<?php echo $index; ?>][title]"
                                   value="<?php echo esc_attr($doc['title']); ?>"
                                   placeholder="Document title" style="width: 200px;">
                            <input type="url" name="parish_event_documents[<?php echo $index; ?>][url]"
                                   value="<?php echo esc_url($doc['url']); ?>"
                                   placeholder="https://..." style="width: 350px;">
                            <button type="button" class="button parish-event-remove-doc">Remove</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button" id="parish-event-add-doc">+ Add Document</button>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var docIndex = <?php echo count($documents); ?>;

            $('#parish-event-add-doc').on('click', function() {
                var row = '<div class="parish-event-document-row" style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">' +
                    '<input type="text" name="parish_event_documents[' + docIndex + '][title]" placeholder="Document title" style="width: 200px;"> ' +
                    '<input type="url" name="parish_event_documents[' + docIndex + '][url]" placeholder="https://..." style="width: 350px;"> ' +
                    '<button type="button" class="button parish-event-remove-doc">Remove</button>' +
                    '</div>';
                $('#parish-event-documents-list').append(row);
                docIndex++;
            });

            $(document).on('click', '.parish-event-remove-doc', function() {
                $(this).closest('.parish-event-document-row').remove();
            });

            // Update preview when preset or date changes
            function updateAutoDocPreview() {
                var preset = $('#parish_event_auto_document_preset').val();
                var date = $('#parish_event_date').val();
                var $preview = $('#parish-event-auto-doc-preview');

                if (!preset) {
                    $preview.html('');
                    return;
                }

                if (!date) {
                    $preview.html('<em>Set an event date to see the resolved path.</em>');
                    return;
                }

                // Show loading state
                $preview.html('<em>Checking...</em>');

                // Make AJAX call to check file status
                $.post(ajaxurl, {
                    action: 'parish_events_check_auto_doc',
                    post_id: <?php echo $post->ID; ?>,
                    preset: preset,
                    date: date,
                    nonce: '<?php echo wp_create_nonce('parish_events_check_auto_doc'); ?>'
                }, function(response) {
                    if (response.success) {
                        var html = '<strong>Resolved path:</strong> ' + response.data.path + '<br>';
                        if (response.data.exists) {
                            html += '<strong>Status:</strong> <span style="color: #46b450;">File found</span>';
                            if (response.data.annexes_count > 0) {
                                html += ' (' + response.data.annexes_count + ' annexes)';
                            }
                        } else {
                            html += '<strong>Status:</strong> <span style="color: #dc3232;">File not found</span>';
                        }
                        $preview.html(html);
                    }
                });
            }

            $('#parish_event_auto_document_preset, #parish_event_date').on('change', updateAutoDocPreview);
        });
        </script>
        <?php
    }

    /**
     * Save event meta data
     */
    public function save_meta($post_id, $post) {
        if (!isset($_POST['parish_event_meta_nonce']) ||
            !wp_verify_nonce($_POST['parish_event_meta_nonce'], 'parish_event_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Event details
        $fields = array(
            'parish_event_date'       => 'sanitize_text_field',
            'parish_event_time'       => 'sanitize_text_field',
            'parish_event_end_date'   => 'sanitize_text_field',
            'parish_event_end_time'   => 'sanitize_text_field',
            'parish_event_location'   => 'sanitize_text_field',
            'parish_event_url'        => 'esc_url_raw',
            'parish_event_recurrence' => 'sanitize_text_field',
            'parish_event_recurrence_end' => 'sanitize_text_field',
            'parish_event_recurrence_interval' => 'absint',
            'parish_event_recurrence_ordinal' => 'sanitize_text_field',
            'parish_event_recurrence_weekday' => 'sanitize_text_field',
        );

        foreach ($fields as $field => $sanitize_func) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, $sanitize_func($_POST[$field]));
            }
        }

        // Cancelled checkbox (handles unchecked state)
        if (isset($_POST['parish_event_cancelled']) && $_POST['parish_event_cancelled'] === '1') {
            update_post_meta($post_id, '_parish_event_cancelled', '1');
        } else {
            delete_post_meta($post_id, '_parish_event_cancelled');
        }

        // Documents (repeatable)
        if (isset($_POST['parish_event_documents']) && is_array($_POST['parish_event_documents'])) {
            $documents = array();
            foreach ($_POST['parish_event_documents'] as $doc) {
                if (!empty($doc['title']) && !empty($doc['url'])) {
                    $documents[] = array(
                        'title' => sanitize_text_field($doc['title']),
                        'url'   => esc_url_raw($doc['url']),
                    );
                }
            }
            update_post_meta($post_id, '_parish_event_documents', $documents);
        } else {
            delete_post_meta($post_id, '_parish_event_documents');
        }

        // Auto-document preset
        if (isset($_POST['parish_event_auto_document_preset'])) {
            $preset = sanitize_text_field($_POST['parish_event_auto_document_preset']);
            if (!empty($preset)) {
                update_post_meta($post_id, '_parish_event_auto_document_preset', $preset);
            } else {
                delete_post_meta($post_id, '_parish_event_auto_document_preset');
            }
        }

        // Create series if recurrence is set and this isn't already part of a series
        $this->maybe_create_series($post_id, $post);
    }

    /**
     * Create individual event posts for a recurring series
     */
    private function maybe_create_series($post_id, $post) {
        // Skip if already part of a series
        if (get_post_meta($post_id, '_parish_event_series_id', true)) {
            return;
        }

        $recurrence = get_post_meta($post_id, '_parish_event_recurrence', true);
        if (empty($recurrence)) {
            return;
        }

        $event_date = get_post_meta($post_id, '_parish_event_date', true);
        $recurrence_end = get_post_meta($post_id, '_parish_event_recurrence_end', true);

        if (empty($event_date)) {
            return;
        }

        // Build options for recurrence calculation
        $options = array(
            'interval' => get_post_meta($post_id, '_parish_event_recurrence_interval', true) ?: 1,
            'ordinal'  => get_post_meta($post_id, '_parish_event_recurrence_ordinal', true),
            'weekday'  => get_post_meta($post_id, '_parish_event_recurrence_weekday', true),
        );

        // Generate occurrence dates
        $dates = $this->recurrence->get_occurrence_dates($event_date, $recurrence, $recurrence_end, $options);

        // Need at least 2 dates to create a series
        if (count($dates) < 2) {
            return;
        }

        // Create series ID and mark parent
        $series_id = 'series_' . $post_id . '_' . time();
        update_post_meta($post_id, '_parish_event_series_id', $series_id);

        // Clear recurrence fields on parent (series is now created)
        delete_post_meta($post_id, '_parish_event_recurrence');
        delete_post_meta($post_id, '_parish_event_recurrence_end');
        delete_post_meta($post_id, '_parish_event_recurrence_interval');
        delete_post_meta($post_id, '_parish_event_recurrence_ordinal');
        delete_post_meta($post_id, '_parish_event_recurrence_weekday');

        // Get fields to copy to child events
        $event_time = get_post_meta($post_id, '_parish_event_time', true);
        $event_end_time = get_post_meta($post_id, '_parish_event_end_time', true);
        $event_location = get_post_meta($post_id, '_parish_event_location', true);
        $event_url = get_post_meta($post_id, '_parish_event_url', true);
        $documents = get_post_meta($post_id, '_parish_event_documents', true);
        $auto_doc_preset = get_post_meta($post_id, '_parish_event_auto_document_preset', true);

        // Calculate event duration for multi-day events
        $event_end_date = get_post_meta($post_id, '_parish_event_end_date', true);
        $duration_days = 0;
        if (!empty($event_end_date) && $event_end_date !== $event_date) {
            $start = new DateTime($event_date);
            $end = new DateTime($event_end_date);
            $duration_days = (int) $start->diff($end)->days;
        }

        // Remove save_meta hook to prevent $_POST overwriting child dates
        remove_action('save_post_parish_event', array($this, 'save_meta'), 10);

        // Create child posts for each occurrence (skip first - that's the parent)
        foreach (array_slice($dates, 1) as $date) {
            $child_id = wp_insert_post(array(
                'post_type'    => 'parish_event',
                'post_status'  => 'publish',
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
            ));

            if ($child_id && !is_wp_error($child_id)) {
                update_post_meta($child_id, '_parish_event_series_id', $series_id);
                update_post_meta($child_id, '_parish_event_date', $date);
                update_post_meta($child_id, '_parish_event_time', $event_time);
                update_post_meta($child_id, '_parish_event_end_time', $event_end_time);
                update_post_meta($child_id, '_parish_event_location', $event_location);
                update_post_meta($child_id, '_parish_event_url', $event_url);

                // Calculate end date if multi-day
                if ($duration_days > 0) {
                    $child_end = new DateTime($date);
                    $child_end->modify("+{$duration_days} days");
                    update_post_meta($child_id, '_parish_event_end_date', $child_end->format('Y-m-d'));
                }

                if (!empty($documents)) {
                    update_post_meta($child_id, '_parish_event_documents', $documents);
                }

                if (!empty($auto_doc_preset)) {
                    update_post_meta($child_id, '_parish_event_auto_document_preset', $auto_doc_preset);
                }
            }
        }

        // Re-add save_meta hook
        add_action('save_post_parish_event', array($this, 'save_meta'), 10, 2);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        if ($post_type === 'parish_event') {
            wp_enqueue_script('jquery');

            // Add admin styles for event edit screen
            if ($hook === 'post.php' || $hook === 'post-new.php') {
                wp_add_inline_style('wp-admin', '
                    /* Compact layout for classic editor */
                    .post-type-parish_event #post-body-content {
                        margin-bottom: 10px;
                    }
                    .post-type-parish_event #postdivrich {
                        margin-bottom: 10px;
                    }
                    .post-type-parish_event #content_ifr {
                        height: 150px !important;
                    }
                    .post-type-parish_event #wp-content-editor-container .wp-editor-area {
                        height: 150px !important;
                    }
                    /* Reduce title input height */
                    .post-type-parish_event #titlewrap #title {
                        padding: 3px 8px;
                        font-size: 1.4em;
                        height: auto;
                    }
                    /* Compact the Event Details meta box */
                    .post-type-parish_event #parish_event_details .form-table {
                        max-width: 600px;
                    }
                    .post-type-parish_event #parish_event_details .form-table th {
                        width: 120px;
                        padding: 8px 10px 8px 0;
                    }
                    .post-type-parish_event #parish_event_details .form-table td {
                        padding: 8px 0;
                    }
                    .post-type-parish_event #parish_event_details .form-table .description {
                        margin-top: 4px;
                    }
                ');
            }
        }
    }

    /**
     * Add custom admin columns
     */
    public function add_admin_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['event_date'] = 'Event Date';
                $new_columns['event_location'] = 'Location';
                $new_columns['event_status'] = 'Status';
                $new_columns['series'] = 'Series';
            }
        }
        unset($new_columns['date']); // Remove default date column
        return $new_columns;
    }

    /**
     * Render custom admin columns
     */
    public function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'event_date':
                $date = get_post_meta($post_id, '_parish_event_date', true);
                $time = get_post_meta($post_id, '_parish_event_time', true);
                if ($date) {
                    echo esc_html(date('j M Y', strtotime($date)));
                    if ($time) {
                        echo ' at ' . esc_html(date('g:ia', strtotime($time)));
                    }
                }
                break;
            case 'event_location':
                echo esc_html(get_post_meta($post_id, '_parish_event_location', true));
                break;
            case 'event_status':
                $is_cancelled = get_post_meta($post_id, '_parish_event_cancelled', true);
                if ($is_cancelled) {
                    echo '<span style="background: #dc3232; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">Cancelled</span>';
                } else {
                    echo '<span style="color: #999;">-</span>';
                }
                break;
            case 'series':
                $series_id = get_post_meta($post_id, '_parish_event_series_id', true);
                if ($series_id) {
                    echo '<span style="background: #e0e0e0; padding: 2px 6px; border-radius: 3px; font-size: 11px;">Series</span>';
                } else {
                    echo '<span style="color: #999;">-</span>';
                }
                break;
        }
    }

    /**
     * Make event_date column sortable
     */
    public function sortable_columns($columns) {
        $columns['event_date'] = 'event_date';
        return $columns;
    }

    /**
     * Add event filter dropdown to admin list
     */
    public function add_event_filter_dropdown($post_type) {
        if ($post_type !== 'parish_event') {
            return;
        }

        $current = isset($_GET['event_filter']) ? sanitize_text_field($_GET['event_filter']) : 'upcoming';
        ?>
        <select name="event_filter">
            <option value="upcoming" <?php selected($current, 'upcoming'); ?>>Upcoming Events</option>
            <option value="past" <?php selected($current, 'past'); ?>>Past Events</option>
            <option value="all" <?php selected($current, 'all'); ?>>All Events</option>
        </select>
        <?php
    }

    /**
     * Sort by event date in admin - show upcoming events first by default
     */
    public function sort_by_event_date($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'parish_event') {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'event_date' || empty($orderby)) {
            $query->set('meta_key', '_parish_event_date');
            $query->set('orderby', 'meta_value');

            // Default: upcoming events first, sorted ascending (soonest first)
            if (empty($query->get('order'))) {
                $query->set('order', 'ASC');
            }

            // Filter to upcoming events by default (unless user explicitly filters)
            $event_filter = isset($_GET['event_filter']) ? sanitize_text_field($_GET['event_filter']) : 'upcoming';

            if ($event_filter === 'upcoming') {
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'key'     => '_parish_event_date',
                    'value'   => date('Y-m-d'),
                    'compare' => '>=',
                    'type'    => 'DATE',
                );
                $query->set('meta_query', $meta_query);
            } elseif ($event_filter === 'past') {
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'key'     => '_parish_event_date',
                    'value'   => date('Y-m-d'),
                    'compare' => '<',
                    'type'    => 'DATE',
                );
                $query->set('meta_query', $meta_query);
                $query->set('order', 'DESC'); // Past events: most recent first
            }
        }
    }

    /**
     * Get events with optional filters
     */
    public function get_events($args = array()) {
        $defaults = array(
            'limit'      => 10,
            'show'       => 'upcoming',  // 'upcoming', 'past', 'all'
            'start_date' => null,
            'end_date'   => null,
            'offset'     => 0,
        );
        $args = wp_parse_args($args, $defaults);

        $today = date('Y-m-d');

        $query_args = array(
            'post_type'      => 'parish_event',
            'post_status'    => 'publish',
            'posts_per_page' => $args['limit'] > 0 ? $args['limit'] : -1,
            'offset'         => $args['offset'],
            'meta_key'       => '_parish_event_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        );

        // Filter by date range
        if ($args['show'] === 'upcoming') {
            $query_args['meta_query'] = array(
                array(
                    'key'     => '_parish_event_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            );
        } elseif ($args['show'] === 'past') {
            $query_args['meta_query'] = array(
                array(
                    'key'     => '_parish_event_date',
                    'value'   => $today,
                    'compare' => '<',
                    'type'    => 'DATE',
                ),
            );
            $query_args['order'] = 'DESC';
        }

        $events_query = new WP_Query($query_args);
        $events = array();

        if ($events_query->have_posts()) {
            while ($events_query->have_posts()) {
                $events_query->the_post();
                $post_id = get_the_ID();

                // Get manual documents
                $manual_docs = get_post_meta($post_id, '_parish_event_documents', true) ?: array();

                // Get auto-linked documents
                $auto_result = $this->get_auto_documents($post_id);

                // Merge auto-documents first, then manual documents
                $all_documents = array_merge($auto_result['documents'], $manual_docs);

                $events[] = array(
                    'id'              => $post_id,
                    'title'           => get_the_title(),
                    'content'         => get_the_content(),
                    'excerpt'         => get_the_excerpt(),
                    'permalink'       => get_permalink(),
                    'date'            => get_post_meta($post_id, '_parish_event_date', true),
                    'time'            => get_post_meta($post_id, '_parish_event_time', true),
                    'end_date'        => get_post_meta($post_id, '_parish_event_end_date', true),
                    'end_time'        => get_post_meta($post_id, '_parish_event_end_time', true),
                    'location'        => get_post_meta($post_id, '_parish_event_location', true),
                    'url'             => get_post_meta($post_id, '_parish_event_url', true),
                    'documents'       => $all_documents,
                    'auto_doc_status' => $auto_result['status_message'],
                    'series_id'       => get_post_meta($post_id, '_parish_event_series_id', true),
                    'cancelled'       => (bool) get_post_meta($post_id, '_parish_event_cancelled', true),
                );
            }
        }
        wp_reset_postdata();

        // Sort by date and time (database only sorts by date)
        $sort_order = ($args['show'] === 'past') ? -1 : 1;
        usort($events, function($a, $b) use ($sort_order) {
            $date_compare = strcmp($a['date'], $b['date']);
            if ($date_compare !== 0) {
                return $date_compare * $sort_order;
            }
            // Same date - sort by time (empty time treated as 00:00)
            $time_a = $a['time'] ?: '00:00';
            $time_b = $b['time'] ?: '00:00';
            return strcmp($time_a, $time_b) * $sort_order;
        });

        return $events;
    }

    /**
     * Get total count of events
     */
    public function get_total_events($show = 'upcoming') {
        $today = date('Y-m-d');

        $query_args = array(
            'post_type'      => 'parish_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        if ($show === 'upcoming') {
            $query_args['meta_query'] = array(
                array(
                    'key'     => '_parish_event_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            );
        } elseif ($show === 'past') {
            $query_args['meta_query'] = array(
                array(
                    'key'     => '_parish_event_date',
                    'value'   => $today,
                    'compare' => '<',
                    'type'    => 'DATE',
                ),
            );
        }

        $query = new WP_Query($query_args);
        return $query->found_posts;
    }

    /**
     * Render a single event item for the list
     */
    public function render_event_item($event) {
        $is_cancelled = !empty($event['cancelled']);
        $item_class = 'parish-event-item' . ($is_cancelled ? ' parish-event-cancelled' : '');
        $display_title = $is_cancelled ? 'Cancelled: ' . $event['title'] : $event['title'];

        ob_start();
        ?>
        <article class="<?php echo esc_attr($item_class); ?>">
            <div class="parish-event-date-badge">
                <span class="parish-event-day"><?php echo date('d', strtotime($event['date'])); ?></span>
                <span class="parish-event-month"><?php echo date('M', strtotime($event['date'])); ?></span>
                <span class="parish-event-year"><?php echo date('Y', strtotime($event['date'])); ?></span>
            </div>
            <div class="parish-event-content">
                <h3 class="parish-event-title">
                    <a href="<?php echo esc_url($event['permalink']); ?>"><?php echo esc_html($display_title); ?></a>
                </h3>
                <div class="parish-event-meta">
                    <?php if (!empty($event['time'])): ?>
                        <span class="parish-event-time">
                            <svg class="parish-event-icon" viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 6v6l4 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <?php echo esc_html(date('g:ia', strtotime($event['time']))); ?><?php if (!empty($event['end_time'])): ?> – <?php echo esc_html(date('g:ia', strtotime($event['end_time']))); ?><?php endif; ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($event['location'])): ?>
                        <span class="parish-event-location">
                            <svg class="parish-event-icon" viewBox="0 0 24 24" width="16" height="16"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="9" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                            <?php echo esc_html($event['location']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($event['excerpt'])): ?>
                    <div class="parish-event-excerpt"><?php echo wp_kses_post($event['excerpt']); ?></div>
                <?php endif; ?>
                <?php if (!empty($event['documents'])): ?>
                    <div class="parish-event-documents">
                        <strong>Documents:</strong>
                        <?php foreach ($event['documents'] as $doc): ?>
                            <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($doc['title']); ?><?php if (!empty($doc['is_annex'])): ?> <span class="parish-event-annex-badge">Annex</span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (!empty($event['auto_doc_status'])): ?>
                    <div class="parish-event-documents parish-event-doc-status-inline">
                        <em><?php echo esc_html($event['auto_doc_status']); ?></em>
                    </div>
                <?php endif; ?>
                <?php if (!empty($event['url'])): ?>
                    <a href="<?php echo esc_url($event['url']); ?>" class="parish-event-link" target="_blank" rel="noopener">
                        More info &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for event pagination
     */
    public function ajax_paginate() {
        check_ajax_referer('parish_events_paginate', 'nonce');

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        $show = isset($_POST['show']) ? sanitize_text_field($_POST['show']) : 'upcoming';

        // Handle "all" option
        if ($per_page <= 0) {
            $per_page = -1;
        }

        $offset = $per_page > 0 ? ($page - 1) * $per_page : 0;

        $events = $this->get_events(array(
            'limit'  => $per_page,
            'show'   => $show,
            'offset' => $offset,
        ));

        $total_events = $this->get_total_events($show);

        $html = '';
        if (!empty($events)) {
            foreach ($events as $event) {
                $html .= $this->render_event_item($event);
            }
        } else {
            $html = '<p class="parish-events-no-results">' .
                    ($show === 'upcoming' ? 'No upcoming events.' : 'No events found.') .
                    '</p>';
        }

        wp_send_json_success(array(
            'html'     => $html,
            'total'    => $total_events,
            'page'     => $page,
            'per_page' => $per_page > 0 ? $per_page : $total_events,
        ));
    }

    /**
     * Render events list shortcode
     */
    public function render_events_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit'    => 10,
            'show'     => 'upcoming',
            'per_page' => 0,
        ), $atts);

        $per_page = intval($atts['per_page']);
        $show = $atts['show'];

        // Get total events for pagination
        $total_events = $this->get_total_events($show);
        $use_pagination = ($per_page > 0 && $total_events > $per_page);

        // For initial load, use per_page if pagination enabled, otherwise limit
        $initial_limit = $use_pagination ? $per_page : intval($atts['limit']);

        $events = $this->get_events(array(
            'limit' => $initial_limit,
            'show'  => $show,
        ));

        // Generate unique ID for this instance
        $instance_id = 'parish-events-' . uniqid();

        ob_start();
        ?>
        <div class="parish-events-container"
             id="<?php echo esc_attr($instance_id); ?>"
             data-per-page="<?php echo esc_attr($per_page); ?>"
             data-total="<?php echo esc_attr($total_events); ?>"
             data-show="<?php echo esc_attr($show); ?>">

            <?php if ($use_pagination): ?>
            <div class="parish-events-controls">
                <div class="parish-events-per-page">
                    <label>Show:</label>
                    <select class="parish-events-per-page-select">
                        <option value="5" <?php selected($per_page, 5); ?>>5</option>
                        <option value="10" <?php selected($per_page, 10); ?>>10</option>
                        <option value="25" <?php selected($per_page, 25); ?>>25</option>
                        <option value="50" <?php selected($per_page, 50); ?>>50</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="parish-events-page-info">
                    Showing <span class="parish-events-showing-start">1</span>–<span class="parish-events-showing-end"><?php echo min($per_page, $total_events); ?></span> of <span class="parish-events-total"><?php echo $total_events; ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="parish-events-list">
                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $event): ?>
                        <?php echo $this->render_event_item($event); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="parish-events-no-results">
                        <?php echo ($show === 'upcoming') ? 'No upcoming events.' : 'No events found.'; ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($use_pagination):
                $total_pages = ceil($total_events / $per_page);
            ?>
            <div class="parish-events-pagination">
                <button class="parish-events-page-btn parish-events-prev" disabled>&laquo; Previous</button>
                <span class="parish-events-page-numbers">
                    <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                        <button class="parish-events-page-num <?php echo $i === 1 ? 'active' : ''; ?>" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
                    <?php endfor; ?>
                </span>
                <button class="parish-events-page-btn parish-events-next" <?php echo $total_pages <= 1 ? 'disabled' : ''; ?>>Next &raquo;</button>
            </div>
            <?php endif; ?>

            <div class="parish-events-subscribe">
                <a href="<?php echo home_url('/events/feed.ics'); ?>" class="parish-events-subscribe-link">
                    <svg viewBox="0 0 24 24" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/></svg>
                    Subscribe to calendar
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render calendar shortcode
     */
    public function render_calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'month' => date('n'),
            'year'  => date('Y'),
        ), $atts);

        $month = intval($atts['month']);
        $year = intval($atts['year']);

        ob_start();
        include PARISH_EVENTS_PLUGIN_DIR . 'templates/events-calendar.php';
        return ob_get_clean();
    }

    /**
     * Render next event shortcode
     *
     * Attributes:
     * - summary_length: Max characters for description (0 = full content, default)
     */
    public function render_next_event_shortcode($atts) {
        $atts = shortcode_atts(array(
            'summary_length' => 0,
        ), $atts, 'next_parish_event');

        $events = $this->get_events(array(
            'limit' => 1,
            'show'  => 'upcoming',
        ));

        if (empty($events)) {
            return '<p class="parish-events-no-upcoming">No upcoming events.</p>';
        }

        $event = $events[0];
        $summary_length = intval($atts['summary_length']);

        ob_start();
        include PARISH_EVENTS_PLUGIN_DIR . 'templates/next-event.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler for calendar month data
     */
    public function ajax_get_month() {
        $month = isset($_POST['month']) ? intval($_POST['month']) : date('n');
        $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');

        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));

        $events = $this->get_events(array(
            'limit'      => -1,
            'show'       => 'all',
            'start_date' => $start_date,
            'end_date'   => $end_date,
        ));

        // Filter to this month only
        $events = array_filter($events, function($event) use ($start_date, $end_date) {
            return $event['date'] >= $start_date && $event['date'] <= $end_date;
        });

        wp_send_json_success(array(
            'month'  => $month,
            'year'   => $year,
            'events' => array_values($events),
        ));
    }

    /**
     * AJAX handler for auto-document preview check
     */
    public function ajax_check_auto_doc() {
        check_ajax_referer('parish_events_check_auto_doc', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $preset_name = isset($_POST['preset']) ? sanitize_text_field($_POST['preset']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if (empty($preset_name) || empty($date)) {
            wp_send_json_error('Missing parameters');
            return;
        }

        $settings = $this->get_settings();
        $preset = $settings->get_preset_by_name($preset_name);

        if (!$preset) {
            wp_send_json_error('Preset not found');
            return;
        }

        // Resolve the file pattern
        $resolved_pattern = $this->resolve_file_pattern($preset['file_pattern'], $date);
        $relative_path = $preset['base_path'] . '/' . $resolved_pattern;

        // Build full filesystem path
        $base_path = $settings->get_setting('nextcloud_base_path');
        $full_path = rtrim($base_path, '/') . '/' . $relative_path;

        // Check if file exists
        $exists = file_exists($full_path);

        // Count annexes if file exists
        $annexes_count = 0;
        if ($exists) {
            $annexes_path = $full_path . '_annexes';
            if (is_dir($annexes_path)) {
                $files = scandir($annexes_path);
                if ($files !== false) {
                    $annexes_count = count(array_filter($files, function($f) use ($annexes_path) {
                        return $f !== '.' && $f !== '..' && is_file($annexes_path . '/' . $f);
                    }));
                }
            }
        }

        wp_send_json_success(array(
            'path'          => $relative_path,
            'exists'        => $exists,
            'annexes_count' => $annexes_count,
        ));
    }

    /**
     * Load custom single event template
     */
    public function load_single_template($template) {
        global $post;

        if ($post->post_type === 'parish_event') {
            $plugin_template = PARISH_EVENTS_PLUGIN_DIR . 'templates/single-parish_event.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Load custom archive template
     */
    public function load_archive_template($template) {
        if (is_post_type_archive('parish_event')) {
            $plugin_template = PARISH_EVENTS_PLUGIN_DIR . 'templates/archive-parish_event.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'parish-events',
            PARISH_EVENTS_PLUGIN_URL . 'assets/css/parish-events.css',
            array(),
            PARISH_EVENTS_VERSION
        );

        wp_enqueue_script(
            'parish-events',
            PARISH_EVENTS_PLUGIN_URL . 'assets/js/parish-events.js',
            array(),
            PARISH_EVENTS_VERSION,
            true
        );

        wp_localize_script('parish-events', 'parishEvents', array(
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('parish_events_nonce'),
            'paginateNonce'  => wp_create_nonce('parish_events_paginate'),
        ));
    }
}
