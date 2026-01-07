<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function my_subscription_status_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div class="subscription-status-box">
            <p>Proszę zalogować się, aby zobaczyć szczegóły swojej subskrypcji.</p>
        </div>';
    }

    $user_id = get_current_user_id();

    if ( ! function_exists( 'pms_get_member_subscriptions' ) ) {
        return '<p><strong>Błąd:</strong> Wtyczka Paid Member Subscriptions nie jest aktywna.</p>';
    }

    // Pobierz subskrypcje (zwraca tablicę obiektów PMS_Member_Subscription)
    $subscriptions = pms_get_member_subscriptions( array( 'user_id' => $user_id ) );
	ob_start();
	
    ?>
    <style>
    .d-flex {
		display: flex;
	}
	.subscription-status-card {
		display: flex;
		font-family: "Roboto", Sans-serif;
		background:#EBF6EE;
		padding:10px;
		border-radius:10px;
		color: #00131B;
	}
	.subscription-status {
		width: 57.638%;
		padding:10px;
	}
	.subscription-text {
		width: 62.223%;
		padding: 10px;
		align-self: center;
		text-align: end;
	}
	.subscription-status-card h3 {
		font-size: 16px;
		font-weight: 600;
		line-height: 28px;
		margin: 0;
	}
	.elementor-icon-list-icon {
		display: flex;
		justify-content: center;
		align-items: center;
	}
	.elementor-icon-list-icon svg {
		fill: #34A853;
		transition: fill 0.3s;
		height: 27px !important;
		width: 27px !important;
	}
	.plan-status {
		font-size: 24px;
		font-weight: 700;
	}
	.subscription-text a {
		display: inline-block;
		background-color: #429FE1;
		font-family: "Roboto", Sans-serif;
		font-size: 18px;
		font-weight: 400;
		fill: #FFFFFF;
		color: #FFFFFF;
		border-style: none;
		border-radius: 25px 25px 25px 25px;
		padding: 10px 35px 10px 35px;
	}
	@media (max-width: 1050px) {
		.subscription-status-card {
			display: block;
		}
		.subscription-status, .subscription-text {
			width: 100%;
		}
	}
    </style>
    <?php
	
    if ( empty( $subscriptions ) ) {
        ?>
        <div class="subscription-status-card">
            <div class="subscription-status">
                <h3>Aktualna subskrypcja:</h3>
                <p class="d-flex">
                    <strong class="plan-status">Free</strong>
                </p>
            </div>
            <div class="subscription-text">
                <p>Jako członek planu darmowego otrzymasz bezpłatnie powiadomienia o promocjach w ograniczonej formie.</p>
                <a href="<?php echo esc_url( home_url( '/checkout' ) ); ?>" class="upgrade-button">Uaktualnij swój plan</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
	
	// echo '<pre>'; print_r($subscriptions);exit;
    foreach ( $subscriptions as $sub ) {
		// $a = pms_get_subscription_plan( $sub->subscription_plan_id );
        $plan_id = $sub->subscription_plan_id;
        $status = ucfirst( $sub->status );
        $expiration = $sub->expiration_date ? date( 'd.m.Y', strtotime( $sub->expiration_date ) ) : 'Nigdy';

        $plan = get_post( $plan_id );
		// echo '<pre>'; print_r($plan);exit;
        $plan_name = $plan ? $plan->post_title : 'Nieznany plan';

        ?>
        <div class="subscription-status-card">
            <div class="subscription-status">
                <h3>Aktualna subskrypcja:</h3>
                <p class="d-flex">
                    <span class="elementor-icon-list-icon">
                        <svg aria-hidden="true" class="e-font-icon-svg e-fas-check" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path></svg>                        
                    </span>
                    <strong class="plan-status"><?php echo esc_html( $plan_name ); ?></strong>
                </p>
                <p>Status: <strong><?php echo esc_html( $status ); ?></strong></p>
                <!--<p>Wygasa dnia: <strong><?php echo esc_html( $expiration ); ?></strong></p>-->
            </div>
            <div class="subscription-text">
				<?php
				if($plan->post_name != 'premium'){
				?>
					<p>Jako członek planu darmowego otrzymasz bezpłatnie powiadomienia o promocjach w ograniczonej formie.</p>
				<?php } ?>
                <a href="<?php echo esc_url( home_url( '/checkout' ) ); ?>" class="upgrade-button">Uaktualnij swój plan</a>
            </div>
        </div>
        <?php
    }

    return ob_get_clean();
}
add_shortcode( 'subscription_status', 'my_subscription_status_shortcode' );
