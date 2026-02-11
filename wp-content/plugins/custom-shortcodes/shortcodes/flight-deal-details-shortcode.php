<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

function custom_flight_deal_details_shortcode() {
    global $wpdb;

    // Get deal ID from URL
    $deal_id = get_query_var('deal_id');
    if (!$deal_id) return '<p>Nieprawidłowy identyfikator transakcji.</p>';

    // Get logged-in user
    $user_id = get_current_user_id();
    if (!$user_id) {
        return '<p>Zaloguj się, aby zobaczyć szczegóły oferty.</p>';
    }

    // Get user's saved airports (comma-separated)
    $user_airports = get_user_meta($user_id, 'airport', true);
    $airport_ids = array_filter(array_map('intval', explode(',', $user_airports)));

    if (empty($airport_ids)) {
        return '<p>Nie masz przypisanych żadnych lotnisk. Zaktualizuj swój profil, aby zobaczyć oferty lotów.</p>';
    }

    $table = $wpdb->prefix . 'flight_deals';
    $airport_table = $wpdb->prefix . 'airport_list';

    // Build query that checks both deal_id and user's airport_ids
    $placeholders = implode(',', array_fill(0, count($airport_ids), '%d'));

    $query = $wpdb->prepare("
        SELECT f.*, a.code AS airport_code 
        FROM $table f 
        LEFT JOIN $airport_table a ON f.airport_id = a.id 
        WHERE f.id = %d 
        AND f.status = 1
        AND f.airport_id IN ($placeholders)
    ", array_merge([$deal_id], $airport_ids));

    $deal = $wpdb->get_row($query);

    // If not found or user not allowed
    if (!$deal) {
        return '<p>Nie masz uprawnień do przeglądania tej oferty lotu.</p>';
    }

    // Prevent showing premium-only deals
	$is_show = true;
	$subscriptions = pms_get_member_subscriptions( array( 'user_id' => $user_id ) );
	if ($deal->offer_type == 1) {
		if (empty($subscriptions)) {
			$is_show = false;
		} else {
			$sub = $subscriptions[0];
			
			//Get plan details
			$plan = pms_get_subscription_plan( $sub->subscription_plan_id );
			// echo '<pre>'; print_r($plan); echo '</pre>';exit;
	
			$status          = $sub->get_status(); // active / canceled
			$billing_amount  = (float) $sub->billing_amount;
			$expiration_date = !empty($sub->expiration_date)
				? strtotime($sub->expiration_date)
				: null;
				
			$today = strtotime(date('Y-m-d'));

			// Default: hide
			$is_show = false;

			// Case 1: Active subscription with amount
			if (($status === 'active' && $billing_amount != 0) || ($status === 'active' && $plan->price != 0)) {
				$is_show = true;
			}

			// Case 2: Canceled, expired (<= today), amount not zero
			if (
				$status === 'canceled' &&
				$billing_amount != 0 &&
				$expiration_date !== null &&
				$expiration_date >= $today
			) {
				$is_show = true;
			}
		}
	}
	if(!$is_show){
		wp_redirect(home_url('/flight-deals'));
		exit;
	}
    /*if (intval($deal->offer_type) === 1) {
		$subscriptions = pms_get_member_subscriptions( array( 'user_id' => $user_id ) );
		if ( empty( $subscriptions ) || $subscriptions[0]->billing_amount == 0){
			wp_redirect(home_url('/flight-deals'));
			exit;
		}
    }*/

    // Prepare display values
    $image = !empty($deal->image) ? esc_url($deal->image) : esc_url(content_url('uploads/2025/10/noimage.png'));
    $title = esc_html($deal->purpose);
    $desc = wpautop(esc_html($deal->more_details));
    $price = esc_html(number_format($deal->price, 0));
	$fmt = new IntlDateFormatter('pl_PL', IntlDateFormatter::LONG, IntlDateFormatter::NONE, null, null, 'd MMM');
	$start = $fmt->format(new DateTime($deal->start_date));
	$end   = $fmt->format(new DateTime($deal->end_date));
    // $start = date('d F', strtotime($deal->start_date));
    // $end = date('d F', strtotime($deal->end_date));
    $booking_link = !empty($deal->booking_link) ? esc_url($deal->booking_link) : '';
    $airport_code = esc_html($deal->airport_code);

    ob_start();
    ?>
    <style>
    .deal-details-container {
        max-width: 1000px;
        margin: 60px auto;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 6px 24px rgba(0, 53, 74, 0.2);
        overflow: hidden;
        padding: 40px;
    }
    .deal-details-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 30px;
        margin-bottom: 30px;
    }
    .deal-details-header img {
        width: 350px;
        height: 250px;
        border-radius: 12px;
        object-fit: cover;
        flex-shrink: 0;
    }
    .deal-details-header-content {
        flex: 1;
    }
    .deal-details-title {
        font-size: 2rem;
        font-weight: 700;
        color: #00354a;
        margin-bottom: 10px;
    }
	.flight-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .route-badge {
        border: 1px solid #0073aa;
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 13px;
        font-weight: 500;
    }
    .unlock-badge {
        background: #f2f5f7;
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .unlock-badge svg {
        width: 13px;
        height: 13px;
        fill: #0073aa;
    }
    .deal-details-price {
        font-size: 1.6rem;
        font-weight: 700;
        color: #0073aa;
        margin-top: 10px;
    }
    .deal-details-info {
        color: #555;
        font-size: 1rem;
        margin-bottom: 20px;
    }
    .deal-details-desc {
        font-size: 1rem;
        color: #444;
        line-height: 1.8;
    }
	
	/* ===== BOOKING SECTION ===== */
    .booking-section {
        background: #f8fbff;
        border: 1px solid #d6e8f5;
        border-radius: 12px;
        padding: 10px 15px;
    }
    .booking-section h3 {
        margin: 0 0 5px;
        color: #00354a;
        font-size: 1.2rem;
        font-weight: 700;
    }
    .booking-link-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .booking-link {
        flex: 1;
        background: #fff;
        border: 1px solid #ccd;
		padding: 5px 14px;
		border-radius: 500px;
        font-size: 0.95rem;
        color: #333;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .copy-btn {
        background: #0073aa;
        color: #fff;
        border: none;
        padding: 10px 16px;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.3s;
        font-weight: 600;
    }
    .copy-btn:hover { color: #fff; }
	
	/* ===== BACK BUTTON ===== */
    .back-to-deals {
        display: inline-block;
        margin-top: 30px;
        background: #0073aa;
        color: #fff;
        padding: 12px 28px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 600;
        transition: background 0.3s;
    }
    .back-to-deals:hover {
        color: #fff;
    }
    @media (max-width: 768px) {
        .deal-details-header {
            flex-direction: column;
			align-items: start;
        }
        .deal-details-header img {
            width: 100%;
            height: 220px;
        }
    }
    </style>

    <div class="deal-details-container">
        <div class="deal-details-header">
            <img src="<?php echo $image; ?>" alt="Flight Deal">
            <div class="deal-details-header-content">
				<div class="flight-top">
                    <span class="route-badge"><?php echo $airport_code; ?></span>
                    <?php if ($deal->offer_type == 1): ?>
                        <span class="unlock-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M12 17a2 2 0 0 0 2-2v-1H10v1a2 2 0 0 0 2 2zm6-6V9a6 6 0 0 0-12 0h2a4 4 0 0 1 8 0v2H6a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9a2 2 0 0 0-2-2z"/>
                            </svg> PREMIUM
                        </span>
                    <?php endif; ?>
                </div>
                <div class="deal-details-title"><?php echo $title; ?></div>
                <div class="deal-details-info">
                    <i class="dashicons dashicons-calendar"></i>
                    <?php echo $start . ' – ' . $end; ?>
                </div>
                <div class="deal-details-price"><?php echo $price; ?> zł</div>
            </div>
        </div>
        <div class="deal-details-desc">
            <?php echo $desc; ?>
        </div>
		
		<?php if ($booking_link): ?>
        <div class="booking-section">
            <h3>Link do rezerwacji</h3>
            <div class="booking-link-wrapper">
                <div class="booking-link" id="bookingLink"><?php echo $booking_link; ?></div>
                <!--<button class="copy-btn" id="copyBookingLink">Copy</button>-->
            </div>
        </div>
        <?php endif; ?>
		
        <a href="<?php echo esc_url(home_url('/flight-deals')); ?>" class="back-to-deals">← Powrót do ofert</a>
    </div>
	
	<script>
    document.addEventListener('DOMContentLoaded', function() {
        const copyBtn = document.getElementById('copyBookingLink');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const linkText = document.getElementById('bookingLink').textContent.trim();
                navigator.clipboard.writeText(linkText).then(() => {
                    copyBtn.textContent = 'Copied!';
                    setTimeout(() => copyBtn.textContent = 'Copy', 1500);
                });
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('flight_deal_details', 'custom_flight_deal_details_shortcode');
