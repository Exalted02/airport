<?php
// =======================
// Registration Shortcode
// =======================
function custom_user_register_form() {
    global $wpdb;
    $airports = $wpdb->get_results("SELECT id, code, name FROM {$wpdb->prefix}airport_list ORDER BY name ASC");

    ob_start();

    // If already logged in
    if ( is_user_logged_in() ) {
        echo "<p>You are already registered and logged in.</p>";
        return ob_get_clean();
    }
    ?>

    <form method="post" action="">
        <p>
            <label>Email</label><br>
            <input type="email" name="reg_email" required>
        </p>

        <p>
            <label>Password</label><br>
            <input type="password" name="reg_password" required>
        </p>

        <p>
            <label>Choose Airport</label><br>
            <select name="airport" required>
                <option value="">-- Select Airport --</option>
                <?php foreach ($airports as $airport): ?>
                    <option value="<?php echo esc_attr($airport->id); ?>">
                        <?php echo esc_html($airport->code . ' - ' . $airport->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>
                <input type="checkbox" name="terms" required>
                I accept the Terms & Conditions
            </label>
        </p>

        <p>
            <input type="submit" name="custom_register_submit" value="Register">
        </p>
    </form>

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
        $airport  = intval($_POST['airport']);
        $terms    = isset($_POST['terms']);

        // Terms check
        if ( ! $terms ) {
            echo "<p style='color:red'>You must accept the terms.</p>";
            return;
        }

        // Email duplicate check
        if ( username_exists($email) || email_exists($email) ) {
            echo "<p style='color:red'>This email is already registered.</p>";
            return;
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
            // Save airport selection
            update_user_meta($user_id, 'airport', $airport);

            // Send WordPress default welcome email
            wp_new_user_notification($user_id, null, 'both');

            echo "<p style='color:green'>Registration successful. Please login.</p>";
        } else {
            echo "<p style='color:red'>Error: " . $user_id->get_error_message() . "</p>";
        }
    }
}
add_action('wp', 'handle_custom_user_registration');
