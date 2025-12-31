<?php
// =======================
// Registration Shortcode
// =======================
function custom_user_register_form() {
    global $wpdb;
    $airports = $wpdb->get_results("SELECT id, code, name FROM {$wpdb->prefix}airport_list ORDER BY name ASC");

    ob_start();

	// Display error messages based on query param
    if ( isset($_GET['register_error']) ) {
        if ( $_GET['register_error'] === 'terms' ) {
            echo '<p style="color:red">Musisz zaakceptować warunki.</p>';
        } elseif ( $_GET['register_error'] === 'duplicate_email' ) {
            echo '<p style="color:red">Ten adres e-mail jest już zarejestrowany.</p>';
        }

        // Remove the query param to prevent repeat on refresh
        $url = remove_query_arg('register_error');
        echo '<script>history.replaceState(null, null, "'.$url.'");</script>';
    }
	
    ?>

    <form method="post" action="">
        <p>
            <label>Adres e-mail*</label><br>
            <input type="email" name="reg_email" required>
        </p>

        <p>
            <label>Hasło*</label><br>
            <input type="password" name="reg_password" required>
        </p>

        <p>
            <label>Wybrane lotniska</label><br>
            <select name="airports[]" class="common-select2" multiple data-placeholder="Wybierz lotniska">
                <?php foreach ($airports as $airport): ?>
                    <option value="<?php echo esc_attr($airport->id); ?>">
                        <?php echo esc_html($airport->code . ' - ' . $airport->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label style="line-height: 1.5">
                <input type="checkbox" name="terms" id="terms_checkbox" required>
                Akceptuję <a target="_blank" href="<?php echo esc_url(home_url('/regulamin-serwisu')); ?>">Regulamin</a> i <a target="_blank" href="<?php echo esc_url(home_url('/polityka-prywatnosci')); ?>">Politykę Prywatności</a>.
            </label>
			<label id="terms_error_msg" style="display:none;color:red;">
				Musisz zaakceptować warunki.
			</label>
        </p>


        <p>
            <input type="submit" name="custom_register_submit" value="Utwórz konto" class="elementor-login-register-button">
        </p>
		
		<p>
            Masz konto? <a href="<?php echo esc_url(home_url('/login')); ?>">Zaloguj się</a>
        </p>
    </form>
	<script>
	document.addEventListener('DOMContentLoaded', function () {

		const termsCheckbox = document.getElementById('terms_checkbox');
		const errorMsg      = document.getElementById('terms_error_msg');

		function blockIfNotAccepted(e) {
			if (!termsCheckbox || !termsCheckbox.checked) {
				e.preventDefault();
				e.stopImmediatePropagation();
				e.stopPropagation();

				errorMsg.style.display = 'block';
				termsCheckbox.scrollIntoView({ behavior: 'smooth', block: 'center' });

				return false;
			}

			errorMsg.style.display = 'none';
			return true;
		}

		// Capture events BEFORE Nextend
		['pointerdown', 'mousedown', 'click'].forEach(function(evt) {
			document.querySelectorAll('.nsl-button').forEach(function(button) {
				button.addEventListener(evt, blockIfNotAccepted, true);
			});
		});

		// Hide error when checkbox is checked
		if (termsCheckbox) {
			termsCheckbox.addEventListener('change', function () {
				if (this.checked) {
					errorMsg.style.display = 'none';
				}
			});
		}

	});
	</script>


    <?php
    return ob_get_clean();
}
add_shortcode('custom_register', 'custom_user_register_form');


// =======================
// Handle Registration
// =======================
function handle_custom_user_registration() {
    if ( isset($_POST['custom_register_submit']) ) {
        $email    = sanitize_email($_POST['reg_email']);
        $password = sanitize_text_field($_POST['reg_password']);
        $airports = isset($_POST['airports']) ? array_map('intval', $_POST['airports']) : array();
        $terms    = isset($_POST['terms']);

        // Terms check
        if ( ! $terms ) {
			$redirect_url = wp_get_referer() ? wp_get_referer() : home_url('register/');
			$redirect_url = remove_query_arg(['register_error'], $redirect_url);
			$redirect_url = add_query_arg('register_error', 'terms', $redirect_url);
			wp_safe_redirect($redirect_url);
			exit;
			
            // echo "<p style='color:red'>Musisz zaakceptować warunki.</p>";
            // return;
        }

        // Email duplicate check
        if ( username_exists($email) || email_exists($email) ) {
			$redirect_url = wp_get_referer() ? wp_get_referer() : home_url('register/');
			$redirect_url = remove_query_arg(['register_error'], $redirect_url);
			$redirect_url = add_query_arg('register_error', 'duplicate_email', $redirect_url);
			wp_safe_redirect($redirect_url);
			exit;
			
            // echo "<p style='color:red'>Ten adres e-mail jest już zarejestrowany.</p>";
            // return;
        }

        // Create new user as Front User
        $userdata = array(
            'user_login' => $email,
            'user_pass'  => $password,
            'user_email' => $email,
            'role'       => 'front_user',
        );

        $user_id = wp_insert_user($userdata);

        if ( ! is_wp_error($user_id) ) {
			// Convert selected airports to comma-separated string
            $airport_list = implode(',', $airports);

            // Save airport(s) selection
            update_user_meta($user_id, 'airport', $airport_list);
			
            // Save referral code
            update_user_meta($user_id, 'wrc_ref_code', $user_id . generate_unique_code());

            // Send WordPress default welcome email
            // wp_new_user_notification($user_id, null, 'both');
			send_frontend_welcome_email( $user_id, array(
				'type'     => 'normal',
				'password' => $password,
			) );
			
			/*
			// Redirect to login page after successful registration
            wp_safe_redirect(home_url('/login/')); 
			exit;*/
			// After successful registration do login and redirect to another page
			wp_set_current_user($user_id);
			wp_set_auth_cookie($user_id);
			
			// =======================
			// CREATE FREE PMS SUBSCRIPTION (VERSION SAFE)
			// =======================
			if ( function_exists( 'pms_get_member_subscriptions' ) &&
				 class_exists( 'PMS_Member_Subscription' ) ) {

				$existing = pms_get_member_subscriptions( array(
					'user_id' => $user_id,
				) );

				if ( empty( $existing ) ) {

					$subscription = new PMS_Member_Subscription();

					$subscription_id = $subscription->insert( array(
						'user_id'              => $user_id,
						'subscription_plan_id' => 2345, // FREE PLAN ID
						'status'               => 'active',
						'start_date'           => current_time( 'mysql' ),
						'expiration_date'      => date( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
					) );

					if ( $subscription_id ) {
						error_log( "PMS subscription INSERTED (ID: {$subscription_id}) for user {$user_id}" );
					} else {
						error_log( "PMS subscription insert FAILED for user {$user_id}" );
					}
				}

			} else {
				error_log( 'PMS subscription API not available' );
			}


			// Redirect after login
			// wp_redirect(home_url('/flight-deals')); 
			// exit;
			
            // echo "<p style='color:green'>Rejestracja pomyślna. Zaloguj się.</p>";
        } else {
            echo "<p style='color:red'>Error: " . $user_id->get_error_message() . "</p>";
        }
    }
}
add_action('wp', 'handle_custom_user_registration');
