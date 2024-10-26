<?php
class User_Reactivation_User_Manager {
    public static function get_inactive_users() {
        $options = get_option('user_reactivation_settings');
        $inactivity_days = isset($options['inactivity_days']) ? $options['inactivity_days'] : 30;
        $only_authors = isset($options['only_authors']) ? $options['only_authors'] : 1;
        $selected_roles = isset($options['user_roles']) ? $options['user_roles'] : array('author', 'contributor');
        
        $args = array(
            'fields' => array('ID', 'user_email', 'display_name'),
        );
        
        // Configurar la consulta para roles seleccionados
        if (in_array('subscriber', $selected_roles)) {
            // Si subscriber está seleccionado, incluir también usuarios sin rol
            $args['role__in'] = $selected_roles;
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => 'wp_capabilities',
                    'value' => 'a:0:{}',
                    'compare' => '='
                ),
                array(
                    'key' => 'wp_capabilities',
                    'compare' => 'EXISTS'
                )
            );
        } else {
            // Si no está seleccionado subscriber, usar solo los roles seleccionados
            $args['role__in'] = $selected_roles;
        }
        
        if ($only_authors) {
            $args['has_published_posts'] = true;
        }
        
        $users = get_users($args);
        $inactive_users = array();
        
        foreach ($users as $user) {
            if (self::is_user_inactive($user, $inactivity_days, $only_authors)) {
                $inactive_users[] = $user;
            }
        }
        
        return $inactive_users;
    }

    private static function is_user_inactive($user, $inactivity_days, $only_authors) {
        $last_post = get_posts(array(
            'author' => $user->ID,
            'posts_per_page' => 1,
            'orderby' => 'post_date',
            'order' => 'DESC'
        ));
        
        if (empty($last_post) && !$only_authors) {
            return true;
        }
        
        if (!empty($last_post)) {
            $last_post_date = strtotime($last_post[0]->post_date);
            return (time() - $last_post_date) / DAY_IN_SECONDS > $inactivity_days;
        }
        
        return false;
    }
}