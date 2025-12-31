<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

function notification_switch_handler_shortcode() {
    if ( ! is_user_logged_in() ) {
        return ''; // nothing for guests
    }

    // Tell main plugin to print the script in the footer and pass initial data
    global $custom_notification_switch_needed, $custom_notification_switch_data;
    $custom_notification_switch_needed = true;

    $user_id = get_current_user_id();
    $custom_notification_switch_data = array(
        'email_notifications_switch' => get_user_meta($user_id, 'email_notifications', true) ?: 'no',
        'newsletter_switch' => get_user_meta($user_id, 'newsletter_subscription', true) ?: 'no',
        'nonce' => wp_create_nonce('save_notification_settings_nonce'),
        'ajax_url' => admin_url('admin-ajax.php'),
    );

    // return nothing (script will be printed in footer)
    return '';
}
add_shortcode('notification_switch_handler', 'notification_switch_handler_shortcode');

// ----------------------------------------------------
// AJAX: Save notification settings (email/newsletter)
// ----------------------------------------------------
function save_notification_settings_callback() {
    check_ajax_referer('save_notification_settings_nonce', 'security');

    if ( ! is_user_logged_in() ) {
        wp_send_json_error('Not logged in');
    }

    $user_id = get_current_user_id();
    $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
    $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';

    $allowed_fields = ['email_notifications', 'newsletter_subscription'];

    if ( in_array($field, $allowed_fields, true) ) {
        update_user_meta($user_id, $field, $value);
		
		// Only sync newsletter switch
        //if ($field === 'newsletter_subscription') {

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

			/**
			 * STEP 1 – Create or update subscriber (so tags can be added)
			 */
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

			/**
			 * STEP 2 – Apply tags
			 */
			$tag_status = ($value === "yes") ? "active" : "inactive";

			$tag_payload = [
				"tags" => [
					[
						"name"   => $field,
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


        //}
		
        wp_send_json_success('Saved');
    }

    wp_send_json_error('Invalid field');
}
add_action('wp_ajax_save_notification_settings', 'save_notification_settings_callback');

// ----------------------------------------------------
// Print the notification JS in footer only when shortcode was used
// The shortcode sets a global flag and data; we check here and print JS in footer
// ----------------------------------------------------
add_action('wp_footer', 'custom_notification_switch_print_script', 20);
function custom_notification_switch_print_script() {
    global $custom_notification_switch_needed, $custom_notification_switch_data;

    if (empty($custom_notification_switch_needed) || !is_user_logged_in()) {
        return;
    }

    $data = $custom_notification_switch_data ?: [
        'email_notifications_switch' => get_user_meta(get_current_user_id(), 'email_notifications', true) ?: 'no',
        'newsletter_switch' => get_user_meta(get_current_user_id(), 'newsletter_subscription', true) ?: 'no',
        'nonce' => wp_create_nonce('save_notification_settings_nonce'),
        'ajax_url' => admin_url('admin-ajax.php'),
    ];

    $data_json = wp_json_encode($data);
    ?>
    <script>
    (function($){
        var notifData = <?php echo $data_json; ?>;

        function setSwitcherState($switch, val) {
			// console.log($switch);
            if (!$switch.length) return;
            if (val === 'yes') {
                $switch.removeClass('jet-switcher--disable').addClass('jet-switcher--enable');
            } else {
                $switch.removeClass('jet-switcher--enable').addClass('jet-switcher--disable');
            }
        }

        function applySavedStates() {
            // console.log('[ns-live] Applying saved states…');
            setSwitcherState($('#email_notifications_switch .jet-switcher'), notifData.email_notifications_switch);
            setSwitcherState($('#newsletter_switch .jet-switcher'), notifData.newsletter_switch);
        }

        // Observe DOM changes to catch JetSwitchers after Elementor render
        const observer = new MutationObserver(() => {
            if ($('#email_notifications_switch .jet-switcher').length &&
                $('#newsletter_switch .jet-switcher').length) {
                // console.log('[ns-live] Switchers found, applying states');
                applySavedStates();
                observer.disconnect();
            }
        });

        observer.observe(document.body, {childList: true, subtree: true});

        // Handle toggles dynamically
        // $(document).on('click', '#email_notifications_switch, #email_notifications_switch *,' + '#newsletter_switch, #newsletter_switch *', function(e) {
		$(document).on('click', '.elementor-widget-jet-switcher', function(e) {
			e.stopPropagation(); // prevent multiple triggers
			var $switcher = $(this);
			var $jet_switcher = $switcher.find('.jet-switcher');

			var id = $switcher.attr('id');
			var newVal = $jet_switcher.hasClass('jet-switcher--enable') ? 'yes' : 'no';

			// console.log('[ns-live] Toggle →', id, newVal);
			// setSwitcherState($jet_switcher, newVal);

			var field = id === 'email_notifications_switch' ? 'email_notifications' :
						id === 'newsletter_switch' ? 'newsletter_subscription' : null;
			if (!field) return;

			$.post(notifData.ajax_url, {
				action: 'save_notification_settings',
				field: field,
				value: newVal,
				security: notifData.nonce
			}, function(res) {
				if (res && res.success) {
					showSavedToast();
					// console.log('[ns-live] Saved ✓');
				} else {
					console.warn('[ns-live] Save failed:', res);
				}
			}, 'json');
		});

        // Small green "Saved ✓" toast
        function showSavedToast() {
            var $toast = $('<div>')
                .text('Zapisano ✓')
                .css({
                    position: 'fixed',
                    bottom: '30px',
                    right: '30px',
                    background: '#28a745',
                    color: '#fff',
                    padding: '8px 15px',
                    borderRadius: '6px',
                    fontSize: '14px',
                    zIndex: 99999,
                    opacity: 0,
                    transition: 'opacity .3s ease'
                })
                .appendTo('body');
            setTimeout(() => $toast.css('opacity', 1), 50);
            setTimeout(() => $toast.fadeOut(400, () => $toast.remove()), 1500);
        }
    })(jQuery);
    </script>
    <?php
}
