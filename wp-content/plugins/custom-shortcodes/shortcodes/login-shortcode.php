<?php
// =======================
// Frontend Login Shortcode
// =======================
function custom_user_login_form() {
    if ( is_user_logged_in() ) {
        echo "<p>You are already logged in.</p>";
        return;
    }

    ob_start(); 
	
	// Display error messages based on query param
    if ( isset($_GET['login_error']) ) {
        if ( $_GET['login_error'] === 'invalid' ) {
            echo '<p style="color:red">Nieprawidłowy adres e-mail lub hasło.</p>';
        } elseif ( $_GET['login_error'] === 'no_role' ) {
            echo '<p style="color:red">Nie masz uprawnień, aby się tutaj zalogować.</p>';
        }

        // Remove the query param to prevent repeat on refresh
        $url = remove_query_arg('login_error');
        echo '<script>history.replaceState(null, null, "'.$url.'");</script>';
    }
	
	?>
    <form method="post" action="">
        <p>
            <label>Adres e-mail*</label><br>
            <input type="email" name="login_email" required>
        </p>

        <p>
            <label>Hasło*</label><br>
            <input type="password" name="login_password" required>
        </p>
		
		<p>
            <a href="<?php echo esc_url(home_url('/reset-password')); ?>">Zapomniałeś hasła?</a>
        </p>
		
        <p>
            <input type="submit" name="custom_login_submit" value="Zaloguj się" class="elementor-login-register-button">
        </p>
		
		<p>
            Nie masz konta?<a href="<?php echo esc_url(home_url('/register')); ?>"> Rejestracja</a>
        </p>
		
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_login', 'custom_user_login_form');


// =======================
// Handle Login
// =======================
function handle_custom_user_login() {
    if ( isset($_POST['custom_login_submit']) ) {
        $email    = sanitize_email($_POST['login_email']);
        $password = sanitize_text_field($_POST['login_password']);

        $user = get_user_by('email', $email);

        if ( $user && wp_check_password($password, $user->user_pass, $user->ID) ) {
            
            if ( in_array('front_user', (array) $user->roles) ) {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID);

                // Redirect after login
                wp_redirect(home_url('/flight-deals')); 
                exit;
            } else {
				// User exists but has wrong role
                $redirect_url = wp_get_referer() ? wp_get_referer() : home_url('login/');
                $redirect_url = remove_query_arg(['login_error'], $redirect_url);
                $redirect_url = add_query_arg('login_error', 'no_role', $redirect_url);
                wp_safe_redirect($redirect_url);
                exit;
            }
        } else {
			// Wrong email or password
            $redirect_url = wp_get_referer() ? wp_get_referer() : home_url('login/');
            $redirect_url = remove_query_arg(['login_error'], $redirect_url);
            $redirect_url = add_query_arg('login_error', 'invalid', $redirect_url);
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}
add_action('wp', 'handle_custom_user_login');
