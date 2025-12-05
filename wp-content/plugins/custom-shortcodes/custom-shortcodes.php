<?php
/*
Plugin Name: Custom Shortcodes
Description: A plugin to manage frontend custom shortcodes
Version: 1.0
Author: Custom made
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Include scripts loader
require_once plugin_dir_path(__FILE__) . 'includes/enqueue-scripts.php';

// Include shortcode files
require_once plugin_dir_path(__FILE__) . 'custom-functions.php';

require_once plugin_dir_path(__FILE__) . 'shortcodes/register-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/login-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/login-logout-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'referral-system.php';

require_once plugin_dir_path(__FILE__) . 'shortcodes/dashboard-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/airport-form-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/account-form-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/change-password-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/notification-switch-handler.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/flight-deals-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/home-flight-deals-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/flight-deal-details-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/referral-page-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/subscription-status-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/change-subscription-shortcode.php';

require_once plugin_dir_path(__FILE__) . 'includes/referral-rewards.php';

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
        wp_redirect(home_url('/flight-deals'));
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

// Redirect Premium Flight Deals before page content loads
add_action('template_redirect', function() {
    if (is_singular() && has_shortcode(get_post()->post_content, 'flight_deal_details')) {
        global $wpdb;

        // Get deal_id from URL query var
        $deal_id = get_query_var('deal_id');
        if (!$deal_id) return;

        $table = $wpdb->prefix . 'flight_deals';
        $deal = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table WHERE id = %d AND status = 1
        ", $deal_id));

        if ($deal && intval($deal->offer_type) === 1) {
			$user_id = get_current_user_id();
			$subscriptions = pms_get_member_subscriptions( array( 'user_id' => $user_id ) );
			if ( empty( $subscriptions ) || $subscriptions[0]->billing_amount == 0){
				wp_redirect(home_url('/flight-deals'));
				exit;
			}
        }
    }
});
function add_deal_details_rewrite_rule() {
    add_rewrite_rule('^deal-details/([0-9]+)/?', 'index.php?pagename=deal-details&deal_id=$matches[1]', 'top');
    add_rewrite_tag('%deal_id%', '([0-9]+)');
}
add_action('init', 'add_deal_details_rewrite_rule');

/**
 * Force Sassy Social Share (and similar) to share the user's referral link
 * Referral link = register page + ?ref=referral_code (from user_meta)
 */
function heateor_sss_customize_shared_url($postUrl, $sharingType, $standardWidget){
	// Only modify if the user is logged in
    if (is_user_logged_in()) {

        $user_id = get_current_user_id();

        // Change 'referral_code' to the actual meta key you use in user_meta table
        $referral_code = get_user_meta($user_id, 'wrc_ref_code', true);

        if (!empty($referral_code)) {
            // Change this to your actual register page URL
            $register_page_url = home_url('/register');

            // Build full referral URL
            $referral_link = add_query_arg('ref', $referral_code, $register_page_url);

            return $referral_link;
        }
    }

    // If not logged in or no code found, fallback to original URL
    return $postUrl;
}
add_filter('heateor_sss_target_share_url_filter', 'heateor_sss_customize_shared_url', 10, 3);

function restrict_pages_for_front_user_role() {
	if ( is_admin() || isset($_GET['elementor-preview']) || isset($_GET['action']) && $_GET['action'] === 'elementor' ) {
        return;
    }
    if ( is_page() ) {
        // List of pages you want to restrict
        $restricted_pages = array(
            'polec-znajomym',
            'subskrypcje',
            'zmiana-subskrypcji',
            'zmiana-lotniska',
            'oferty-lotnicze',
            'powiadomienia',
            'konto',
            'flight-deals',
            'deal-details',
            // 'pricing',
            'checkout',
        );

        // Check if the current page matches
        $current_slug = get_post_field( 'post_name', get_post() );

        if ( in_array( $current_slug, $restricted_pages ) ) {

            // Check if logged in
            if ( ! is_user_logged_in() ) {
                wp_redirect( home_url( '/login' ) . '?redirect_to=' . urlencode( get_permalink() ) );
				exit;
            }

            // Check user role
            $user = wp_get_current_user();
            if ( ! in_array( 'front_user', (array) $user->roles ) ) {
                wp_redirect( home_url() ); // or use a custom "no access" page
                exit;
            }
        }
    }
}
add_action( 'template_redirect', 'restrict_pages_for_front_user_role' );
// Redirect logged-in users visiting the login page
function redirect_logged_in_users_from_login_page() {
    // Don't affect wp-admin or wp-login.php
    if ( is_admin() || isset($_GET['elementor-preview']) || isset($_GET['action']) && $_GET['action'] === 'elementor' ) {
        return;
    }

    if ( is_user_logged_in() && ( is_page('login') || is_page('register') ) ) {
        wp_safe_redirect( home_url('/flight-deals') );
        exit;
    }
}
add_action('template_redirect', 'redirect_logged_in_users_from_login_page');

add_filter('locale', function($locale) {
    // Check if we're in the admin area
    if ( is_admin() ) {
        return 'en_US'; // Keep backend in English
    } else {
        return 'pl_PL'; // Force frontend to Polish
    }
});

add_filter('wp_nav_menu_objects', function ($items, $args) {
	if ($args->menu === 'menu-desktop') {
	   foreach ($items as $key => $item) {
			// Find "Strona główna"
			if ($item->title === 'Strona główna') {

				// If the user is logged in, replace it with "Oferty lotów"
				if (is_user_logged_in()) {
					$item->title = 'Oferty lotów';
					$item->url   = site_url('/flight-deals/');

					// Make it active if on /flight-deals/ page
					if (is_page('flight-deals') || strpos($_SERVER['REQUEST_URI'], '/flight-deals') !== false) {
						$item->classes[] = 'current-menu-item';
					}
				}

				break; // Stop after modifying the first match
			}
		}
	}
	if ($args->menu != 'menu-desktop') {
		// Handle submenu for "Konto"
		if (is_user_logged_in()) {
			$konto_id = null;

			// Find the "Konto" menu item
			foreach ($items as $item) {
				if ($item->title === 'Konto') {
					$konto_id = $item->ID;
					break;
				}
			}

			// If Konto menu exists, add Wyloguj się under it
			if ($konto_id) {
				$logout_item = (object) [
					'ID' => 999999,
					'title' => 'Wyloguj się',
					'url' => wp_logout_url(home_url( '/login' )),
					'menu_item_parent' => $konto_id,
					'classes' => ['menu-item', 'logout-link'],
					'type' => '',
					'object' => '',
					'object_id' => '',
				];

				$items[] = $logout_item;
			}
		}
	}
	
	return $items;
}, 10, 2);
