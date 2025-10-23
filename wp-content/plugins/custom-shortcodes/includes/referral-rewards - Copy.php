<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * REFERRAL REWARDS SYSTEM
 * ==========================================================
 *
 * Logic:
 *  - When a referred user (User B) completes a subscription via PMS,
 *    their referrer (User A) receives ONE pending reward credit.
 *  - User A can later activate each reward manually on the
 *    referral page using the [referral_rewards] shortcode.
 *  - Activation gives +1 month of Premium.
 */

// ------------------------
// Define your Premium plan ID as a constant
// ------------------------
if (!defined('PREMIUM_PLAN_ID')) {
    define('PREMIUM_PLAN_ID', 1350);
}

// ----------------------------------------------------------
// 1️⃣ Grant reward after successful PMS subscription
// ----------------------------------------------------------
add_action('pms_member_subscription_insert', function($subscription_id, $data) {

    // Ensure subscription plan ID exists
    $plan_id = $data['subscription_plan_id'] ?? 0;

    // Only grant reward if Premium plan
    if ($plan_id != PREMIUM_PLAN_ID) {
        error_log("Subscription #{$subscription_id} is plan #{$plan_id}. No referral reward granted.");
        return;
    }

    error_log("Referral reward hook triggered for subscription #{$subscription_id}");

    // Get user ID of the referred user
    $referred_user_id = $data['user_id'] ?? 0;
    if (empty($referred_user_id)) {
        error_log("Referred user ID not found for subscription #{$subscription_id}");
        return;
    }

    // Find referrer (User A)
    $referrer_id = get_user_meta($referred_user_id, 'referred_by', true);
    if (empty($referrer_id)) {
        error_log("No referrer found for user #{$referred_user_id}");
        return;
    }

    // Allow only ONE reward per referred user
    if (get_user_meta($referred_user_id, 'referral_reward_recorded', true)) {
        error_log("Reward already recorded for referred user #{$referred_user_id}");
        return;
    }

    // Add pending reward credit
    $pending_count = (int) get_user_meta($referrer_id, 'pending_referral_rewards', true);
    update_user_meta($referrer_id, 'pending_referral_rewards', $pending_count + 1);

    // Store details for audit
    $details = get_user_meta($referrer_id, 'pending_referral_rewards_details', true);
    if (!is_array($details)) $details = [];
    $details[] = [
        'id'                     => uniqid('reward_', true),
        'from_user_id'           => $referred_user_id,
        'source_subscription_id' => $subscription_id,
        'date'                   => current_time('mysql'),
        'note'                   => sprintf('Reward earned from user #%d', $referred_user_id),
    ];
    update_user_meta($referrer_id, 'pending_referral_rewards_details', $details);

    // Mark referred user so they can’t trigger again
    update_user_meta($referred_user_id, 'referral_reward_recorded', 1);

    // Optional email notice
    $ref_user = get_userdata($referrer_id);
    if ($ref_user && !empty($ref_user->user_email)) {
        wp_mail(
            $ref_user->user_email,
            'You earned a referral reward!',
            "Your referral just purchased a Premium plan! You now have 1 pending Premium reward.\nVisit your Rewards page to activate it."
        );
    }

    // Debug log
    error_log("Referral reward granted to user #{$referrer_id} from referred #{$referred_user_id}");

}, 10, 2);
// ----------------------------------------------------------
// 3️⃣ Grant reward when subscription is updated to Premium
// ----------------------------------------------------------
add_action('pms_member_subscription_update', function($subscription_id, $new_data, $old_data) {

    $new_plan_id = $new_data['subscription_plan_id'] ?? 0;
    $old_plan_id = $old_data['subscription_plan_id'] ?? 0;

    // Only grant reward if updated to Premium and wasn't Premium before
    if ($new_plan_id != PREMIUM_PLAN_ID || $old_plan_id == PREMIUM_PLAN_ID) {
        error_log("Subscription #{$subscription_id} updated. No referral reward granted. New plan: {$new_plan_id}, Old plan: {$old_plan_id}");
        return;
    }

    error_log("Referral reward hook triggered for updated subscription #{$subscription_id} (now Premium)");

    // Get user ID of the referred user
    $referred_user_id = $new_data['user_id'] ?? 0;
    if (empty($referred_user_id)) {
        error_log("Referred user ID not found for updated subscription #{$subscription_id}");
        return;
    }

    // Find referrer (User A)
    $referrer_id = get_user_meta($referred_user_id, 'referred_by', true);
    if (empty($referrer_id)) {
        error_log("No referrer found for user #{$referred_user_id}");
        return;
    }

    // Allow only ONE reward per referred user
    if (get_user_meta($referred_user_id, 'referral_reward_recorded', true)) {
        error_log("Reward already recorded for referred user #{$referred_user_id}");
        return;
    }

    // Add pending reward credit
    $pending_count = (int) get_user_meta($referrer_id, 'pending_referral_rewards', true);
    update_user_meta($referrer_id, 'pending_referral_rewards', $pending_count + 1);

    // Store details for audit
    $details = get_user_meta($referrer_id, 'pending_referral_rewards_details', true);
    if (!is_array($details)) $details = [];
    $details[] = [
        'id'                     => uniqid('reward_', true),
        'from_user_id'           => $referred_user_id,
        'source_subscription_id' => $subscription_id,
        'date'                   => current_time('mysql'),
        'note'                   => sprintf('Reward earned from user #%d (subscription upgraded to Premium)', $referred_user_id),
    ];
    update_user_meta($referrer_id, 'pending_referral_rewards_details', $details);

    // Mark referred user so they can’t trigger again
    update_user_meta($referred_user_id, 'referral_reward_recorded', 1);

    // Optional email notice
    $ref_user = get_userdata($referrer_id);
    if ($ref_user && !empty($ref_user->user_email)) {
        wp_mail(
            $ref_user->user_email,
            'You earned a referral reward!',
            "Your referral just upgraded to a Premium plan! You now have 1 pending Premium reward.\nVisit your Rewards page to activate it."
        );
    }

    // Debug log
    error_log("Referral reward granted to user #{$referrer_id} from referred #{$referred_user_id} (updated subscription)");

}, 10, 3);


