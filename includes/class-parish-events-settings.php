<?php
/**
 * Parish Events Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Parish_Events_Settings {

    private $option_name = 'parish_events_settings';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_options_page(
            'Parish Events Settings',
            'Parish Events',
            'manage_options',
            'parish-events-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Get default settings
     */
    public function get_defaults() {
        return array(
            'nextcloud_base_path' => '/var/www/html/wp-content/uploads/public-docs',
            'message_future'      => 'Agenda will be published shortly before the meeting',
            'message_past'        => 'Agenda not found. Please contact the clerk.',
            'document_presets'    => array(),
        );
    }

    /**
     * Get settings with defaults
     */
    public function get_settings() {
        $settings = get_option($this->option_name, array());
        return wp_parse_args($settings, $this->get_defaults());
    }

    /**
     * Get a specific setting
     */
    public function get_setting($key) {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : null;
    }

    /**
     * Get document presets
     */
    public function get_presets() {
        $settings = $this->get_settings();
        return isset($settings['document_presets']) ? $settings['document_presets'] : array();
    }

    /**
     * Get a preset by name
     */
    public function get_preset_by_name($name) {
        $presets = $this->get_presets();
        foreach ($presets as $preset) {
            if ($preset['name'] === $name) {
                return $preset;
            }
        }
        return null;
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'parish_events_settings_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );

        // Section 1: Documents Configuration
        add_settings_section(
            'parish_events_nextcloud',
            'Documents Configuration',
            array($this, 'render_nextcloud_section'),
            'parish-events-settings'
        );

        add_settings_field(
            'nextcloud_base_path',
            'Documents Base Path',
            array($this, 'render_text_field'),
            'parish-events-settings',
            'parish_events_nextcloud',
            array(
                'field' => 'nextcloud_base_path',
                'description' => 'Absolute filesystem path to the public documents folder (must be under web root)',
                'class' => 'large-text',
            )
        );

        // Section 2: Status Messages
        add_settings_section(
            'parish_events_messages',
            'Status Messages',
            array($this, 'render_messages_section'),
            'parish-events-settings'
        );

        add_settings_field(
            'message_future',
            'Future Event Message',
            array($this, 'render_text_field'),
            'parish-events-settings',
            'parish_events_messages',
            array(
                'field' => 'message_future',
                'description' => 'Message shown when agenda is not yet available for future events',
                'class' => 'large-text',
            )
        );

        add_settings_field(
            'message_past',
            'Past Event Message',
            array($this, 'render_text_field'),
            'parish-events-settings',
            'parish_events_messages',
            array(
                'field' => 'message_past',
                'description' => 'Message shown when agenda is not found for past events',
                'class' => 'large-text',
            )
        );

        // Section 3: Document Presets
        add_settings_section(
            'parish_events_presets',
            'Document Presets',
            array($this, 'render_presets_section'),
            'parish-events-settings'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['nextcloud_base_path'] = isset($input['nextcloud_base_path'])
            ? sanitize_text_field($input['nextcloud_base_path'])
            : '';

        $sanitized['message_future'] = isset($input['message_future'])
            ? sanitize_text_field($input['message_future'])
            : '';

        $sanitized['message_past'] = isset($input['message_past'])
            ? sanitize_text_field($input['message_past'])
            : '';

        // Sanitize presets
        $sanitized['document_presets'] = array();
        if (isset($input['document_presets']) && is_array($input['document_presets'])) {
            foreach ($input['document_presets'] as $preset) {
                if (!empty($preset['name'])) {
                    $sanitized['document_presets'][] = array(
                        'name'         => sanitize_text_field($preset['name']),
                        'base_path'    => sanitize_text_field($preset['base_path']),
                        'file_pattern' => sanitize_text_field($preset['file_pattern']),
                    );
                }
            }
        }

        return $sanitized;
    }

    /**
     * Render Nextcloud section description
     */
    public function render_nextcloud_section() {
        echo '<p>Configure the path to your public documents folder.</p>';
    }

    /**
     * Render messages section description
     */
    public function render_messages_section() {
        echo '<p>Configure messages shown when auto-linked documents are not available.</p>';
    }

    /**
     * Render presets section
     */
    public function render_presets_section() {
        echo '<p>Define document presets that can be selected for events. Use placeholders: <code>{YYYY}</code> (4-digit year), <code>{YY}</code> (2-digit year), <code>{MM}</code> (2-digit month), <code>{DD}</code> (2-digit day).</p>';

        $settings = $this->get_settings();
        $presets = isset($settings['document_presets']) ? $settings['document_presets'] : array();
        ?>
        <div id="parish-events-presets-container">
            <table class="widefat" id="parish-events-presets-table">
                <thead>
                    <tr>
                        <th style="width: 200px;">Preset Name</th>
                        <th style="width: 300px;">Base Path</th>
                        <th>File Pattern</th>
                        <th style="width: 80px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="parish-events-presets-list">
                    <?php if (!empty($presets)): ?>
                        <?php foreach ($presets as $index => $preset): ?>
                            <tr class="parish-events-preset-row">
                                <td>
                                    <input type="text" name="parish_events_settings[document_presets][<?php echo $index; ?>][name]"
                                           value="<?php echo esc_attr($preset['name']); ?>"
                                           class="regular-text" placeholder="e.g., Full Council Agenda">
                                </td>
                                <td>
                                    <input type="text" name="parish_events_settings[document_presets][<?php echo $index; ?>][base_path]"
                                           value="<?php echo esc_attr($preset['base_path']); ?>"
                                           class="regular-text" placeholder="Meeting Documents/Full Council/Agendas">
                                </td>
                                <td>
                                    <input type="text" name="parish_events_settings[document_presets][<?php echo $index; ?>][file_pattern]"
                                           value="<?php echo esc_attr($preset['file_pattern']); ?>"
                                           class="large-text" placeholder="{YYYY}/{MM}.{YY} APC Agenda.pdf">
                                </td>
                                <td>
                                    <button type="button" class="button parish-events-remove-preset">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button" id="parish-events-add-preset">+ Add Preset</button>
            </p>
        </div>
        <?php
    }

    /**
     * Render text field
     */
    public function render_text_field($args) {
        $settings = $this->get_settings();
        $field = $args['field'];
        $value = isset($settings[$field]) ? $settings[$field] : '';
        $class = isset($args['class']) ? $args['class'] : 'regular-text';

        printf(
            '<input type="text" id="%s" name="%s[%s]" value="%s" class="%s">',
            esc_attr($field),
            esc_attr($this->option_name),
            esc_attr($field),
            esc_attr($value),
            esc_attr($class)
        );

        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('parish_events_settings_group');
                do_settings_sections('parish-events-settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_parish-events-settings') {
            return;
        }

        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                var presetIndex = ' . count($this->get_presets()) . ';

                $("#parish-events-add-preset").on("click", function() {
                    var row = \'<tr class="parish-events-preset-row">\' +
                        \'<td><input type="text" name="parish_events_settings[document_presets][\' + presetIndex + \'][name]" class="regular-text" placeholder="e.g., Full Council Agenda"></td>\' +
                        \'<td><input type="text" name="parish_events_settings[document_presets][\' + presetIndex + \'][base_path]" class="regular-text" placeholder="Meeting Documents/Full Council/Agendas"></td>\' +
                        \'<td><input type="text" name="parish_events_settings[document_presets][\' + presetIndex + \'][file_pattern]" class="large-text" placeholder="{YYYY}/{MM}.{YY} APC Agenda.pdf"></td>\' +
                        \'<td><button type="button" class="button parish-events-remove-preset">Remove</button></td>\' +
                        \'</tr>\';
                    $("#parish-events-presets-list").append(row);
                    presetIndex++;
                });

                $(document).on("click", ".parish-events-remove-preset", function() {
                    $(this).closest(".parish-events-preset-row").remove();
                });
            });
        ');
    }
}
