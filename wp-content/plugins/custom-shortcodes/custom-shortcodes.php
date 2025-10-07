<?php
/*
Plugin Name: Custom Shortcodes
Description: A plugin to manage frontend custom shortcodes
Version: 1.0
Author: Custom made
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Include shortcode files
require_once plugin_dir_path(__FILE__) . 'shortcodes/register-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/login-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/dashboard-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/airport-form-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/account-form-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/change-password-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/notification-switch-handler.php';

// ----------------------------------------------------
// Enqueue jQuery for frontend
// ----------------------------------------------------
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('jquery');
});


// Register custom role on plugin activation
function my_custom_shortcodes_add_role() {
    add_role(
        'front_user',
        'Front User',
        array(
            'read' => true,  // allow login
        )
    );
}
register_activation_hook(__FILE__, 'my_custom_shortcodes_add_role');

function restrict_front_user_admin_access() {
    if ( is_admin() && ! defined('DOING_AJAX') && current_user_can('front_user') ) {
        wp_redirect(home_url('/subskrypcje'));
        exit;
    }
}
add_action('admin_init', 'restrict_front_user_admin_access');

// Force Google users to have "front_user" role after registration/login
function my_custom_force_front_user_role( $user_id, $provider ) {
    if ( $provider === 'google' ) {
        // Update role
        $user = new WP_User( $user_id );
        $user->set_role( 'front_user' );

        // Force update usermeta in case something else overrides it
        update_user_meta( $user_id, $user->cap_key, array( 'front_user' => true ) );
    }
}
add_action( 'nsl_register_user', 'my_custom_force_front_user_role', 10, 2 );
add_action( 'nsl_login_user', 'my_custom_force_front_user_role', 10, 2 );
