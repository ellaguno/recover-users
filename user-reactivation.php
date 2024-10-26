<?php
/**
 * Plugin Name: User Reactivation Email
 * Plugin URI: https://sesolibre.com/user-reactivation
 * Description: Sends personalized emails to inactive users with their previous posts and latest blog updates
 * Version: 1.1.0
 * Author: Eduardo Llaguno
 * Author URI: https://sesolibre.com
 * Text Domain: recover-users
 * Domain Path: /languages
 * License: GPL v2 or later
 */

if (!defined('WPINC')) {
    die;
}

define('USER_REACTIVATION_VERSION', '1.1.0');
define('USER_REACTIVATION_PATH', plugin_dir_path(__FILE__));

// Load translation
function user_reactivation_load_textdomain() {
    load_plugin_textdomain('recover-users', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'user_reactivation_load_textdomain');

// Function to get inactive users
function get_inactive_users() {
    $options = get_option('user_reactivation_settings');
    $inactivity_days = isset($options['inactivity_days']) ? $options['inactivity_days'] : 30;
    $only_authors = isset($options['only_authors']) ? $options['only_authors'] : 1;
    $selected_roles = isset($options['user_roles']) ? $options['user_roles'] : array('author', 'contributor');
    
    $args = array(
        'fields' => array('ID', 'user_email', 'display_name')
    );
    
    if (!empty($selected_roles)) {
        $args['role__in'] = $selected_roles;
    }
    
    if ($only_authors) {
        $args['has_published_posts'] = true;
    }
    
    $users = get_users($args);
    $inactive_users = array();
    
    foreach ($users as $user) {
        $last_post = get_posts(array(
            'author' => $user->ID,
            'posts_per_page' => 1,
            'orderby' => 'post_date',
            'order' => 'DESC'
        ));
        
        if (empty($last_post) && !$only_authors) {
            $inactive_users[] = $user;
        } elseif (!empty($last_post)) {
            $last_post_date = strtotime($last_post[0]->post_date);
            if ((time() - $last_post_date) / DAY_IN_SECONDS > $inactivity_days) {
                $inactive_users[] = $user;
            }
        }
    }
    
    return $inactive_users;
}

// Function to generate post preview HTML
function generate_post_preview($post) {
    $thumbnail = get_the_post_thumbnail($post->ID, 'thumbnail');
    $excerpt = has_excerpt($post->ID) ? get_the_excerpt($post) : wp_trim_words(get_the_content(null, false, $post), 20);
    
    $preview = '<div style="margin-bottom: 20px; padding: 10px; border: 1px solid #ddd;">';
    if ($thumbnail) {
        $preview .= '<div style="float: left; margin-right: 10px; max-width: 150px;">' . $thumbnail . '</div>';
    }
    $preview .= '<h3 style="margin: 0 0 10px 0;"><a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html(get_the_title($post)) . '</a></h3>';
    $preview .= '<p style="margin: 0;">' . wp_kses_post($excerpt) . '</p>';
    $preview .= '<div style="clear: both;"></div>';
    $preview .= '</div>';
    
    return $preview;
}

// Function to send individual email
function send_user_reactivation_email($user) {
    $options = get_option('user_reactivation_settings');
    $message = '<div style="max-width: 600px; margin: 0 auto; font-family: sans-serif;">';
    
    // Usar mensaje personalizado
    $email_message = isset($options['email_message']) ? $options['email_message'] : esc_html__('We hope you are doing well. We noticed it\'s been a while since you last wrote on our blog. We\'d love to have your contributions again!', 'recover-users');
    $email_message = str_replace('{display_name}', $user->display_name, $email_message);
    
    $message .= sprintf(esc_html__('Hi %s,', 'recover-users'), esc_html($user->display_name));
    $message .= "\n\n" . $email_message . "\n\n";

    // Obtener número de posts configurado
    $user_posts_number = isset($options['user_posts_number']) ? absint($options['user_posts_number']) : 5;
    $recent_posts_number = isset($options['recent_posts_number']) ? absint($options['recent_posts_number']) : 5;

    if ($user_posts_number > 0) {
        $user_posts = get_posts(array(
            'author' => $user->ID,
            'posts_per_page' => $user_posts_number,
            'post_status' => 'publish'
        ));

        if (!empty($user_posts)) {
            $message .= '<h2>' . esc_html__('Your latest posts:', 'recover-users') . '</h2>';
            foreach ($user_posts as $post) {
                $message .= generate_post_preview($post);
            }
        }
    }

    if ($recent_posts_number > 0) {
        $recent_posts = get_posts(array(
            'posts_per_page' => $recent_posts_number,
            'post_status' => 'publish',
            'author__not_in' => array($user->ID)
        ));

        $message .= '<h2>' . esc_html__('Latest blog posts:', 'recover-users') . '</h2>';
        foreach ($recent_posts as $post) {
            $message .= generate_post_preview($post);
        }
    }

    $message .= '<hr style="margin: 30px 0;">';
    $message .= esc_html__('We hope to see you back soon!', 'recover-users');
    $message .= '</div>';

    $headers = array('Content-Type: text/html; charset=UTF-8');

    return wp_mail(
        $user->user_email,
        esc_html__('We miss you on our blog!', 'recover-users'),
        $message,
        $headers
    );
}

// Function to send test email
function send_test_reactivation_email() {
    check_ajax_referer('user_reactivation_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => esc_html__('Permission denied', 'recover-users')));
        return;
    }
    
    $current_user = wp_get_current_user();
    
    $subject = sprintf(
        esc_html__('[TEST] User Reactivation Email for %s', 'recover-users'),
        esc_html($current_user->user_email)
    );
    
    // Construir el mensaje igual que en send_user_reactivation_email
    $message = '<div style="max-width: 600px; margin: 0 auto; font-family: sans-serif;">';
    $message .= '<h2>' . esc_html__('This is a test email from User Reactivation plugin', 'recover-users') . '</h2>';
    
    $user_posts = get_posts(array(
        'author' => $current_user->ID,
        'posts_per_page' => 5,
        'post_status' => 'publish'
    ));

    if (!empty($user_posts)) {
        $message .= '<h3>' . esc_html__('Your latest posts:', 'recover-users') . '</h3>';
        foreach ($user_posts as $post) {
            $message .= generate_post_preview($post);
        }
    }

    $recent_posts = get_posts(array(
        'posts_per_page' => 5,
        'post_status' => 'publish',
        'author__not_in' => array($current_user->ID)
    ));

    $message .= '<h3>' . esc_html__('Latest blog posts:', 'recover-users') . '</h3>';
    foreach ($recent_posts as $post) {
        $message .= generate_post_preview($post);
    }
    
    $message .= '</div>';
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    $sent = wp_mail(
        $current_user->user_email,
        $subject,
        $message,
        $headers
    );

    if ($sent) {
        wp_send_json_success(array(
            'message' => sprintf(
                esc_html__('Test email sent to %s', 'recover-users'),
                esc_html($current_user->user_email)
            )
        ));
    } else {
        wp_send_json_error(array(
            'message' => esc_html__('Failed to send test email. Please check your WordPress email configuration.', 'recover-users')
        ));
    }
}
add_action('wp_ajax_send_test_reactivation_email', 'send_test_reactivation_email');

// AJAX handler for manual email sending
function handle_manual_email_send() {
    check_ajax_referer('user_reactivation_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => esc_html__('Permission denied', 'recover-users')));
        return;
    }
    
    $inactive_users = get_inactive_users();
    $emails_sent = 0;
    $total_users = count($inactive_users);

    if ($total_users === 0) {
        wp_send_json_success(array(
            'message' => esc_html__('No inactive users found matching your criteria.', 'recover-users')
        ));
        return;
    }
    
    foreach ($inactive_users as $user) {
        if (send_user_reactivation_email($user)) {
            $emails_sent++;
        }
    }
    
    wp_send_json_success(array(
        'message' => sprintf(
            esc_html__('Successfully sent %d reactivation emails out of %d inactive users.', 'recover-users'),
            $emails_sent,
            $total_users
        )
    ));
}
add_action('wp_ajax_send_reactivation_emails_manual', 'handle_manual_email_send');

// Register plugin settings
function user_reactivation_register_settings() {
    register_setting('user_reactivation_options', 'user_reactivation_settings', 'user_reactivation_sanitize_settings');
    
    // Main settings section
    add_settings_section(
        'user_reactivation_main',
        esc_html__('Email Settings', 'recover-users'),
        null,
        'user-reactivation'
    );
    
    // User type settings section
    add_settings_section(
        'user_reactivation_users',
        esc_html__('User Selection', 'recover-users'),
        null,
        'user-reactivation'
    );
    
    // Add settings fields
    add_settings_field(
        'auto_send',
        esc_html__('Automatic Sending', 'recover-users'),
        'user_reactivation_auto_send_callback',
        'user-reactivation',
        'user_reactivation_main'
    );
    
    add_settings_field(
        'inactivity_period',
        esc_html__('Inactivity Period (days)', 'recover-users'),
        'user_reactivation_inactivity_callback',
        'user-reactivation',
        'user_reactivation_main'
    );
    
    add_settings_field(
        'user_roles',
        esc_html__('User Roles to Include', 'recover-users'),
        'user_reactivation_roles_callback',
        'user-reactivation',
        'user_reactivation_users'
    );
    
    add_settings_field(
        'only_authors',
        esc_html__('Only Users with Posts', 'recover-users'),
        'user_reactivation_authors_callback',
        'user-reactivation',
        'user_reactivation_users'
    );
    // Sección de contenido del email
    add_settings_section(
        'user_reactivation_content',
        esc_html__('Email Content Settings', 'recover-users'),
        null,
        'user-reactivation'
    );
    
    // Campo para mensaje personalizado
    add_settings_field(
        'email_message',
        esc_html__('Custom Message', 'recover-users'),
        'user_reactivation_message_callback',
        'user-reactivation',
        'user_reactivation_content'
    );
    
    // Campo para número de posts
    add_settings_field(
        'posts_number',
        esc_html__('Number of Posts to Include', 'recover-users'),
        'user_reactivation_posts_number_callback',
        'user-reactivation',
        'user_reactivation_content'
    );
}

add_action('admin_init', 'user_reactivation_register_settings');

// Callback para el mensaje personalizado
function user_reactivation_message_callback() {
    $options = get_option('user_reactivation_settings');
    $message = isset($options['email_message']) ? $options['email_message'] : esc_html__('We hope you are doing well. We noticed it\'s been a while since you last wrote on our blog. We\'d love to have your contributions again!', 'recover-users');
    ?>
    <textarea name="user_reactivation_settings[email_message]" 
              rows="4" 
              cols="50" 
              class="large-text"><?php echo esc_textarea($message); ?></textarea>
    <p class="description">
        <?php esc_html_e('Customize the message sent to inactive users. You can use {display_name} to include the user\'s name.', 'recover-users'); ?>
    </p>
    <?php
}

// Callback para el número de posts
function user_reactivation_posts_number_callback() {
    $options = get_option('user_reactivation_settings');
    $user_posts = isset($options['user_posts_number']) ? $options['user_posts_number'] : 5;
    $recent_posts = isset($options['recent_posts_number']) ? $options['recent_posts_number'] : 5;
    ?>
    <div class="posts-number-settings">
        <label>
            <?php esc_html_e('User\'s Posts:', 'recover-users'); ?>
            <input type="number" 
                   name="user_reactivation_settings[user_posts_number]" 
                   value="<?php echo esc_attr($user_posts); ?>" 
                   min="0" 
                   max="20">
        </label>
        <p class="description">
            <?php esc_html_e('Number of user\'s own posts to include in the email (0-20)', 'recover-users'); ?>
        </p>
        
        <br>
        
        <label>
            <?php esc_html_e('Recent Blog Posts:', 'recover-users'); ?>
            <input type="number" 
                   name="user_reactivation_settings[recent_posts_number]" 
                   value="<?php echo esc_attr($recent_posts); ?>" 
                   min="0" 
                   max="20">
        </label>
        <p class="description">
            <?php esc_html_e('Number of recent blog posts to include in the email (0-20)', 'recover-users'); ?>
        </p>
    </div>
    <?php
}

// Settings callbacks
function user_reactivation_auto_send_callback() {
    $options = get_option('user_reactivation_settings');
    $auto_send = isset($options['auto_send']) ? $options['auto_send'] : 0;
    ?>
    <input type="checkbox" 
           name="user_reactivation_settings[auto_send]" 
           value="1" 
           <?php checked(1, $auto_send); ?>>
    <p class="description">
        <?php esc_html_e('Enable automatic weekly emails', 'recover-users'); ?>
    </p>
    <?php
}

function user_reactivation_inactivity_callback() {
    $options = get_option('user_reactivation_settings');
    $days = isset($options['inactivity_days']) ? $options['inactivity_days'] : 30;
    ?>
    <input type="number" 
           name="user_reactivation_settings[inactivity_days]" 
           value="<?php echo esc_attr($days); ?>" 
           min="1">
    <p class="description">
        <?php esc_html_e('Number of days without posts to consider a user inactive', 'recover-users'); ?>
    </p>
    <?php
}

function user_reactivation_roles_callback() {
    $options = get_option('user_reactivation_settings');
    $selected_roles = isset($options['user_roles']) ? $options['user_roles'] : array('author', 'contributor');
    
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

function user_reactivation_authors_callback() {
    $options = get_option('user_reactivation_settings');
    $only_authors = isset($options['only_authors']) ? $options['only_authors'] : 1;
    ?>
    <input type="checkbox" 
           name="user_reactivation_settings[only_authors]" 
           value="1" 
           <?php checked(1, $only_authors); ?>>
    <p class="description">
        <?php esc_html_e('Only send emails to users who have published at least one post', 'recover-users'); ?>
    </p>
    <?php
}

function user_reactivation_sanitize_settings($input) {
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
    
    // Sanitizar mensaje personalizado
    $sanitized['email_message'] = wp_kses_post($input['email_message']);
    
    // Sanitizar números de posts
    $sanitized['user_posts_number'] = min(20, max(0, absint($input['user_posts_number'])));
    $sanitized['recent_posts_number'] = min(20, max(0, absint($input['recent_posts_number'])));
    
    return $sanitized;
}

// Settings page
function user_reactivation_settings_page() {
    ?>
    <div class="wrap">
        <hr>
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
            <div class="manual-email-result"></div>
        </div>
    </div>
    <?php
}

// Add admin menu
function user_reactivation_admin_menu() {
    add_options_page(
        esc_html__('User Reactivation Settings', 'recover-users'),
        esc_html__('User Reactivation', 'recover-users'),
        'manage_options',
        'user-reactivation',
        'user_reactivation_settings_page'
    );
}
add_action('admin_menu', 'user_reactivation_admin_menu');

// Enqueue admin scripts
function user_reactivation_admin_scripts($hook) {
    if ('settings_page_user-reactivation' !== $hook) {
        return;
    }
    
    wp_enqueue_style(
        'user-reactivation-admin',
        plugins_url('assets/css/admin.css', __FILE__),
        array(),
        USER_REACTIVATION_VERSION
    );

    wp_enqueue_script(
        'user-reactivation-admin',
        plugins_url('assets/js/admin.js', __FILE__),
        array('jquery'),
        USER_REACTIVATION_VERSION,
        true
    );
    
    wp_localize_script('user-reactivation-admin', 'userReactivation', array(
        'nonce' => wp_create_nonce('user_reactivation_nonce'),
        'confirmMessage' => esc_html__('Are you sure you want to send reactivation emails now?', 'recover-users'),
        'testEmailSending' => esc_html__('Sending test email...', 'recover-users'),
        'testEmailSuccess' => esc_html__('Test email sent successfully!', 'recover-users'),
        'testEmailError' => esc_html__('Error sending test email. Please try again.', 'recover-users'),
        'error' => esc_html__('An error occurred. Please try again.', 'recover-users')
    ));
}
add_action('admin_enqueue_scripts', 'user_reactivation_admin_scripts');

// Activation hook
register_activation_hook(__FILE__, 'user_reactivation_activation');
function user_reactivation_activation() {
    $default_settings = array(
        'auto_send' => 0,
        'inactivity_days' => 30,
        'only_authors' => 1,
        'user_roles' => array('author', 'contributor'),
        'email_message' => esc_html__('We hope you are doing well. We noticed it\'s been a while since you last wrote on our blog. We\'d love to have your contributions again!', 'recover-users'),
        'user_posts_number' => 5,
        'recent_posts_number' => 5
    );
    add_option('user_reactivation_settings', $default_settings);
    
    if (!wp_next_scheduled('user_reactivation_cron') && $default_settings['auto_send']) {
        wp_schedule_event(time(), 'weekly', 'user_reactivation_cron');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'user_reactivation_deactivation');
function user_reactivation_deactivation() {
    wp_clear_scheduled_hook('user_reactivation_cron');
}

// Run scheduled task
add_action('user_reactivation_cron', function() {
    $options = get_option('user_reactivation_settings');
    if (isset($options['auto_send']) && $options['auto_send']) {
        $inactive_users = get_inactive_users();
        foreach ($inactive_users as $user) {
            send_user_reactivation_email($user);
        }
    }
});