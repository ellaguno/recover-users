<?php
class User_Reactivation_Settings {
    // ... (resto del código igual hasta roles_callback)

    public function roles_callback() {
        $options = get_option('user_reactivation_settings');
        $selected_roles = isset($options['user_roles']) ? $options['user_roles'] : array('author', 'contributor');
        
        // Mostrar roles regulares
        foreach (wp_roles()->roles as $role => $details) {
            $name = translate_user_role($details['name']);
            ?>
            <label>
                <input type="checkbox" 
                       name="user_reactivation_settings[user_roles][]" 
                       value="<?php echo esc_attr($role); ?>"
                       <?php checked(in_array($role, $selected_roles)); ?>>
                <?php echo esc_html($name); ?>
            </label><br>
            <?php
        }
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        $sanitized['auto_send'] = isset($input['auto_send']) ? 1 : 0;
        $sanitized['inactivity_days'] = absint($input['inactivity_days']);
        $sanitized['only_authors'] = isset($input['only_authors']) ? 1 : 0;
        
        // Sanitize role array
        $sanitized['user_roles'] = array();
        if (isset($input['user_roles']) && is_array($input['user_roles'])) {
            $available_roles = array_keys(wp_roles()->roles);
            foreach ($input['user_roles'] as $role) {
                if (in_array($role, $available_roles)) {
                    $sanitized['user_roles'][] = $role;
                }
            }
        }
        
        return $sanitized;
    }

    public function add_admin_menu() {
        add_options_page(
            esc_html__('User Reactivation Settings', 'recover-users'),
            esc_html__('User Reactivation', 'recover-users'),
            'manage_options',
            'user-reactivation',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h2><?php esc_html_e('User Reactivation Settings', 'recover-users'); ?></h2>
            
            <div class="user-reactivation-test-email">
                <h3><?php esc_html_e('Test Email', 'recover-users'); ?></h3>
                <p><?php esc_html_e('Send a test email to yourself to preview how reactivation emails will look.', 'recover-users'); ?></p>
                <button class="button button-secondary send-test-email">
                    <?php esc_html_e('Send Test Email', 'recover-users'); ?>
                </button>
                <span class="spinner"></span>
                <div class="test-email-result"></div>
            </div>

            <hr>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('user_reactivation_options');
                do_settings_sections('user-reactivation');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <div class="user-reactivation-manual-send">
                <h3><?php esc_html_e('Manual Email Send', 'recover-users'); ?></h3>
                <p><?php esc_html_e('Click the button below to manually send reactivation emails to inactive users.', 'recover-users'); ?></p>
                <button class="button button-primary send-reactivation-emails">
                    <?php esc_html_e('Send Emails Now', 'recover-users'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>
        <?php
    }
}

// Inicializar la clase de configuración
new User_Reactivation_Settings();