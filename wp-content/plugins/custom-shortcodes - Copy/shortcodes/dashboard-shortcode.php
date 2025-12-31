<?php
// Dashboard shortcode
function custom_user_dashboard() {
    if ( ! is_user_logged_in() ) {
        return "<p>Please <a href='/login'>login</a> to access your dashboard.</p>";
    }

    $user = wp_get_current_user();
    $airport_id = get_user_meta($user->ID, 'airport', true);

    global $wpdb;
    $airport_name = '';
    if ($airport_id) {
        $airport = $wpdb->get_row(
            $wpdb->prepare("SELECT name FROM {$wpdb->prefix}airport_list WHERE id = %d", $airport_id)
        );
        if ($airport) {
            $airport_name = $airport->name;
        }
    }

    ob_start();
    ?>
    <h2>Welcome, <?php echo esc_html($user->display_name ?: $user->user_email); ?></h2>
    <p>Your registered airport: <?php echo esc_html($airport_name ?: 'Not selected'); ?></p>
    <p><a href="<?php echo wp_logout_url(home_url()); ?>">Logout</a></p>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_dashboard', 'custom_user_dashboard');
