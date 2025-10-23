<?php
/*Place this after your existing all_create_tables() function (for example, at the bottom of your main plugin file):*/

function upgrade_flight_deals_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'flight_deals';

    // Check and add 'image' column
    $image_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'image'");
    if (empty($image_exists)) {
        $wpdb->query("ALTER TABLE `$table` ADD `image` VARCHAR(255) NULL AFTER `more_details`");
    }

    // Check and add 'start_date' column
    $start_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'start_date'");
    if (empty($start_exists)) {
        $wpdb->query("ALTER TABLE `$table` ADD `start_date` DATE NULL AFTER `image`");
    }

    // Check and add 'end_date' column
    $end_exists = $wpdb->get_results("SHOW COLUMNS FROM `$table` LIKE 'end_date'");
    if (empty($end_exists)) {
        $wpdb->query("ALTER TABLE `$table` ADD `end_date` DATE NULL AFTER `start_date`");
    }
}
add_action('init', 'upgrade_flight_deals_table');
