<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function change_subscription_status_shortcode() {
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
		background:#00354A;
		padding:10px;
		border-radius:10px;
		color: #FFFFFF;
	}
	.subscription-status {
		width: 57.638%;
		padding:10px;
	}
	.subscription-text {
		width: 62.223%;
		padding:10px;
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
		fill: #429FE1;
		transition: fill 0.3s;
		height: 27px !important;
		width: 27px !important;
	}
	.plan-status {
		font-size: 24px;
		font-weight: 700;
		margin-left: 10px;
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
                <h3>Uaktualnij swój plan:</h3>
                <p class="d-flex">
                    <strong class="plan-status">Bezpłatny</strong>
                </p>
            </div>
            <div class="subscription-text">
                <p>Jako członek planu darmowego otrzymasz bezpłatnie powiadomienia o promocjach w ograniczonej formie.</p>
                <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="upgrade-button">Uaktualnij swój plan</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    foreach ( $subscriptions as $sub ) {
        $plan_id = $sub->subscription_plan_id;
        $status = ucfirst( $sub->status );
        $expiration = $sub->expiration_date ? date( 'd.m.Y', strtotime( $sub->expiration_date ) ) : 'Nigdy';

        $plan = get_post( $plan_id );
        $plan_name = $plan ? $plan->post_title : 'Nieznany plan';

        ?>
        <div class="subscription-status-card">
            <div class="subscription-status">
                <h3>Uaktualnij swój plan:</h3>
                <p class="d-flex">
                    <span class="elementor-icon-list-icon">
                        <svg aria-hidden="true" class="e-font-icon-svg e-fas-crosshairs" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M500 224h-30.364C455.724 130.325 381.675 56.276 288 42.364V12c0-6.627-5.373-12-12-12h-40c-6.627 0-12 5.373-12 12v30.364C130.325 56.276 56.276 130.325 42.364 224H12c-6.627 0-12 5.373-12 12v40c0 6.627 5.373 12 12 12h30.364C56.276 381.675 130.325 455.724 224 469.636V500c0 6.627 5.373 12 12 12h40c6.627 0 12-5.373 12-12v-30.364C381.675 455.724 455.724 381.675 469.636 288H500c6.627 0 12-5.373 12-12v-40c0-6.627-5.373-12-12-12zM288 404.634V364c0-6.627-5.373-12-12-12h-40c-6.627 0-12 5.373-12 12v40.634C165.826 392.232 119.783 346.243 107.366 288H148c6.627 0 12-5.373 12-12v-40c0-6.627-5.373-12-12-12h-40.634C119.768 165.826 165.757 119.783 224 107.366V148c0 6.627 5.373 12 12 12h40c6.627 0 12-5.373 12-12v-40.634C346.174 119.768 392.217 165.757 404.634 224H364c-6.627 0-12 5.373-12 12v40c0 6.627 5.373 12 12 12h40.634C392.232 346.174 346.243 392.217 288 404.634zM288 256c0 17.673-14.327 32-32 32s-32-14.327-32-32c0-17.673 14.327-32 32-32s32 14.327 32 32z"></path></svg>                    
                    </span>
                    <strong class="plan-status"><?php echo esc_html( $plan_name ); ?></strong>
                </p>
                <p>Pakiet daje nielimitowany dostęp do lotów w ramach wybranej sieci połączeń, pełną elastyczność zmian dat i tras</p>
            </div>
            <div class="subscription-text">
                <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="upgrade-button">Uaktualnij swój plan</a>
            </div>
        </div>
        <?php
    }

    return ob_get_clean();
}
add_shortcode( 'change_subscription', 'change_subscription_status_shortcode' );
