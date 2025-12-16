<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * ==========================================================
 * REFERRAL REWARDS SYSTEM (AJAX version)
 * ==========================================================
 *
 * Logic:
 *  - When a referred user (User B) subscribes to Premium via PMS,
 *    their referrer (User A) gets ONE pending reward.
 *  - User A can activate each reward manually using the shortcode:
 *      [referral_rewards]
 *  - Activation grants +1 month Premium.
 *  - Counter updates instantly via AJAX (no reload).
 */

// ------------------------
// Define your Premium plan ID
// ------------------------
if (!defined('PREMIUM_PLAN_ID')) {
    define('PREMIUM_PLAN_ID', 1350);
}
// ------------------------
// Define all Premium plan ID
// ------------------------
if (!defined('ALL_PREMIUM_PLAN_ID')) {
    define('ALL_PREMIUM_PLAN_ID', [1350, 2340, 2342]);
}

/* ------------------------------------------------
 * Helper: Queue FREE month AFTER buyer's active plan
 * ------------------------------------------------ */
function reward_free_month_for_recurring_buyer($user_id) {

    $subs = pms_get_member_subscriptions(['user_id' => $user_id]);
    if (!$subs) return;

    foreach ($subs as $sub) {
        if ($sub->status === 'active' && in_array($sub->subscription_plan_id, ALL_PREMIUM_PLAN_ID)) {

            // Push next billing date by 1 month
            if (!empty($sub->billing_next_payment)) {
                $new_date = date(
                    'Y-m-d H:i:s',
                    strtotime($sub->billing_next_payment . ' +1 month')
                );

                $sub->update([
                    'billing_next_payment' => $new_date,
                ]);

                error_log("✅ Free month applied to recurring subscription for user {$user_id}");
            }

            return;
        }
    }
}


// ----------------------------------------------------------
// 1️⃣ Reward when new Premium subscription inserted
// ----------------------------------------------------------
add_action('pms_member_subscription_insert', function($subscription_id, $data) {

    $plan_id = $data['subscription_plan_id'] ?? 0;

    if (!in_array($plan_id, ALL_PREMIUM_PLAN_ID)) {
        error_log("Subscription #{$subscription_id} (plan {$plan_id}) - no referral reward.");
        return;
    }

    $referred_user_id = $data['user_id'] ?? 0;
    if (!$referred_user_id) return;
	
    $referrer_id = get_user_meta($referred_user_id, 'referred_by', true);
    if (!$referrer_id) return;

    if (get_user_meta($referred_user_id, 'referral_reward_recorded', true)) return;

    // Add +1 pending reward
    $pending = (int) get_user_meta($referrer_id, 'pending_referral_rewards', true);
    update_user_meta($referrer_id, 'pending_referral_rewards', $pending + 1);

	// Buyer gets queued FREE month
    reward_free_month_for_recurring_buyer($referred_user_id);

    // Log details
    $details = get_user_meta($referrer_id, 'pending_referral_rewards_details', true);
    if (!is_array($details)) $details = [];
    $details[] = [
        'id' => uniqid('reward_', true),
        'from_user_id' => $referred_user_id,
        'source_subscription_id' => $subscription_id,
        'date' => current_time('mysql'),
        'note' => "Reward earned from user #{$referred_user_id}",
    ];
    update_user_meta($referrer_id, 'pending_referral_rewards_details', $details);

    update_user_meta($referred_user_id, 'referral_reward_recorded', 1);

    // Optional email
    $user = get_userdata($referrer_id);
    if ($user && !empty($user->user_email)) {
        wp_mail(
            $user->user_email,
            'You earned a referral reward!',
            "Your referral just purchased a Premium plan! You now have 1 pending Premium reward.\nVisit your Rewards page to activate it."
        );
    }

    error_log("✅ Referral reward granted to user #{$referrer_id} from referred #{$referred_user_id}");
}, 10, 2);


// ----------------------------------------------------------
// 2️⃣ Reward when existing subscription upgraded to Premium
// ----------------------------------------------------------
add_action('pms_member_subscription_update', function($subscription_id, $new_data, $old_data) {

    $new_plan = $new_data['subscription_plan_id'] ?? 0;
    $old_plan = $old_data['subscription_plan_id'] ?? 0;
	
	if (!in_array($new_plan, ALL_PREMIUM_PLAN_ID) || in_array($old_plan, ALL_PREMIUM_PLAN_ID)) return;
    // if ($new_plan != PREMIUM_PLAN_ID || $old_plan == PREMIUM_PLAN_ID) return;

    $referred_user_id = $new_data['user_id'] ?? 0;
    if (!$referred_user_id) return;
	
    $referrer_id = get_user_meta($referred_user_id, 'referred_by', true);
    if (!$referrer_id) return;

    if (get_user_meta($referred_user_id, 'referral_reward_recorded', true)) return;

    $pending = (int) get_user_meta($referrer_id, 'pending_referral_rewards', true);
    update_user_meta($referrer_id, 'pending_referral_rewards', $pending + 1);

	// Buyer gets queued FREE month
    reward_free_month_for_recurring_buyer($referred_user_id);

    $details = get_user_meta($referrer_id, 'pending_referral_rewards_details', true);
    if (!is_array($details)) $details = [];
    $details[] = [
        'id' => uniqid('reward_', true),
        'from_user_id' => $referred_user_id,
        'source_subscription_id' => $subscription_id,
        'date' => current_time('mysql'),
        'note' => "Reward earned from user #{$referred_user_id} (upgrade)",
    ];
    update_user_meta($referrer_id, 'pending_referral_rewards_details', $details);

    update_user_meta($referred_user_id, 'referral_reward_recorded', 1);

    $user = get_userdata($referrer_id);
    if ($user && !empty($user->user_email)) {
        wp_mail(
            $user->user_email,
            'You earned a referral reward!',
            "Your referral just upgraded to Premium! You now have 1 pending Premium reward.\nVisit your Rewards page to activate it."
        );
    }

    error_log("✅ Referral reward granted (upgrade) to user #{$referrer_id}");
}, 10, 3);


