<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Referral System Integration (Normal + Nextend Social Login)
 */

// Save referral code from URL (?ref=CODE)
add_action('init', function() {
    if (isset($_GET['ref']) && !empty($_GET['ref'])) {
        if (!session_id()) session_start();
        $_SESSION['referral_code'] = sanitize_text_field($_GET['ref']);
    }
});

/**
 * Handle referral link storage when a user registers (normal signup)
 */
function handle_referral_on_registration($new_user_id) {
    if (!session_id()) session_start();

    if (!empty($_SESSION['referral_code'])) {
        $ref_code = sanitize_text_field($_SESSION['referral_code']);

        // Replace 'wrc_ref_code' with actual meta key name
        $referring_user = get_users([
            'meta_key'   => 'wrc_ref_code',
            'meta_value' => $ref_code,
            'number'     => 1,
            'fields'     => 'ID'
        ]);

        if (!empty($referring_user)) {
            $referrer_id = $referring_user[0];
            update_user_meta($new_user_id, 'referred_by', $referrer_id);

            // Optional: track referral count
            $count = (int) get_user_meta($referrer_id, 'referral_count', true);
            update_user_meta($referrer_id, 'referral_count', $count + 1);
        }

        unset($_SESSION['referral_code']);
    }
}

// Normal registration
add_action('user_register', 'handle_referral_on_registration', 10, 1);

// Nextend Social Login registration
add_action('nextend_social_login_register', function($user_id, $provider, $profile) {
    handle_referral_on_registration($user_id);
}, 10, 3);