// ----------------------------------------------------------
// 2️⃣ Shortcode: [referral_rewards]
// ----------------------------------------------------------
add_shortcode('referral_rewards', function() {

    if (!is_user_logged_in()) {
        return '<p>Please log in to view your rewards.</p>';
    }

    $user_id = get_current_user_id();
    $pending = (int) get_user_meta($user_id, 'pending_referral_rewards', true);

    ob_start();
    ?>
    <div class="referral-rewards" style="background:#f9f9f9;padding:20px;border-radius:10px;">
        <h3>Twoje nagrody za polecenia</h3>
        <p>Obecnie masz <strong><?php echo $pending; ?></strong> oczekująca nagroda.</p>
        <?php
        if ($pending > 0):

            if (isset($_POST['activate_reward']) && check_admin_referer('activate_referral_reward')) {

                // Get current subscriptions
                $subs = pms_get_member_subscriptions( array( 'user_id' => $user_id ) );
				// echo '<pre>';print_r($user_id);exit;
				// echo '<pre>';print_r($subs);exit;
                $active_sub = null;
                if ($subs && is_array($subs)) {
                    foreach ($subs as $sub) {
                        if ($sub->status === 'active') {
                            $active_sub = $sub;
                            break;
                        }
                    }
                }
				
				$plan_id_to_use = PREMIUM_PLAN_ID; // pick first as default
                $plan = pms_get_subscription_plan($plan_id_to_use);
				
				if ($plan){
					if ($active_sub && !empty($active_sub->expiration_date)) {
						// Extend existing plan
						$new_exp = date('Y-m-d H:i:s', strtotime($active_sub->expiration_date . ' +1 month'));
						$active_sub->update(['expiration_date' => $new_exp]);
						$msg = 'Twój plan Premium został przedłużony o 1 miesiąc!';
					} else {
						global $wpdb;

						// Ensure PMS member exists
						$member = pms_get_member($user_id);
						if (!$member) {
							$member = new PMS_Member(array('user_id' => $user_id));
							$member->save();
						}

						$table_name = $wpdb->prefix . 'pms_member_subscriptions';

						// Prepare new subscription data
						$subscription_data = array(
							'user_id'              => $user_id,
							'subscription_plan_id' => $plan->id,
							'status'               => 'active',
							'start_date'           => current_time('mysql'),
							'expiration_date'      => date('Y-m-d H:i:s', strtotime('+1 month')),
						);

						// Insert manually into the database
						$inserted = $wpdb->insert($table_name, $subscription_data);

						if ($inserted) {
							$new_id = $wpdb->insert_id;
							$msg = 'Twoja 1-miesięczna nagroda Premium została aktywowana!';
						} else {
							$msg = 'Błąd: nie udało się aktywować nagrody.';
						}
					}

					// Decrease pending counter
					$new_pending = max(0, $pending - 1);
                    update_user_meta($user_id, 'pending_referral_rewards', $new_pending);
                    $pending = $new_pending;
					
					// Optional: remove one reward record (if tracking details)
                    $details = get_user_meta($user_id, 'pending_referral_rewards_details', true);
                    if (is_array($details) && count($details) > 0) {
                        array_shift($details);
                        update_user_meta($user_id, 'pending_referral_rewards_details', $details);
                    }

					echo '<div style="color:green;margin-top:10px;">' . esc_html($msg) . '</div>';
				} else {
                    echo '<div style="color:red;margin-top:10px;">Nie znaleziono planu Premium!</div>';
                }
            }
            ?>
            <form method="post">
                <?php wp_nonce_field('activate_referral_reward'); ?>
                <button type="submit" name="activate_reward"
                        style="padding:10px 20px;background:#429FE1;color:#fff;border:none;border-radius:25px;cursor:pointer;">
                    Aktywuj 1-miesięczną nagrodę Premium
                </button>
            </form>
        <?php
        endif;
        ?>
    </div>
    <?php
    return ob_get_clean();
});
