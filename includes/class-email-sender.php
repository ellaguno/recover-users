<?php
class User_Reactivation_Email_Sender {
    private static function get_post_preview($post) {
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

    private static function get_email_preview_content($user) {
        $content = '';

        // Obtener posts del usuario
        $user_posts = get_posts(array(
            'author' => $user->ID,
            'posts_per_page' => 5,
            'post_status' => 'publish'
        ));

        if (!empty($user_posts)) {
            $content .= '<h3>' . esc_html__('Your latest posts:', 'recover-users') . '</h3>';
            foreach ($user_posts as $post) {
                $content .= self::get_post_preview($post);
            }
        }

        // Obtener posts recientes
        $recent_posts = get_posts(array(
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'author__not_in' => array($user->ID)
        ));

        if (!empty($recent_posts)) {
            $content .= '<h3>' . esc_html__('Latest blog posts:', 'recover-users') . '</h3>';
            foreach ($recent_posts as $post) {
                $content .= self::get_post_preview($post);
            }
        }

        return $content;
    }

    public static function send_test_email() {
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
        
        $message = '<div style="max-width: 600px; margin: 0 auto; font-family: sans-serif;">';
        $message .= '<h2>' . esc_html__('This is a test email from User Reactivation plugin', 'recover-users') . '</h2>';
        
        // Agregar contenido de ejemplo con los posts
        $message .= self::get_email_preview_content($current_user);
        
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

    public static function send_reactivation_email($user) {
        $message = '<div style="max-width: 600px; margin: 0 auto; font-family: sans-serif;">';
        $message .= sprintf(esc_html__('Hi %s,', 'recover-users'), esc_html($user->display_name));
        $message .= "\n\n" . esc_html__('We hope you are doing well. We noticed it\'s been a while since you last wrote on our blog. We\'d love to have your contributions again!', 'recover-users') . "\n\n";
        
        // Agregar contenido con los posts
        $message .= self::get_email_preview_content($user);
        
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
}