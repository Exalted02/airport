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
		code varchar(255) NOT NULL,
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
		booking_link text NOT NULL,
		description text NOT NULL,
		more_details text NULL,
		image varchar(255) NULL,
        start_date date NULL,
        end_date date NULL,
        airport_id mediumint(9) NOT NULL,
		status tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Archived',
		showing_home_page tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = Hide, 1 = Show on home page',
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
	
	add_submenu_page('jet-saver-club', 'Export Data', 'Export Data', 'manage_options', 'export-data', 'custom_export_data_page');

});

// Include external page
function airport_admin_render_airports_page() {
    include plugin_dir_path(__FILE__) . 'admin-airports-page.php';
}
function flight_deals_admin_render_page() {
    include plugin_dir_path(__FILE__) . 'admin-flight-deals-page.php';
}
function custom_export_data_page() {
    include plugin_dir_path(__FILE__) . 'admin-export-page.php';
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
/*add_action('admin_init', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'flight_deals';

    // Add column only if not exists
    $code_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'booking_link'");
    if($code_exists) {
        $wpdb->query("ALTER TABLE `$table` CHANGE `booking_link` `booking_link` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL");
    }
});*/
/**
 * ==============================
 * CSV EXPORT HANDLERS
 * ==============================
 */

/**
 * Export WordPress Users CSV
 */
add_action('admin_post_export_users_csv', function () {
    if (!current_user_can('manage_options')) wp_die('Permission denied');

    $users = get_users([
        'role' => 'front_user',
        'orderby' => 'ID',
        'order' => 'ASC'
    ]);

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=users-" . date('Y-m-d') . ".csv");

    $output = fopen('php://output', 'w');

    fputcsv($output, ['ID', 'Email', 'Name', 'First Name', 'Last Name', 'Subscription', 'Registered']);

    foreach ($users as $u) {
		// ✔ Get PMS subscriptions
		$subscriptions = pms_get_member_subscriptions( array( 'user_id' => $u->ID ) );
		// ✔ Determine free/premium
        $user_type = 'free';
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $sub) {
                if ($sub->status === 'active') {
                    $user_type = 'premium';
                    break;
                }
            }
        }
		
        fputcsv($output, [
            $u->ID,
            $u->user_email,
            $u->display_name,
            get_user_meta($u->ID, 'first_name', true),
            get_user_meta($u->ID, 'last_name', true),
            //implode(',', $u->roles),
			$user_type,
            $u->user_registered,
            //get_user_meta($u->ID, 'newsletter_subscription', true) ?: 'no'
        ]);
    }

    fclose($output);
    exit;
});

/**
 * Export PMS Subscription CSV
 */
add_action('admin_post_export_pms_csv', function () {
    if (!current_user_can('manage_options')) wp_die('Permission denied');

    global $wpdb;

    $table = $wpdb->prefix . 'pms_subscriptions';

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=pms-" . date('Y-m-d') . ".csv");

    $output = fopen('php://output', 'w');

    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        fputcsv($output, ['Table missing']);
        exit;
    }

    $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);

    if (!empty($rows)) {
        fputcsv($output, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit;
});

/**
 * Export Flight Deals CSV
 */
add_action('admin_post_export_flight_deals_csv', function () {
    if (!current_user_can('manage_options')) wp_die('Permission denied');

    global $wpdb;

    $table_deals   = $wpdb->prefix . 'flight_deals';
    $table_airport = $wpdb->prefix . 'airport_list';

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=flight-deals-" . date('Y-m-d') . ".csv");

    $output = fopen('php://output', 'w');

    // Check table
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_deals}'") !== $table_deals) {
        fputcsv($output, ['Table missing']);
        exit;
    }

    // Fetch data with airport name
    $rows = $wpdb->get_results("
        SELECT 
            d.id,
            d.offer_type,
            d.price,
            d.purpose,
            d.booking_link,
            d.description,
            d.more_details,
            d.image,
            d.start_date,
            d.end_date,
            a.name AS airport_name,
            a.code AS airport_code
        FROM {$table_deals} d
        LEFT JOIN {$table_airport} a ON d.airport_id = a.id
        ORDER BY d.id ASC
    ", ARRAY_A);

    if (!empty($rows)) {

        // Set header row (no "airport_id", no "status")
        fputcsv($output, [
            'ID',
            'Offer Type',
            'Price',
            'Purpose',
            'Booking Link',
            'Description',
            'More Details',
            'Image',
            'Start Date',
            'End Date',
            'Airport Name'
        ]);

        foreach ($rows as $row) {

            // Convert offer type
            $row['offer_type'] = ($row['offer_type'] == 1) ? 'Premium' : 'Non Premium';

            // Prepare row for CSV
            fputcsv($output, [
                $row['id'],
                $row['offer_type'],
                $row['price'],
                $row['purpose'],
                $row['booking_link'],
                $row['description'],
                $row['more_details'],
                $row['image'],
                $row['start_date'],
                $row['end_date'],
                $row['airport_name'].'('.$row['airport_code'].')' // converted
            ]);
        }
    }

    fclose($output);
    exit;
});

