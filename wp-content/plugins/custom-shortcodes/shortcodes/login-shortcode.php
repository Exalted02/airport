<?php
// =======================
// Frontend Login Shortcode
// =======================
function custom_user_login_form() {
    if ( is_user_logged_in() ) {
        echo "<p>You are already logged in.</p>";
        return;
    }

    ob_start(); ?>
    <form method="post" action="">
        <p>
            <label>Email</label><br>
            <input type="email" name="login_email" required>
        </p>

        <p>
            <label>Password</label><br>
            <input type="password" name="login_password" required>
        </p>

        <p>
            <input type="submit" name="custom_login_submit" value="Login">
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
                wp_redirect(home_url('/subskrypcje')); 
                exit;
            } else {
                echo "<p style='color:red'>You are not allowed to login here.</p>";
            }
        } else {
            echo "<p style='color:red'>Invalid email or password.</p>";
        }
    }
}
add_action('wp', 'handle_custom_user_login');
