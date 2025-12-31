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
	// START Mailchimp INTEGRATIONS
	// Mailchimp API Credentials
	$settings = get_option('pms_email_marketing_settings');
	if ( is_array($settings) 
		 && isset($settings['platforms']['mailchimp']) ) {

		$api_key = $settings['platforms']['mailchimp']['api_key'] ?? '';
		$list_id = $settings['platforms']['mailchimp']['list_id'] ?? '';
	}else{
		return;
	}
	
	$email    = wp_get_current_user()->user_email;
	$dc       = substr($api_key, strpos($api_key, '-') + 1);
	$hash     = md5(strtolower($email));

	// STEP 1 – Create or update subscriber (so tags can be added)
	$subscriber_data = [
		"email_address" => $email,
		"status_if_new" => "subscribed",
	];

	$ch = curl_init("https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$hash}");
	curl_setopt($ch, CURLOPT_USERPWD, "user:{$api_key}");
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($subscriber_data));
	curl_exec($ch);
	curl_close($ch);
	
	// STEP 2 – Get all existing tags for the user
	$ch = curl_init("https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$hash}/tags");
	curl_setopt($ch, CURLOPT_USERPWD, "user:{$api_key}");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	curl_close($ch);

	$data = json_decode($response, true);

	$tags_to_remove = [];

	if (!empty($data['tags'])) {
		foreach ($data['tags'] as $tag) {
			$tags_to_remove[] = [
				'name'   => $tag['name'],
				'status' => 'inactive'
			];
		}
	}
	
	// STEP 3 – Deactivate ALL tags in one request
	if (!empty($tags_to_remove)) {

		$payload = [
			'tags' => $tags_to_remove
		];

		$ch = curl_init("https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$hash}/tags");
		curl_setopt($ch, CURLOPT_USERPWD, "user:{$api_key}");
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_exec($ch);
		curl_close($ch);
	}
	

	// STEP 4 – Apply tags
	$tag_status = "active";
	$tag_payload = [
		"tags" => [
			[
				"name"   => 'deleted user',
				"status" => $tag_status
			]
		]
	];

	$ch = curl_init("https://{$dc}.api.mailchimp.com/3.0/lists/{$list_id}/members/{$hash}/tags");
	curl_setopt($ch, CURLOPT_USERPWD, "user:{$api_key}");
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tag_payload));
	$response = curl_exec($ch);
	curl_close($ch);
	// END Mailchimp INTEGRATIONS
	
    require_once(ABSPATH . 'wp-admin/includes/user.php');

    $result = wp_delete_user($user_id);

    if ($result) {
        wp_send_json_success("Konto zostało usunięte.");
    } else {
        wp_send_json_error("Nie udało się usunąć konta.");
    }
}
add_action('wp_ajax_cs_delete_user_account', 'cs_delete_user_account');