// ----------------------------------------------------------
// 3️⃣ Shortcode: [referral_rewards]
// ----------------------------------------------------------
add_shortcode('referral_rewards', function() {

    if (!is_user_logged_in()) {
        return '<p>Musisz być zalogowany, aby zobaczyć swoje nagrody.</p>';
    }

    $user_id = get_current_user_id();
    $pending = (int) get_user_meta($user_id, 'pending_referral_rewards', true);

    ob_start(); ?>
    <div class="referral-rewards" style="background:#f9f9f9;padding:20px;border-radius:10px;">
        <h3>Twoje nagrody za polecenia</h3>
        <p>Twoje udane polecenia: <strong id="pendingCounter"><?php echo $pending; ?></strong></p>

        <?php if ($pending > 0): ?>
            <form id="referralRewardForm">
                <?php wp_nonce_field('activate_referral_reward'); ?>
                <button type="submit"
                    style="padding:10px 20px;background:#429FE1;color:#fff;border:none;border-radius:25px;cursor:pointer;">
                    Aktywuj 1-miesięczną nagrodę Premium
                </button>
            </form>
            <div id="rewardMessage" style="margin-top:10px;"></div>

            <script>
            jQuery(document).ready(function($){
                $('#referralRewardForm').on('submit', function(e){
                    e.preventDefault();
                    var $btn = $(this).find('button');
                    $btn.prop('disabled', true).text('Przetwarzanie...');

                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'activate_referral_reward',
                        _ajax_nonce: $('input[name="_wpnonce"]').val()
                    }, function(response){
                        $btn.prop('disabled', false).text('Aktywuj 1-miesięczną nagrodę Premium');
                        if(response.success){
							$('#rewardMessage').html('<span style="color:green;">' + response.data.message + '</span>');
							
							// Animate counter and update UI
							$('#pendingCounter').fadeOut(200, function(){
								$(this).text(response.data.new_pending).fadeIn(200, function(){
									if (parseInt(response.data.new_pending) === 0) {
										// Hide form & show message when no rewards left
										$('#referralRewardForm').fadeOut(300, function(){
											$('#rewardMessage').html('<span style="color:green;">' + response.data.message + '</span>');
											$('<p>Brak oczekujących nagród do aktywacji.</p>').hide().appendTo('.referral-rewards').fadeIn(300);
										});
									}
								});
							});
						} else {
							$('#rewardMessage').html('<span style="color:red;">' + response.data + '</span>');
						}

                    });
                });
            });
            </script>
        <?php else: ?>
            <p>Brak oczekujących nagród do aktywacji.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});


// ----------------------------------------------------------
// 4️⃣ AJAX Handler for instant reward activation
// ----------------------------------------------------------
add_action('wp_ajax_activate_referral_reward', function() {
    check_ajax_referer('activate_referral_reward');

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('Musisz być zalogowany.');
    }

    $pending = (int) get_user_meta($user_id, 'pending_referral_rewards', true);
    if ($pending <= 0) {
        wp_send_json_error('Brak oczekujących nagród.');
    }

    global $wpdb;
    $subs = pms_get_member_subscriptions(['user_id' => $user_id]);
    $active_sub = null;
    if ($subs && is_array($subs)) {
        foreach ($subs as $sub) {
            if ($sub->status === 'active') {
                $active_sub = $sub;
                break;
            }
        }
    }

    $plan_id_to_use = PREMIUM_PLAN_ID;
    $plan = pms_get_subscription_plan($plan_id_to_use);

    if (!$plan) {
        wp_send_json_error('Nie znaleziono planu Premium.');
    }

    if ($active_sub && !empty($active_sub->expiration_date)) {
        $new_exp = date('Y-m-d H:i:s', strtotime($active_sub->expiration_date . ' +1 month'));
        $active_sub->update(['expiration_date' => $new_exp]);
        $msg = 'Twój plan Premium został przedłużony o 1 miesiąc!';
    } else {
        // Ensure PMS member exists
        $member = pms_get_member($user_id);
        if (!$member) {
            $member = new PMS_Member(['user_id' => $user_id]);
            $member->save();
        }

        $table_name = $wpdb->prefix . 'pms_member_subscriptions';
        $subscription_data = [
            'user_id'              => $user_id,
            'subscription_plan_id' => $plan->id,
            'status'               => 'active',
            'start_date'           => current_time('mysql'),
            'expiration_date'      => date('Y-m-d H:i:s', strtotime('+1 month')),
        ];

        $inserted = $wpdb->insert($table_name, $subscription_data);
        if (!$inserted) {
            wp_send_json_error('Błąd: nie udało się aktywować nagrody.');
        }

        $msg = 'Twoja 1-miesięczna nagroda Premium została aktywowana!';
    }

    // Decrease pending counter
    $new_pending = max(0, $pending - 1);
    update_user_meta($user_id, 'pending_referral_rewards', $new_pending);

    $details = get_user_meta($user_id, 'pending_referral_rewards_details', true);
    if (is_array($details) && count($details) > 0) {
        array_shift($details);
        update_user_meta($user_id, 'pending_referral_rewards_details', $details);
    }

    wp_send_json_success([
        'message' => $msg,
        'new_pending' => $new_pending,
    ]);
});
