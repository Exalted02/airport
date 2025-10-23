<?php
/**
 * Plugin Name: Custom Plugin Menu
 * Description: Admin menu with Users submenu using custom DB table.
 * Version: 1.0
 * Author: Custom made
 */

if (!defined('ABSPATH')) exit;

// Create DB table on activation
function all_create_tables() {	
    global $wpdb;
	$table1 = $wpdb->prefix . 'airport_list';
    $charset = $wpdb->get_charset_collate();
	
    $sql = "CREATE TABLE IF NOT EXISTS $table1 (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		code varchar(20) NOT NULL,
		name varchar(255) NOT NULL,
		PRIMARY KEY (id)
	) $charset;";
	
	// Flight Deals table
	$table2 = $wpdb->prefix . 'flight_deals';
	$sql2 = "CREATE TABLE IF NOT EXISTS $table2 (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		offer_type tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = Non Premium, 1 = Premium',
		price decimal(10,2) NOT NULL,
		purpose varchar(255) NOT NULL,
		booking_link varchar(255) NOT NULL,
		description text NOT NULL,
		more_details text NULL,
		image varchar(255) NULL,
        start_date date NULL,
        end_date date NULL,
        airport_id mediumint(9) NOT NULL,
		status tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Archived',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) $charset;";
	
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
	dbDelta($sql2);
}

// Add menu
add_action('admin_menu', function() {
	add_menu_page('Custom Menu', 'Custom Menu', 'manage_options', 'jet-saver-club', function() {
        echo '<div class="wrap"><h1>Custom Menu</h1><p>Select a submenu.</p></div>';
    }, 'dashicons-admin-generic');
	add_submenu_page('jet-saver-club', 'Airports', 'Airports', 'manage_options', 'airports', 'airport_admin_render_airports_page');
	
	add_submenu_page('jet-saver-club', 'Flight Deals', 'Flight Deals', 'manage_options', 'flight-deals', 'flight_deals_admin_render_page');
});

// Include external page
function airport_admin_render_airports_page() {
    include plugin_dir_path(__FILE__) . 'admin-airports-page.php';
}
function flight_deals_admin_render_page() {
    include plugin_dir_path(__FILE__) . 'admin-flight-deals-page.php';
}

// DB add/edit function
add_action('init', 'wp_add_airport_table');
function wp_add_airport_table() {
    if (!get_option('wp_adding_airport_table')) {
        all_create_tables();
        update_option('wp_adding_airport_table', true);
    }
}
add_action('init', 'wp_add_flight_deals_tables');
function wp_add_flight_deals_tables() {
    if (!get_option('wp_flight_deals_tables_created')) {
        all_create_tables();
        update_option('wp_flight_deals_tables_created', true);
    }
}
