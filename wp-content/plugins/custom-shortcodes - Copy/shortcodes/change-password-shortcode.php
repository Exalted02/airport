<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shortcode: Change Password Form
 */
function custom_change_password_form_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Musisz być zalogowany, aby zmienić hasło.</p>';
    }

    $user_id = get_current_user_id();
    $provider = get_user_meta($user_id, 'default_password_nag', true);
	
    $is_social_user = $provider;

    ob_start();

    // Success or error messages
    if ( isset($_GET['password_updated']) && $_GET['password_updated'] === 'true' ) {
        echo '<div style="color: green; margin-bottom:10px;">Hasło zostało pomyślnie zmienione.</div>';
        $url = remove_query_arg('password_updated');
        echo '<script>history.replaceState(null, null, "'.$url.'");</script>';
    }

    if ( isset($_GET['password_error']) ) {
        $error = sanitize_text_field($_GET['password_error']);
        echo '<div style="color: red; margin-bottom:10px;">' . esc_html($error) . '</div>';
        $url = remove_query_arg('password_error');
        echo '<script>history.replaceState(null, null, "'.$url.'");</script>';
    }
    ?>

    <form method="post" action="" class="elementor-form" style="max-width:500px;">
        <div class="elementor-form-fields-wrapper elementor-labels-above">

            <?php if ( ! $is_social_user ): ?>
                <div class="elementor-field-type-password elementor-field-group elementor-column elementor-col-100 elementor-field-required">
                    <label for="old_password" class="elementor-field-label">Stare hasło <span style="color:red;">*</span></label>
                    <input type="password" name="old_password" class="elementor-field elementor-size-xs elementor-field-textual" required>
                </div>
            <?php else: ?>
                <div style="margin-bottom:10px; color:#555;">
                    <em>Zalogowano przez Google — możesz ustawić nowe hasło poniżej.</em>
                </div>
            <?php endif; ?>

            <div class="elementor-field-type-password elementor-field-group elementor-column elementor-col-100">
                <label for="new_password" class="elementor-field-label">Nowe hasło</label>
                <input type="password" name="new_password" class="elementor-field elementor-size-xs elementor-field-textual" required>
            </div>

            <div class="elementor-field-type-password elementor-field-group elementor-column elementor-col-100">
                <label for="confirm_password" class="elementor-field-label">Powtórz hasło</label>
                <input type="password" name="confirm_password" class="elementor-field elementor-size-xs elementor-field-textual" required>
            </div>

            <div class="elementor-field-type-submit elementor-field-group">
                <button type="submit" name="submit_password_change" class="elementor-button-custom elementor-size-sm">
                    <span class="elementor-button-content-wrapper">
                        <span class="elementor-button-text">Zapisz</span>
                    </span>
                </button>
            </div>

        </div>
    </form>

    <?php
    return ob_get_clean();
}
add_shortcode( 'account_password_form', 'custom_change_password_form_shortcode' );


/**
 * Handle Password Change Submission
 */
function handle_password_change_form_submission() {
    if ( isset($_POST['submit_password_change']) && is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        $provider = get_user_meta($user_id, 'default_password_nag', true);
        // $is_social_user = ! empty($provider);
        $is_social_user = $provider;

        $old_password     = $_POST['old_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Check new password match
        if ( $new_password !== $confirm_password ) {
            wp_redirect( esc_url( add_query_arg( 'password_error', urlencode('Nowe hasła nie są zgodne.'), wp_get_referer() ) ) );
            exit;
        }

        // Check password length
        if ( strlen($new_password) < 6 ) {
            wp_redirect( esc_url( add_query_arg( 'password_error', urlencode('Hasło musi mieć co najmniej 6 znaków.'), wp_get_referer() ) ) );
            exit;
        }

        // Normal users must validate old password
        if ( ! $is_social_user && ! wp_check_password( $old_password, $user->user_pass, $user_id ) ) {
            wp_redirect( esc_url( add_query_arg( 'password_error', urlencode('Nieprawidłowe stare hasło.'), wp_get_referer() ) ) );
            exit;
        }

        // Update password
        wp_set_password( $new_password, $user_id );
		
		update_user_meta($user_id, 'default_password_nag', 0);

        // Redirect success
        wp_redirect( esc_url( add_query_arg( 'password_updated', 'true', wp_get_referer() ) ) );
        exit;
    }
}
add_action('template_redirect', 'handle_password_change_form_submission');
