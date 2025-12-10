<?php

// Localize AJAX vars
function cs_enqueue_delete_account_script() {
	wp_enqueue_script(
		'delete-account-js',
		plugin_dir_url(__FILE__) . '../assets/js/delete-account.js',
		array('jquery'),   // <-- FIXED
		null,
		true
	);
	
    wp_localize_script(
        'jquery',
        'csDeleteAccount',
        [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('cs_delete_account_nonce')
        ]
    );
}
add_action('wp_enqueue_scripts', 'cs_enqueue_delete_account_script');


// AJAX handler
function cs_delete_user_account() {
    if (!isset($_POST['nonce']) ||
        !wp_verify_nonce($_POST['nonce'], 'cs_delete_account_nonce')) {

        wp_send_json_error("Błąd bezpieczeństwa.");
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error("Użytkownik nie jest zalogowany.");
    }

    require_once(ABSPATH . 'wp-admin/includes/user.php');

    $result = wp_delete_user($user_id);

    if ($result) {
        wp_send_json_success("Konto zostało usunięte.");
    } else {
        wp_send_json_error("Nie udało się usunąć konta.");
    }
}
add_action('wp_ajax_cs_delete_user_account', 'cs_delete_user_account');
